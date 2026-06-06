<?php
declare(strict_types=1);

namespace App\Application\Web;

use App\Application\Maintenance\MaintenanceService;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoSiteImageRepository;
use App\Shared\Config\DynamicConfig;

final class WebCheckoutService
{
    private const SETTING_WEB_COMPRAS = 'web_compras_habilitadas';

    private const READ_ACTIONS = [
        'bootstrap_landing',
        'rifas_activas', 'config_publica',
        'inventario', 'progreso', 'buscar_cliente_checkout', 'buscar_numeros',
    ];

    public function __construct(private readonly DynamicConfig $dynamicConfig)
    {
    }

    public function execute(string $action, array $payload, array $files): array
    {
        if (!in_array($action, self::READ_ACTIONS, true) && !$this->areWebPurchasesEnabled()) {
            return [
                'success' => false,
                'message' => 'Las compras en línea están temporalmente deshabilitadas.',
            ];
        }

        return match ($action) {
            'crear_respaldo' => \PaymentBackupsController::crearRespaldo($payload),
            'ir_openpay' => \OpenPayController::irAOpenPay($payload),
            'crear_transferencia_completa' => $this->createTransfer($payload, $files),
            'bootstrap_landing' => $this->bootstrapLanding($payload),
            'rifas_activas' => $this->getActiveRaffles(),
            'config_publica' => $this->getPublicConfig(),
            'inventario' => $this->getPublicInventory($payload),
            'progreso' => $this->getPublicProgress($payload),
            'buscar_cliente_checkout' => $this->lookupCustomerByPhone($payload),
            'buscar_numeros' => $this->searchPublicNumbers($payload),
            default => ['success' => false, 'message' => 'Accion no valida'],
        };
    }

    public function arePurchasesAllowed(): bool
    {
        return $this->areWebPurchasesEnabled();
    }

