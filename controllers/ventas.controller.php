<?php

require_once __DIR__ . '/mail.controller.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Application\Marketing\MetaConversionsApi;

/**
 * Ventas — persistencia directa en MySQL (sin API REST).
 */
class VentasController
{
    public const TABLE = 'sales';
    private const STATUS_TICKET_AVAILABLE = 0;
    private const STATUS_TICKET_SOLD = 1;
    private const STATUS_TICKET_RESERVED = 2;
    private const MAX_LOG_BYTES = 5242880;

    private static function baseSalesSelectSql(): string
    {
        return 'SELECT s.id_sale, s.code_sale, s.total_sale, s.payment_method_sale, s.status_sale, s.date_created_sale, s.quantity_sale,
            s.id_admin_sale, s.source_sale, s.id_raffle_sale, s.id_customer_sale,
            c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer, c.city_customer,
            r.title_raffle,
            COALESCE(a.email_admin, \'Sistema\') AS email_admin
            FROM sales s
            INNER JOIN customers c ON c.id_customer = s.id_customer_sale
            INNER JOIN raffles r ON r.id_raffle = s.id_raffle_sale
            LEFT JOIN admins a ON a.id_admin = s.id_admin_sale';
    }

    public static function obtenerVentas()
    {
        $filtros = self::obtenerFiltros();
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = min(200, max(1, (int)($_POST['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $s = trim($filtros['search'] ?? '');

        $where = ['1=1'];
        $params = [];

        if (!empty($filtros['idRaffle'])) {
            $where[] = 's.id_raffle_sale = :idRaffle';
            $params[':idRaffle'] = $filtros['idRaffle'];
        }
        if (!empty($filtros['metodoPago'])) {
            $where[] = 's.payment_method_sale = :mp';
            $params[':mp'] = $filtros['metodoPago'];
        }
        if (!empty($filtros['idAdmin'])) {
            $where[] = 's.id_admin_sale = :idAdmin';
            $params[':idAdmin'] = $filtros['idAdmin'];
        }
        if (!empty($filtros['sourceSale'])) {
            $where[] = 's.source_sale = :src';
            $params[':src'] = $filtros['sourceSale'];
        }
        if (!empty($filtros['dateFrom']) && !empty($filtros['dateTo'])) {
            $where[] = 'DATE(s.date_created_sale) BETWEEN :df AND :dt';
            $params[':df'] = $filtros['dateFrom'];
            $params[':dt'] = $filtros['dateTo'];
        }

        $searchOr = [];
        if ($s !== '') {
            if (str_contains($s, '@')) {
                $searchOr[] = 'c.email_customer LIKE :s';
                $params[':s'] = '%' . $s . '%';
            } elseif (ctype_digit($s)) {
                $searchOr[] = 'c.phone_customer = :sphone';
                $searchOr[] = 's.code_sale = :scode';
                $params[':sphone'] = $s;
                $params[':scode'] = $s;
            } elseif (preg_match('/^[a-zA-Z0-9\-]+$/', $s) && preg_match('/\d/', $s)) {
                $searchOr[] = 's.code_sale = :scode2';
                $searchOr[] = 'c.name_customer LIKE :sn';
                $searchOr[] = 'c.lastname_customer LIKE :sl';
                $params[':scode2'] = $s;
                $params[':sn'] = '%' . $s . '%';
                $params[':sl'] = '%' . $s . '%';
            } else {
                $searchOr[] = 'c.name_customer LIKE :sn2';
                $searchOr[] = 'c.lastname_customer LIKE :sl2';
                $params[':sn2'] = '%' . $s . '%';
                $params[':sl2'] = '%' . $s . '%';
            }
            $where[] = '(' . implode(' OR ', $searchOr) . ')';
        }

        $sql = self::baseSalesSelectSql() . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.id_sale DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $ventas = Db::fetchAll($sql, $params);

        $countSql = 'SELECT COUNT(*) FROM sales s
            INNER JOIN customers c ON c.id_customer = s.id_customer_sale
            INNER JOIN raffles r ON r.id_raffle = s.id_raffle_sale
            WHERE ' . implode(' AND ', $where);
        $st = Db::pdo()->prepare($countSql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        return ['success' => true, 'data' => $ventas, 'total' => $total];
    }

    /**
     * Crea venta con una sola transacción InnoDB: reserva (0→2), venta, confirmación (2→1).
     * Cualquier fallo o ROLLBACK deja los tickets otra vez en disponible (0): no quedan “colgados” en 2.
     *
     * @param array<string, mixed> $data
     * @param int[]|null $ticketIds id_ticket a vender; si null, se eligen al azar entre disponibles.
     */
    public static function crearVenta($data, $ticketIds = null, bool $enforceRaffleTotal = true)
    {
        return self::withVentaLock(function () use ($data, $ticketIds, $enforceRaffleTotal) {
            self::logVenta('INICIO_CREAR_VENTA', ['data' => $data, 'ticketIds_input' => $ticketIds]);

            $validacion = self::validarDatosVenta($data);
            if (!$validacion['success']) {
                return $validacion;
            }

            $cantidad = (int)($data['quantity_sale'] ?? 0);
            $idRaffle = (int)($data['id_raffle'] ?? 0);

            $totalInsert = (float)($data['total_sale'] ?? 0);
            if ($enforceRaffleTotal) {
                $precioSrv = self::resolverTotalDesdePrecioRifa($idRaffle, $cantidad);
                if (!$precioSrv['success']) {
                    return $precioSrv;
                }
                $totalInsert = $precioSrv['total'];
                $enviado = (float)($data['total_sale'] ?? 0);
                if (!self::montosEquivalentesCOP($enviado, $totalInsert)) {
                    self::logVenta('TOTAL_RECHAZADO_NO_COINCIDE', [
                        'enviado' => $enviado,
                        'esperado' => $totalInsert,
                        'id_raffle' => $idRaffle,
                        'cantidad' => $cantidad,
                    ]);

                    return [
                        'success' => false,
                        'message' => 'El total no coincide con el precio vigente. Recarga la página e intenta de nuevo.',
                    ];
                }
            }

            if (is_array($ticketIds) && count($ticketIds) > 0) {
                $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
                if (count($ticketIds) !== $cantidad) {
                    return ['success' => false, 'message' => 'La cantidad de tickets no coincide con quantity_sale'];
                }
                self::logVenta('TICKETS_FORZADOS', $ticketIds);
            } else {
                $rows = Db::fetchAll(
                    'SELECT id_ticket
                     FROM tickets
                     WHERE id_raffle_ticket = :r AND status_ticket = 0
                     ORDER BY RAND()
                     LIMIT ' . (int)$cantidad,
                    [':r' => $idRaffle]
                );
                if (count($rows) < $cantidad) {
                    return ['success' => false, 'message' => 'No hay suficientes nros disponibles'];
                }
                $ticketIds = array_map(static fn($row) => (int)$row->id_ticket, $rows);
                self::logVenta('TICKETS_ALEATORIOS_SELECCIONADOS', $ticketIds);
            }

            self::logVenta('TICKETS_FINAL_PRE_TX', $ticketIds);

            $pdo = Db::pdo();
            $idVenta = null;
            $idCliente = null;

            try {
                $pdo->beginTransaction();

                $paymentBackupId = isset($data['id_payment_backup']) && $data['id_payment_backup'] !== ''
                    ? (int)$data['id_payment_backup']
                    : null;

                if (self::reservarTicketsEnTransaccion($pdo, $ticketIds, $paymentBackupId) === null) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'No se pudieron reservar todos los nros. Intenta de nuevo.'];
                }

                $idCliente = self::obtenerOCrearCliente($data);
                self::logVenta('CLIENTE', ['idCliente' => $idCliente]);
                if (!$idCliente) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Error al procesar cliente'];
                }

                $idAdmin = $data['id_admin'] ?? $data['id_admin_sale'] ?? ($_SESSION['user_id'] ?? null);
                $idVenta = Db::insert('sales', [
                    'id_customer_sale' => (int)$idCliente,
                    'id_raffle_sale' => $idRaffle,
                    'code_sale' => trim((string)$data['code_sale']),
                    'quantity_sale' => count($ticketIds),
                    'total_sale' => $totalInsert,
                    'payment_method_sale' => trim((string)$data['payment_method_sale']),
                    'status_sale' => self::STATUS_TICKET_SOLD,
                    'id_admin_sale' => $idAdmin !== null && $idAdmin !== '' ? (int)$idAdmin : null,
                    'source_sale' => $data['source_sale'] ?? $data['source_transfer'] ?? null,
                    // Hora de venta en zona PHP (TIMEZONE / America/Bogota), no la de MySQL (CURRENT_TIMESTAMP).
                    'date_created_sale' => date('Y-m-d H:i:s'),
                ]);

                self::logVenta('VENTA_CREADA', ['idVenta' => $idVenta]);

                $stUp = $pdo->prepare(
                    'UPDATE tickets SET status_ticket = :sold, id_customer_ticket = :cid, id_sale_ticket = :sid
                     WHERE id_ticket = :tid AND status_ticket = :res'
                );
                foreach ($ticketIds as $idTicket) {
                    $stUp->execute([
                        ':sold' => self::STATUS_TICKET_SOLD,
                        ':cid' => (int)$idCliente,
                        ':sid' => (int)$idVenta,
                        ':tid' => $idTicket,
                        ':res' => self::STATUS_TICKET_RESERVED,
                    ]);
                    if ($stUp->rowCount() !== 1) {
                        $pdo->rollBack();
                        self::logVenta('TICKETS_FALLIDOS_EN_CONFIRMACION', ['id' => $idTicket]);
                        return [
                            'success' => false,
                            'message' => 'No se pudo confirmar todos los tickets. La operación fue revertida.',
                        ];
                    }
                }

                self::insertSaleItems($pdo, (int)$idVenta, $ticketIds, $totalInsert);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::logVenta('ERROR_TX_VENTA', ['error' => $e->getMessage()]);
                $msg = 'Error al crear venta';
                if (str_contains($e->getMessage(), 'uk_si_ticket')) {
                    $msg = 'Conflicto al registrar los nros vendidos. Intenta de nuevo.';
                }

                return ['success' => false, 'message' => $msg];
            }

            try {
                AppContainer::get()->audit()->record('sale.created', 'sale', (int)$idVenta, null, [
                    'code_sale' => trim((string)$data['code_sale']),
                    'quantity' => count($ticketIds),
                    'total' => $totalInsert,
                    'id_raffle' => $idRaffle,
                ], isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            } catch (Throwable) {
                /* best effort */
            }

            $sendSaleEmail = !array_key_exists('send_sale_email', (array)$data)
                || filter_var($data['send_sale_email'], FILTER_VALIDATE_BOOLEAN);
            if ($sendSaleEmail) {
                try {
                    MailController::enviarCorreoVenta((int)$idVenta);
                } catch (Throwable $e) {
                    self::logVenta('ERROR_CORREO', ['idVenta' => $idVenta, 'error' => $e->getMessage()]);
                }
            } else {
                self::logVenta('CORREO_OMITIDO', ['idVenta' => $idVenta, 'motivo' => 'send_sale_email=false']);
            }

            $metaPurchase = self::buildMetaPurchasePayload(
                (int)$idVenta,
                (int)$idCliente,
                $data,
                $ticketIds,
                $totalInsert
            );
            try {
                $extraUserData = is_array($data['meta_user_data'] ?? null)
                    ? $data['meta_user_data']
                    : [];
                $saleCode = (string)($metaPurchase['code_sale'] ?? $idVenta);
                $guard = new \App\Application\Marketing\MetaPixelGuard();
                if ($guard->shouldSendPurchase($saleCode)) {
                    $eventId = MetaConversionsApi::purchaseEventId(
                        (int)$idVenta,
                        (string)($metaPurchase['code_sale'] ?? '')
                    );
                    if (MetaConversionsApi::sendPurchase($metaPurchase, $eventId, $extraUserData)) {
                        $guard->markPurchaseSent($saleCode);
                        try {
                            AppContainer::get()->audit()->record('meta.purchase.sent', 'sale', (int)$idVenta, null, [
                                'code_sale' => $saleCode,
                            ]);
                        } catch (Throwable) {
                            /* best effort */
                        }
                    }
                } else {
                    self::logVenta('META_PURCHASE_OMITIDO', [
                        'idVenta' => $idVenta,
                        'code_sale' => $saleCode,
                        'motivo' => 'ya_enviado_o_codigo_vacio',
                    ]);
                }
            } catch (Throwable $e) {
                self::logVenta('ERROR_META_CAPI_PURCHASE', ['idVenta' => $idVenta, 'error' => $e->getMessage()]);
            }

            return ['success' => true, 'id_sale' => $idVenta];
        });
    }

    /**
     * @param list<int> $ticketIds
     */
    private static function insertSaleItems(\PDO $pdo, int $saleId, array $ticketIds, float $totalSale): void
    {
        if ($ticketIds === []) {
            return;
        }
        $unitPrice = round($totalSale / count($ticketIds), 2);
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT id_ticket, number_ticket FROM tickets WHERE id_ticket IN ({$placeholders})"
        );
        $stmt->execute($ticketIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // uk_si_ticket: un ticket solo puede tener una fila; reactivar si fue anulado antes.
        $ins = $pdo->prepare(
            'INSERT INTO sale_items (id_sale, id_ticket, number_ticket, unit_price, status_item)
             VALUES (?,?,?,?,\'active\')
             ON DUPLICATE KEY UPDATE
               id_sale = VALUES(id_sale),
               number_ticket = VALUES(number_ticket),
               unit_price = VALUES(unit_price),
               status_item = \'active\',
               cancelled_at = NULL,
               cancelled_by = NULL'
        );
        foreach ($rows as $row) {
            $ins->execute([
                $saleId,
                (int)$row['id_ticket'],
                (string)$row['number_ticket'],
                $unitPrice,
            ]);
        }
    }

    public static function crearVentaMixta($data)
    {
        $cantidad = (int)($data['quantity_sale'] ?? 0);
        $idRaffle = (int)($data['id_raffle'] ?? 0);
        $forzado = trim((string)($data['premiado_ticket'] ?? ''));

        if ($cantidad <= 0 || $idRaffle <= 0) {
            return ['success' => false, 'message' => 'Datos inválidos'];
        }

        $idForzado = null;
        if ($forzado !== '') {
            $rowForzado = Db::fetchOne(
                'SELECT id_ticket
                 FROM tickets
                 WHERE id_raffle_ticket = :r
                   AND status_ticket = 0
                   AND number_ticket = :n
                 LIMIT 1',
                [':r' => $idRaffle, ':n' => $forzado]
            );
            if (!$rowForzado) {
                return ['success' => false, 'message' => "El nro $forzado ya está vendido o no está disponible."];
            }
            $idForzado = (int)$rowForzado->id_ticket;
        }

        $needRandom = $cantidad - ($idForzado ? 1 : 0);
        if ($needRandom < 0) {
            return ['success' => false, 'message' => 'Cantidad inválida'];
        }

        $params = [':r' => $idRaffle];
        $sqlRandom = 'SELECT id_ticket
                      FROM tickets
                      WHERE id_raffle_ticket = :r
                        AND status_ticket = 0';
        if ($idForzado) {
            $sqlRandom .= ' AND id_ticket <> :f';
            $params[':f'] = $idForzado;
        }
        $sqlRandom .= ' ORDER BY RAND() LIMIT ' . (int)$needRandom;

        $rowsRandom = $needRandom > 0 ? Db::fetchAll($sqlRandom, $params) : [];
        if (count($rowsRandom) < $needRandom) {
            return ['success' => false, 'message' => 'No hay suficientes nros disponibles'];
        }

        $ticketIds = array_map(static fn($row) => (int)$row->id_ticket, $rowsRandom);
        if ($idForzado) {
            $ticketIds[] = $idForzado;
        }

        self::logVenta('MIXTA_TICKETS_ELEGIDOS', $ticketIds);

        return self::crearVenta($data, $ticketIds, true);
    }

    /**
     * Reserva filas dentro de la transacción activa: solo pasa 0→2 si siguen disponibles.
     * Con $forPaymentBackupId, un ticket en reservado (2) solo es válido si pertenece a ese respaldo.
     *
     * @param int[] $ids
     * @return int[]|null null si alguna reserva falló (el caller debe hacer rollBack).
     */
    private static function reservarTicketsEnTransaccion(\PDO $pdo, array $ids, ?int $forPaymentBackupId = null): ?array
    {
        $stAvail = $pdo->prepare(
            'UPDATE tickets SET status_ticket = :r WHERE id_ticket = :id AND status_ticket = :a'
        );
        $stCheck = $pdo->prepare(
            'SELECT id_ticket FROM tickets WHERE id_ticket = :id AND status_ticket = :r LIMIT 1'
        );
        $stBackupLink = $pdo->prepare(
            'SELECT 1 FROM payment_backup_tickets
             WHERE id_payment_backup = :bid AND id_ticket = :tid LIMIT 1'
        );

        foreach ($ids as $id) {
            $stAvail->execute([
                ':r' => self::STATUS_TICKET_RESERVED,
                ':id' => $id,
                ':a' => self::STATUS_TICKET_AVAILABLE,
            ]);
            if ($stAvail->rowCount() === 1) {
                continue;
            }

            $stCheck->execute([
                ':id' => $id,
                ':r' => self::STATUS_TICKET_RESERVED,
            ]);
            if (!$stCheck->fetchColumn()) {
                self::logVenta('ERROR_RESERVA_TICKET', ['id' => $id]);

                return null;
            }

            if ($forPaymentBackupId !== null && $forPaymentBackupId > 0) {
                $stBackupLink->execute([
                    ':bid' => $forPaymentBackupId,
                    ':tid' => $id,
                ]);
                if (!$stBackupLink->fetchColumn()) {
                    self::logVenta('ERROR_RESERVA_TICKET_OTRO_BACKUP', [
                        'id' => $id,
                        'id_payment_backup' => $forPaymentBackupId,
                    ]);

                    return null;
                }
            }
        }

        self::logVenta('TICKETS_RESERVADOS_EN_TX', $ids);

        return $ids;
    }

    public static function anularVenta($id_sale)
    {
        if (empty($id_sale)) {
            return ['success' => false, 'message' => 'ID inválido'];
        }
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'No autorizado'];
        }
        try {
            return AppContainer::get()->sales()->cancelTotal((int)$id_sale, $adminId);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function obtenerFiltros()
    {
        $search = trim($_POST['search'] ?? '');
        $idRaffle = $_POST['id_raffle'] ?? '';
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';
        $periodo = $_POST['periodo'] ?? '';
        $metodoPago = $_POST['payment_method'] ?? '';
        $idAdmin = $_POST['id_admin'] ?? '';
        $sourceSale = $_POST['source_sale'] ?? '';
        [$dateFrom, $dateTo] = self::calcularRangoFechas($fechaInicio, $fechaFin, $periodo);

        return compact('search', 'idRaffle', 'metodoPago', 'dateFrom', 'dateTo', 'idAdmin', 'sourceSale');
    }

    public static function calcularRangoFechas($fechaInicio, $fechaFin, $periodo)
    {
        if ($fechaInicio && $fechaFin) {
            return [$fechaInicio, $fechaFin];
        }
        if (!$periodo) {
            return [null, null];
        }
        $hoy = date('Y-m-d');
        $rangos = [
            'today' => [$hoy, $hoy],
            'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'week' => [date('Y-m-d', strtotime('monday this week')), $hoy],
            'month' => [date('Y-m-01'), date('Y-m-t')],
            'year' => [date('Y-01-01'), date('Y-12-31')],
        ];
        return $rangos[$periodo] ?? [null, null];
    }

    public static function obtenerOCrearCliente($data)
    {
        if (!empty($data['id_customer'])) {
            return $data['id_customer'];
        }

        $phoneRaw = (string)($data['phone_customer'] ?? '');
        $name = trim((string)($data['name_customer'] ?? ''));
        $lastname = trim((string)($data['lastname_customer'] ?? ''));
        $city = trim((string)($data['city_customer'] ?? ''));

        if ($phoneRaw === '' || $name === '' || $lastname === '' || $city === '') {
            self::logVenta('VALIDACION_CLIENTE_FALLIDA', ['motivo' => 'Campos requeridos incompletos']);
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phoneRaw);
        if (strpos($phone, '57') === 0 && strlen($phone) === 12) {
            $phone = substr($phone, 2);
        }
        if (!preg_match('/^\d{10}$/', $phone)) {
            self::logVenta('VALIDACION_CLIENTE_FALLIDA', ['motivo' => 'Celular inválido']);
            return null;
        }

        $found = Db::fetchOne(
            'SELECT id_customer FROM customers WHERE phone_customer = :p LIMIT 1',
            [':p' => $phone]
        );
        if ($found) {
            return (int)$found->id_customer;
        }

        return Db::insert('customers', [
            'name_customer' => ucwords(strtolower($name)),
            'lastname_customer' => ucwords(strtolower($lastname)),
            'phone_customer' => $phone,
            'email_customer' => trim((string)($data['email_customer'] ?? '')),
            'department_customer' => trim((string)($data['department_customer'] ?? '')),
            'city_customer' => $city,
            'status_customer' => 1,
        ]);
    }

    public static function obtenerDetalleVenta($idVenta)
    {
        $venta = self::consultarVenta($idVenta);
        if (!$venta) {
            return ['success' => false, 'message' => 'Venta no encontrada'];
        }
        $tickets = self::consultarTicketsVenta($idVenta);
        $htmlRecibo = self::generarRecibo($venta, $tickets);
        if (!$htmlRecibo) {
            return ['success' => false, 'message' => 'Error al generar recibo'];
        }
        return ['success' => true, 'html_recibo' => $htmlRecibo];
    }

    /**
     * @param array<int> $ticketIds
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function buildMetaPurchasePayload(
        int $idVenta,
        int $idCliente,
        array $data,
        array $ticketIds,
        float $totalInsert
    ): array {
        $payload = [
            'id_sale' => $idVenta,
            'code_sale' => $data['code_sale'] ?? '',
            'id_customer_sale' => $idCliente,
            'quantity_sale' => count($ticketIds),
            'total_sale' => $totalInsert,
            'payment_method_sale' => $data['payment_method_sale'] ?? '',
            'name_customer' => $data['name_customer'] ?? '',
            'lastname_customer' => $data['lastname_customer'] ?? '',
            'phone_customer' => $data['phone_customer'] ?? '',
            'email_customer' => $data['email_customer'] ?? '',
            'city_customer' => $data['city_customer'] ?? '',
            'department_customer' => $data['department_customer'] ?? '',
        ];

        if ($idCliente > 0) {
            $customer = Db::fetchOne(
                'SELECT name_customer, lastname_customer, phone_customer, email_customer, city_customer, department_customer
                 FROM customers WHERE id_customer = :id LIMIT 1',
                [':id' => $idCliente]
            );
            if ($customer) {
                foreach ([
                    'name_customer',
                    'lastname_customer',
                    'phone_customer',
                    'email_customer',
                    'city_customer',
                    'department_customer',
                ] as $field) {
                    $dbValue = trim((string)($customer->{$field} ?? ''));
                    if ($dbValue !== '') {
                        $payload[$field] = $dbValue;
                    }
                }
            }
        }

        return $payload;
    }

    public static function consultarVenta($idVenta)
    {
        $sql = self::baseSalesSelectSql() . ' WHERE s.id_sale = :id LIMIT 1';
        return Db::fetchOne($sql, [':id' => $idVenta]);
    }

    public static function consultarTicketsVenta($idVenta)
    {
        $rows = Db::fetchAll(
            'SELECT id_ticket, number_ticket, COALESCE(is_premium_ticket, 0) AS is_premium_ticket
             FROM tickets WHERE id_sale_ticket = :id',
            [':id' => $idVenta]
        );
        if ($rows !== []) {
            shuffle($rows);
        }

        return array_map(static function ($item) {
            return (object)[
                'id_ticket' => (int)($item->id_ticket ?? 0),
                'number_ticket' => $item->number_ticket,
                'is_premium_ticket' => (int)($item->is_premium_ticket ?? 0),
            ];
        }, $rows);
    }

    public static function generarRecibo($venta, $tickets)
    {
        $rutaBase = dirname(__DIR__) . '/includes/';
        $rutaPlantilla = $rutaBase . 'templeate-ticket.php';
        if (!file_exists($rutaPlantilla)) {
            $rutaPlantilla = $rutaBase . 'template-ticket.php';
        }
        if (!file_exists($rutaPlantilla)) {
            return null;
        }

        $fecha = new DateTime($venta->date_created_sale);
        $fecha->setTimezone(new DateTimeZone('America/Bogota'));

        $grupoUrl = '#';
        $nombreRifa = defined('SITE_NAME') ? SITE_NAME : 'El Día de Tu Suerte';
        $sorteoLabel = 'Juega 10 de Julio con la de Medellín';

        $settingsRows = Db::fetchAll('SELECT key_setting, value_setting FROM settings');
        foreach ($settingsRows as $item) {
            if ($item->key_setting === 'whatsapp_group_url') {
                $grupoUrl = $item->value_setting;
            }
            if ($item->key_setting === 'nombre_rifa') {
                $nombreRifa = $item->value_setting;
            }
        }

        $tituloEvento = trim(preg_replace('/\*+/', '', (string)($venta->title_raffle ?? 'COMBO EXTREMO')));
        if ($tituloEvento === '') {
            $tituloEvento = 'COMBO EXTREMO';
        }
        $fechaJuego = preg_replace('/^Juega\s+/iu', 'JUEGA ESTE ', $sorteoLabel);
        $fechaJuego = function_exists('mb_strtoupper') ? mb_strtoupper($fechaJuego, 'UTF-8') : strtoupper($fechaJuego);
        $textoEvento = '🛵⚡️ ' . strtoupper($tituloEvento)
            . ', YAMAHA MT 15 0KM PARA EL NRO PRINCIPAL Y PARA EL NRO INVERTIDO YAMAHA XTZ 150. '
            . '💶 ' . $fechaJuego;
        $htmlTickets = '';
        foreach ($tickets as $t) {
            $numero = is_string($t) ? $t : ($t->number_ticket ?? '');
            $idTicket = is_string($t) ? 0 : (int)($t->id_ticket ?? 0);
            if ($numero === '') {
                continue;
            }
            $esPremium = !is_string($t) && (int)($t->is_premium_ticket ?? 0) === 1;
            $variant = $esPremium ? 'premium' : 'recibo';

            $htmlTickets .= self::htmlChipNumeroRecibo((string)$numero, $idTicket, $variant);
        }

        $reemplazos = [
            '{LogoUrl}' => edts_logo_url(),
            '{Nombre Cliente}' => trim($venta->name_customer . ' ' . $venta->lastname_customer),
            '{ID}' => $venta->id_sale,
            '{Fecha}' => $fecha->format('d/m/Y h:i A'),
            '{Cantidad}' => $venta->quantity_sale,
            '{Codigo}' => $venta->code_sale,
            '{NumerosHTML}' => $htmlTickets,
            '{Total}' => '$' . number_format((float)$venta->total_sale, 0, ',', '.'),
            '{GrupoUrl}' => $grupoUrl,
            '{NombreRifa}' => $nombreRifa,
            '{Evento}' => htmlspecialchars($textoEvento, ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($reemplazos), array_values($reemplazos), file_get_contents($rutaPlantilla));
    }

    public static function obtenerTicketsDisponibles($idRaffle)
    {
        $idRaffle = (int) $idRaffle;
        $data = Db::fetchAll(
            'SELECT id_ticket, number_ticket FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 0',
            [':r' => $idRaffle]
        );
        if ($data !== []) {
            shuffle($data);
        }
        $priceRow = Db::fetchOne(
            'SELECT price_raffle FROM raffles WHERE id_raffle = :id LIMIT 1',
            [':id' => $idRaffle]
        );
        $priceRaffle = $priceRow ? (float) ($priceRow->price_raffle ?? 0) : 0.0;

        return ['success' => true, 'data' => $data, 'price_raffle' => $priceRaffle];
    }

    public static function obtenerNumerosVendidos()
    {
        $search = trim($_POST['search'] ?? '');
        $idRaffle = $_POST['id_raffle'] ?? '';
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';
        $periodo = $_POST['periodo'] ?? '';
        [$dateFrom, $dateTo] = self::calcularRangoFechas($fechaInicio, $fechaFin, $periodo);

        $where = ['t.status_ticket = 1'];
        $params = [];

        if ($idRaffle !== '' && $idRaffle !== null) {
            $where[] = 't.id_raffle_ticket = :rif';
            $params[':rif'] = $idRaffle;
        }
        if ($dateFrom && $dateTo) {
            $where[] = 'DATE(s.date_created_sale) BETWEEN :df AND :dt';
            $params[':df'] = $dateFrom;
            $params[':dt'] = $dateTo;
        }

        $searchOr = [];
        if ($search !== '') {
            if (is_numeric($search)) {
                $searchOr[] = 't.number_ticket = :sn';
                $params[':sn'] = $search;
            } elseif (str_contains($search, '@')) {
                $searchOr[] = 'c.email_customer LIKE :em';
                $params[':em'] = '%' . $search . '%';
            } else {
                $searchOr[] = 'c.name_customer LIKE :n';
                $searchOr[] = 'c.lastname_customer LIKE :ln';
                $searchOr[] = 's.code_sale LIKE :cs';
                $params[':n'] = '%' . $search . '%';
                $params[':ln'] = '%' . $search . '%';
                $params[':cs'] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $searchOr) . ')';
        }

        $sql = 'SELECT t.id_ticket, t.number_ticket, t.id_sale_ticket, s.date_created_sale,
            c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer, c.city_customer,
            r.title_raffle, s.code_sale
            FROM tickets t
            INNER JOIN sales s ON s.id_sale = t.id_sale_ticket
            INNER JOIN customers c ON c.id_customer = s.id_customer_sale
            INNER JOIN raffles r ON r.id_raffle = s.id_raffle_sale
            WHERE ' . implode(' AND ', $where) . ' ORDER BY t.number_ticket ASC';

        $rows = Db::fetchAll($sql, $params);
        return ['success' => true, 'data' => $rows];
    }

    public static function obtenerVentaPorCodigo(string $codeSale)
    {
        $venta = Db::fetchOne(
            self::baseSalesSelectSql() . ' WHERE s.code_sale = :c LIMIT 1',
            [':c' => $codeSale]
        );
        if (!$venta) {
            return ['success' => false, 'message' => 'Venta no encontrada'];
        }
        $tickets = Db::fetchAll(
            'SELECT number_ticket FROM tickets WHERE id_sale_ticket = :id',
            [':id' => $venta->id_sale]
        );
        return ['success' => true, 'venta' => $venta, 'tickets' => $tickets];
    }

    public static function buscarTicketsPorCelular($phoneCustomer)
    {
        $phone = preg_replace('/\D/', '', (string)$phoneCustomer);
        if (!preg_match('/^\d{10}$/', $phone)) {
            return ['success' => false, 'message' => 'El celular debe contener exactamente 10 dígitos'];
        }

        $ventas = Db::fetchAll(
            self::baseSalesSelectSql() . ' WHERE c.phone_customer = :p ORDER BY s.id_sale ASC',
            [':p' => $phone]
        );
        if ($ventas === []) {
            return ['success' => false, 'message' => 'No encontrado'];
        }

        $html = '';
        foreach ($ventas as $venta) {
            $tickets = self::consultarTicketsVenta((int)$venta->id_sale);
            $html .= self::generarRecibo($venta, $tickets);
        }
        return ['success' => true, 'html' => $html];
    }

    public static function listarRifas()
    {
        $rows = Db::fetchAll('SELECT id_raffle, title_raffle FROM raffles ORDER BY id_raffle DESC');
        return ['success' => true, 'data' => $rows];
    }

    public static function obtenerOrigenesUnicos()
    {
        $rows = Db::fetchAll('SELECT DISTINCT source_sale FROM sales WHERE source_sale IS NOT NULL AND source_sale != \'\'');
        $origenes = array_values(array_unique(array_filter(array_map(
            static fn ($r) => trim((string)($r->source_sale ?? '')),
            $rows
        ))));
        sort($origenes);
        return ['success' => true, 'data' => $origenes];
    }

    public static function obtenerAdmins()
    {
        $rows = Db::fetchAll('SELECT id_admin, email_admin FROM admins WHERE status_admin = 1 OR status_admin IS NULL ORDER BY id_admin');
        return ['success' => true, 'data' => $rows];
    }

    private static function logVenta($tag, $data): void
    {
        $file = appLogPath('ventas_debug.log');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            return;
        }
        // El directorio puede parecer escribible pero el .log existente (p. ej. creado por root) no: eso rompía el JSON de transferencias.ajax.
        clearstatcache(true, $file);
        if (file_exists($file)) {
            if (!is_writable($file)) {
                return;
            }
        } elseif (!is_writable($dir)) {
            return;
        }
        self::rotarLogSiExcede($file);
        $line = '[' . date('Y-m-d H:i:s') . "] $tag | " . json_encode(self::sanitizeLogData($data), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Cantidad mínima configurada en la rifa (web y checkout).
     */
    public static function cantidadMinimaRifa(int $idRaffle): int
    {
        if ($idRaffle <= 0) {
            return 1;
        }

        $row = Db::fetchOne(
            'SELECT min_quantity_raffle FROM raffles WHERE id_raffle = :id LIMIT 1',
            [':id' => $idRaffle]
        );
        if (!$row) {
            return 1;
        }

        return max(1, (int)($row->min_quantity_raffle ?? 1));
    }

    /**
     * @return array{success:false,message:string}|null
     */
    public static function validarCantidadMinimaRifa(int $idRaffle, int $quantity): ?array
    {
        $min = self::cantidadMinimaRifa($idRaffle);
        if ($quantity < $min) {
            return [
                'success' => false,
                'message' => 'La compra mínima es de ' . $min . ' nro' . ($min > 1 ? 's' : ''),
            ];
        }

        return null;
    }

    /**
     * Total = cantidad × price_raffle (misma regla que al validar ventas). Para transferencias y otros flujos.
     *
     * @return array{success:bool,total?:float,unit?:float,message?:string}
     */
    public static function calcularTotalPorPrecioRifa(int $idRaffle, int $quantity): array
    {
        return self::resolverTotalDesdePrecioRifa($idRaffle, $quantity);
    }

    /**
     * Comparación de montos en COP (pesos enteros). Tolera ±1 por redondeo float/JS vs PHP.
     */
    public static function montosEquivalentesCOP(float $a, float $b): bool
    {
        return abs((int) round($a) - (int) round($b)) <= 1;
    }

    /**
     * Total esperado = cantidad × price_raffle (fuente de verdad en BD). Una consulta indexada por PK.
     *
     * @return array{success:bool,total?:float,unit?:float,message?:string}
     */
    private static function resolverTotalDesdePrecioRifa(int $idRaffle, int $quantity): array
    {
        if ($idRaffle <= 0 || $quantity <= 0) {
            return ['success' => false, 'message' => 'Dinámica o cantidad inválida'];
        }
        $row = Db::fetchOne(
            'SELECT price_raffle FROM raffles WHERE id_raffle = :id LIMIT 1',
            [':id' => $idRaffle]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Dinámica no encontrada'];
        }
        $fallbackUnit = (float)($row->price_raffle ?? 0);

        $pricing = \App\Application\Pricing\RaffleQuantityPricing::fromConfig(
            class_exists(\AppContainer::class) ? \AppContainer::get()->config() : null
        );
        $breakdown = $pricing->calculate($quantity, $fallbackUnit);

        if ($breakdown['total'] <= 0) {
            return ['success' => false, 'message' => 'Precio no configurado'];
        }

        return [
            'success' => true,
            'total' => (float)$breakdown['total'],
            'unit' => $fallbackUnit > 0 ? $fallbackUnit : (float)$breakdown['tier1_unit'],
            'pricing' => $breakdown,
        ];
    }

    private static function validarDatosVenta(array $data): array
    {
        $required = ['quantity_sale', 'id_raffle', 'code_sale', 'total_sale', 'payment_method_sale'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                return ['success' => false, 'message' => "Campo requerido faltante: {$field}"];
            }
        }
        if ((int)$data['quantity_sale'] <= 0 || (int)$data['id_raffle'] <= 0) {
            return ['success' => false, 'message' => 'Datos inválidos para crear venta'];
        }
        if ((float)$data['total_sale'] < 0) {
            return ['success' => false, 'message' => 'Total inválido para crear venta'];
        }
        return ['success' => true];
    }

    private static function withVentaLock(callable $callback)
    {
        $lockPath = appDataPath('locks/ventas_controller.lock');
        $lockDir = dirname($lockPath);
        ensureWritableDirectory($lockDir, 0777);
        @chmod($lockDir, 0777);

        if (is_file($lockPath) && !is_writable($lockPath)) {
            @chmod($lockPath, 0666);
        }

        $fp = @fopen($lockPath, 'c+');
        if (!$fp) {
            $err = error_get_last();
            error_log('[ventas] lock fopen failed: ' . $lockPath . ' — ' . ($err['message'] ?? 'unknown'));

            return ['success' => false, 'message' => 'No fue posible abrir lock de concurrencia'];
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return ['success' => false, 'message' => 'No fue posible adquirir lock de concurrencia'];
            }
            return $callback();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function htmlChipNumeroRecibo(string $numberTicket, int $idTicket = 0, string $variant = 'recibo'): string
    {
        if (!function_exists('cr_numero_chip')) {
            require_once dirname(__DIR__) . '/includes/components/cr-numero-chip.php';
        }

        return cr_numero_chip($numberTicket, $variant, '', $idTicket);
    }

    /** @deprecated Use htmlChipNumeroRecibo */
    private static function htmlChipNumeroReciboBendecidos(string $numberTicket, int $idTicket = 0): string
    {
        return self::htmlChipNumeroRecibo($numberTicket, $idTicket, 'selected');
    }

    /** @deprecated Use htmlChipNumeroRecibo */
    private static function htmlChipNumeroReciboDefault(string $numberTicket, int $idTicket = 0): string
    {
        return self::htmlChipNumeroRecibo($numberTicket, $idTicket, 'selected');
    }

    private static function sanitizeLogData($data)
    {
        if (is_object($data)) {
            $data = (array)$data;
        }
        if (!is_array($data)) {
            return $data;
        }
        $sensitive = ['email_customer', 'phone_customer', 'name_customer', 'lastname_customer'];
        foreach ($sensitive as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '***';
            }
        }
        return $data;
    }

    private static function rotarLogSiExcede(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }
        $size = @filesize($file);
        if ($size === false || $size < self::MAX_LOG_BYTES) {
            return;
        }
        @rename($file, $file . '.' . date('Ymd_His'));
    }
}
