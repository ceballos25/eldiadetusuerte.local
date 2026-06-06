<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Analytics\DashboardDbService;
use App\Presentation\Http\Middleware\RbacMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class DashboardHttpController
{
    private const ROLE_BY_ACTION = [
        'obtener_dashboard' => ['admin', 'administrador', 'superadmin', 'vendedor'],
        'obtener_rifas' => ['admin', 'administrador', 'superadmin', 'vendedor'],
    ];

    public function __construct(
        private readonly DashboardDbService $service,
        private readonly RbacMiddleware $rbac,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if ($action === 'obtener_dashboard') {
                $this->rbac->authorize($action, self::ROLE_BY_ACTION);
                $desde = trim((string)$request->input('fechaDesde', date('Y-m-01')));
                $hasta = trim((string)$request->input('fechaHasta', date('Y-m-d')));
                $rifaRaw = $request->input('id_raffle', '');
                $idRaffle = ($rifaRaw === '' || $rifaRaw === null) ? null : (int)$rifaRaw;
                $result = $this->service->obtenerDashboard($desde, $hasta, $idRaffle && $idRaffle > 0 ? $idRaffle : null);
                $this->audit->log('dashboard.obtener', ['success' => true]);
                Response::json($result);
            }
            if ($action === 'obtener_rifas') {
                $this->rbac->authorize($action, self::ROLE_BY_ACTION);
                $result = $this->service->listarRifas();
                Response::json($result);
            }
            Response::json(['success' => false, 'message' => 'Accion invalida'], 422);
        } catch (Throwable $e) {
            $this->audit->log('dashboard.error', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
