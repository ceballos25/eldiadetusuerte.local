<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Ticketing\TicketSalesService;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\RbacMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class TicketSalesController
{
    private const ALLOWED_ACTIONS = [
        'obtener',
        'obtener_rifas',
        'crear_venta',
        'crear_venta_mixta',
        'obtener_por_codigo',
        'obtener_disponibles',
        'detalle_venta',
        'obtener_por_celular',
        'numeros_vendidos',
        'obtener_admins',
        'anular',
        'anular_parcial',
        'obtener_origenes',
    ];

    private const CSRF_PROTECTED_ACTIONS = ['anular', 'anular_parcial'];

    /** Acciones invocables sin sesión de administrador (sitio público). */
    private const PUBLIC_ACTIONS = [
        'obtener_por_celular',
    ];

    private const ROLE_BY_ACTION = [
        'obtener' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'obtener_rifas' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'crear_venta' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'crear_venta_mixta' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'obtener_por_codigo' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'obtener_disponibles' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'detalle_venta' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'obtener_por_celular' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'numeros_vendidos' => ['admin', 'administrador', 'vendedor', 'superadmin'],
        'obtener_admins' => ['admin', 'administrador', 'superadmin'],
        'anular' => ['admin', 'administrador', 'superadmin'],
        'anular_parcial' => ['admin', 'administrador', 'superadmin'],
        'obtener_origenes' => ['admin', 'administrador', 'superadmin'],
    ];

    public function __construct(
        private readonly TicketSalesService $service,
        private readonly CsrfMiddleware $csrfMiddleware,
        private readonly RbacMiddleware $rbac,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Accion no valida'], 422);
            }

            if (!in_array($action, self::PUBLIC_ACTIONS, true)) {
                $this->rbac->authorize($action, self::ROLE_BY_ACTION);
            }
            $this->csrfMiddleware->handle($request, self::CSRF_PROTECTED_ACTIONS);
            $payload = $this->sanitizePayload($request->all());
            $result = $this->service->execute($action, $payload);
            $this->audit->log('ticket_sales.' . $action, ['success' => (bool)($result['success'] ?? false)]);
            Response::json($result);
        } catch (Throwable $exception) {
            $this->audit->log('ticket_sales.error', ['error' => $exception->getMessage()]);
            Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if ($key === 'ticket_ids' && is_array($value)) {
                $clean['ticket_ids'] = array_map('intval', $value);
                continue;
            }
            if (is_array($value)) {
                continue;
            }

            $clean[(string)$key] = trim((string)$value);
        }

        return $clean;
    }
}