    private function createTransfer(array $payload, array $files): array
    {
        if (empty($files['comprobante']) || !is_array($files['comprobante'])) {
            return ['success' => false, 'message' => 'Comprobante requerido'];
        }

        $file = $files['comprobante'];
        $err = (int)($file['error'] ?? \UPLOAD_ERR_OK);
        if ($err !== \UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => self::uploadErrorMessage($err)];
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'message' => 'Archivo temporal inválido. Intenta de nuevo o usa otra imagen.'];
        }

        if (!self::isAllowedComprobanteMime($tmp, (string)($file['type'] ?? ''))) {
            return ['success' => false, 'message' => 'Formato no permitido (usa JPG, PNG o WebP).'];
        }

        if (((int)($file['size'] ?? 0)) > (5 * 1024 * 1024)) {
            return ['success' => false, 'message' => 'Archivo muy pesado (max 5MB)'];
        }

        $transfer = \TransfersController::crearTransferencia(array_merge($payload, [
            'ticket_ids' => $payload['ticket_ids'] ?? ($_POST['ticket_ids'] ?? null),
        ]));
        if (empty($transfer['success']) || empty($transfer['id_transfer'])) {
            return $transfer;
        }
        $idTransfer = (int)$transfer['id_transfer'];

        $name = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', (string)($file['name'] ?? 'comprobante'));
        $relativePath = 'uploads/comprobantes/' . $name;
        $absolutePath = self::resolveComprobanteAbsolutePath($relativePath);
        $dir = dirname($absolutePath);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'No se pudo crear la carpeta de comprobantes: ' . $dir];
        }

        if (!is_writable($dir)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'Sin permiso de escritura en ' . $dir];
        }

        if (!move_uploaded_file($tmp, $absolutePath)) {
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'No se pudo guardar el archivo'];
        }

        $base = defined('BASE_URL') ? rtrim((string)\BASE_URL, '/') : '';
        $fileUrl = ($base !== '' ? $base . '/' : '/') . $relativePath;

        $n = \Db::update('transfers', ['url_transfer' => $fileUrl], 'id_transfer = :id', [':id' => $idTransfer]);
        if ($n < 1) {
            @unlink($absolutePath);
            \Db::delete('transfers', 'id_transfer = :id', [':id' => $idTransfer]);

            return ['success' => false, 'message' => 'Error actualizando transferencia'];
        }

        return ['success' => true, 'code_transfer' => $transfer['code_transfer'] ?? null];
    }

    private function areWebPurchasesEnabled(): bool
    {
        $raw = strtolower(trim((string)$this->dynamicConfig->get(self::SETTING_WEB_COMPRAS, '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor.',
            \UPLOAD_ERR_PARTIAL => 'La subida quedó incompleta. Intenta de nuevo.',
            \UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            \UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para subidas (tmp).',
            \UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal.',
            \UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida.',
            default => 'Error al recibir el archivo (código ' . $code . ').',
        };
    }

    private static function isAllowedComprobanteMime(string $tmp, string $browserType): bool
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/pjpeg'];
        $browserType = strtolower(trim($browserType));
        if ($browserType !== '' && in_array($browserType, $allowed, true)) {
            return true;
        }
        if (!is_file($tmp) || !is_readable($tmp) || !function_exists('finfo_open')) {
            return false;
        }
        $f = finfo_open(\FILEINFO_MIME_TYPE);
        if ($f === false) {
            return false;
        }
        $mime = strtolower((string)finfo_file($f, $tmp));
        finfo_close($f);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private static function resolveComprobanteAbsolutePath(string $relativePath): string
    {
        $bases = [];
        if (defined('ROOT_PATH') && \ROOT_PATH !== false && \ROOT_PATH !== '') {
            $bases[] = rtrim((string)\ROOT_PATH, '/');
        }
        $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($doc !== '') {
            $bases[] = $doc;
        }
        $bases = array_values(array_unique(array_filter($bases)));

        foreach ($bases as $base) {
            $full = $base . '/' . $relativePath;
            $parent = dirname($full);
            if (is_dir($parent) && is_writable($parent)) {
                return $full;
            }
            if (is_writable($base)) {
                return $full;
            }
        }

        return ($bases[0] ?? '') . '/' . $relativePath;
    }

    private function getActiveRaffles(): array
    {
        $rows = (new PdoRaffleRepository())->findAllActive();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id_raffle' => (int)$r['id_raffle'],
                'title_raffle' => $r['title_raffle'],
                'description_raffle' => $r['description_raffle'],
                'price_raffle' => (float)$r['price_raffle'],
                'digits_raffle' => (int)$r['digits_raffle'],
                'type_raffle' => $r['type_raffle'] ?? 'automatic',
                'min_quantity_raffle' => (int)($r['min_quantity_raffle'] ?? 1),
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    private function getPublicConfig(): array
    {
        return [
            'success' => true,
            'data' => $this->buildPublicConfigData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicConfigData(): array
    {
        $maintenance = new MaintenanceService($this->dynamicConfig);

        return [
            'maintenance_mode' => $maintenance->isPublicBlocked(),
            'maintenance_message' => $maintenance->getMaintenanceMessage(),
            'sales_blocked' => $maintenance->areSalesBlocked(),
            'sales_blocked_message' => $maintenance->getSalesBlockedMessage(),
            'images' => (new PdoSiteImageRepository())->asKeyMap(),
            'web_id_raffle' => $this->dynamicConfig->get('web_id_raffle', ''),
            'pricing' => \App\Application\Pricing\RaffleQuantityPricing::fromConfig($this->dynamicConfig)->toPublicArray(),
            'contact' => [
                'whatsapp' => trim((string)$this->dynamicConfig->get('whatsapp', '')),
                'whatsapp_chat_url' => trim((string)$this->dynamicConfig->get('whatsapp_chat_url', '')),
                'social_instagram_url' => trim((string)$this->dynamicConfig->get('social_instagram_url', '')),
                'social_facebook_url' => trim((string)$this->dynamicConfig->get('social_facebook_url', '')),
            ],
            'settings' => $this->landingSettingsMap(),
        ];
    }

    /**
     * Ajustes mínimos para la landing (evita settings.ajax.php al cargar).
     *
     * @return array<string, string>
     */
    private function landingSettingsMap(): array
    {
        return [
            'web_id_raffle' => trim((string)$this->dynamicConfig->get('web_id_raffle', '')),
            'web_compras_habilitadas' => trim((string)$this->dynamicConfig->get('web_compras_habilitadas', '1')),
            'web_mensaje_compras_bloqueadas' => trim((string)$this->dynamicConfig->get('web_mensaje_compras_bloqueadas', '')),
            'barra' => trim((string)$this->dynamicConfig->get('barra', '')),
            'pricing_tiered_enabled' => trim((string)$this->dynamicConfig->get('pricing_tiered_enabled', '1')),
            'pricing_first_unit' => trim((string)$this->dynamicConfig->get('pricing_first_unit', '1200')),
            'pricing_tier1_unit' => trim((string)$this->dynamicConfig->get('pricing_tier1_unit', '1200')),
            'pricing_tier2_unit' => trim((string)$this->dynamicConfig->get('pricing_tier2_unit', '1000')),
            'pricing_bulk_threshold' => trim((string)$this->dynamicConfig->get('pricing_bulk_threshold', '40')),
            'social_instagram_url' => trim((string)$this->dynamicConfig->get('social_instagram_url', '')),
            'social_facebook_url' => trim((string)$this->dynamicConfig->get('social_facebook_url', '')),
            'whatsapp' => trim((string)$this->dynamicConfig->get('whatsapp', '')),
            'whatsapp_chat_url' => trim((string)$this->dynamicConfig->get('whatsapp_chat_url', '')),
        ];
    }

    private function bootstrapLanding(array $payload): array
    {
        $config = $this->buildPublicConfigData();
        $rifas = $this->getActiveRaffles()['data'] ?? [];
        $idRaffle = $this->resolveLandingRaffleId($config, $rifas, (int)($payload['id_raffle'] ?? 0));

        $progreso = null;
        if ($idRaffle > 0) {
            $progress = $this->getPublicProgress(['id_raffle' => $idRaffle]);
            $progreso = !empty($progress['success']) ? $progress : null;
        }

        return [
            'success' => true,
            'data' => [
                'config' => $config,
                'rifas' => $rifas,
                'id_raffle_resolved' => $idRaffle > 0 ? $idRaffle : null,
                'progreso' => $progreso,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<array<string, mixed>> $rifas
     */
    private function resolveLandingRaffleId(array $config, array $rifas, int $requestedId): int
    {
        if ($requestedId > 0) {
            foreach ($rifas as $r) {
                if ((int)($r['id_raffle'] ?? 0) === $requestedId) {
                    return $requestedId;
                }
            }
        }

        $idCfg = (int)trim((string)($config['web_id_raffle'] ?? ''));
        if ($idCfg > 0) {
            foreach ($rifas as $r) {
                if ((int)($r['id_raffle'] ?? 0) === $idCfg) {
                    return $idCfg;
                }
            }
        }

        if (count($rifas) > 0) {
            return (int)($rifas[0]['id_raffle'] ?? 0);
        }

        return 0;
    }

    /**
     * @param array<string, mixed>|null $inventario
     * @return array<string, mixed>|null
     */
    private function progressFromInventory(?array $inventario, int $idRaffle): ?array
    {
        if (is_array($inventario) && !empty($inventario['success'])) {
            $stats = $inventario['stats'] ?? null;
            if (is_array($stats) && (int)($stats['total'] ?? 0) > 0) {
                $total = (int)$stats['total'];
                $vendidos = (int)($stats['vendidos'] ?? 0);

                return [
                    'success' => true,
                    'total' => $total,
                    'vendidos' => $vendidos,
                    'porcentaje' => round(($vendidos * 100) / $total, 2),
                ];
            }
        }

        $progress = $this->getPublicProgress(['id_raffle' => $idRaffle]);

        return !empty($progress['success']) ? $progress : null;
    }

    private function getPublicInventory(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        $_POST['id_raffle'] = (int)($payload['id_raffle'] ?? 0);
        $_POST['grilla'] = '1';

        return \NumerosController::obtenerInventario();
    }

    private function searchPublicNumbers(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        $_POST['id_raffle'] = (int)($payload['id_raffle'] ?? 0);
        $_POST['search'] = trim((string)($payload['search'] ?? ''));
        $_POST['status'] = '0';

        $result = \NumerosController::obtenerInventario();
        if (!empty($result['success']) && is_array($result['data'] ?? null)) {
            $result['data'] = array_slice($result['data'], 0, 150);
        }

        return $result;
    }

    private function getPublicProgress(array $payload): array
    {
        if (!class_exists('NumerosController')) {
            require_once dirname(__DIR__, 3) . '/controllers/numeros.controller.php';
        }

        return \NumerosController::obtenerProgreso((int)($payload['id_raffle'] ?? 0));
    }

    private function lookupCustomerByPhone(array $payload): array
    {
        $phone = preg_replace('/\D+/', '', (string)($payload['phone_customer'] ?? ''));
        if (strlen($phone) !== 10) {
            return ['success' => false, 'message' => 'Teléfono inválido'];
        }

        $row = \Db::fetchOne(
            'SELECT name_customer, lastname_customer, email_customer, department_customer, city_customer
             FROM customers WHERE phone_customer = :p AND status_customer = 1 LIMIT 1',
            [':p' => $phone]
        );

        if (!$row) {
            return ['success' => true, 'data' => null];
        }

        return [
            'success' => true,
            'data' => [
                'name_customer' => (string)($row->name_customer ?? ''),
                'lastname_customer' => (string)($row->lastname_customer ?? ''),
                'email_customer' => (string)($row->email_customer ?? ''),
                'department_customer' => (string)($row->department_customer ?? ''),
                'city_customer' => (string)($row->city_customer ?? ''),
            ],
        ];
    }
}

