<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Web\WebCheckoutService;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class WebCheckoutController
{
    private const ALLOWED_ACTIONS = [
        'crear_respaldo', 'ir_openpay', 'crear_transferencia_completa',
        'bootstrap_landing',
        'rifas_activas', 'config_publica',
        'inventario', 'progreso', 'buscar_cliente_checkout', 'buscar_numeros',
    ];

    private const PUBLIC_READ_ACTIONS = [
        'bootstrap_landing',
        'rifas_activas', 'config_publica',
        'inventario', 'progreso', 'buscar_cliente_checkout', 'buscar_numeros',
    ];

    private const CSRF_WRITE_ACTIONS = ['crear_respaldo', 'ir_openpay', 'crear_transferencia_completa'];

    public function __construct(
        private readonly WebCheckoutService $service,
        private readonly AuditLogger $audit,
        private readonly CsrfMiddleware $csrf,
        private readonly \App\Presentation\Http\Middleware\RateLimitMiddleware $rateLimit = new \App\Presentation\Http\Middleware\RateLimitMiddleware()
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Accion no valida'], 422);
            }

            if (in_array($action, self::PUBLIC_READ_ACTIONS, true)) {
                $this->rateLimit->check('web_' . $action, 120, 60);
            } else {
                $this->rateLimit->check('web_' . $action, 30, 60);
            }

            if (!$this->isReadAction($action) && !$this->service->arePurchasesAllowed()) {
                Response::json(['success' => false, 'message' => 'Las compras en línea están temporalmente deshabilitadas.'], 403);
            }
            $this->csrf->handle($request, self::CSRF_WRITE_ACTIONS);
            $payload = $this->sanitize($request->all());
            if ($action === 'crear_transferencia_completa') {
                $result = $this->service->execute($action, $payload, $request->files());
            } else {
                $result = $this->service->execute($action, $payload, []);
            }
            $this->audit->log('web_checkout.' . $action, ['success' => (bool)($result['success'] ?? false)]);
            Response::json($result);
        } catch (Throwable $exception) {
            $this->audit->log('web_checkout.error', ['error' => $exception->getMessage()]);
            Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function sanitize(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_array($value) && (str_starts_with((string)$key, 'ticket_ids') || $key === 'ticket_ids')) {
                $clean['ticket_ids'] = array_values(array_map('intval', $value));
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $clean[(string)$key] = trim((string)$value);
        }

        if (isset($_POST['ticket_ids']) && is_array($_POST['ticket_ids'])) {
            $clean['ticket_ids'] = array_values(array_map('intval', $_POST['ticket_ids']));
        }

        return $clean;
    }

    private function isReadAction(string $action): bool
    {
        return in_array($action, self::PUBLIC_READ_ACTIONS, true);
    }
}
