<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Raffle\RaffleService;
use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use RifasController;
use Throwable;

final class RaffleHttpController
{
    private const ALLOWED_ACTIONS = [
        'obtener',
        'obtener_activas',
        'crear',
        'actualizar',
        'eliminar',
        'listar',
        'pausar',
        'reanudar',
        'finalizar',
        'ocultar',
        'bloquear_ventas',
    ];

    /** Legacy actions handled by RifasController until UI migration completes. */
    private const LEGACY_ACTIONS = ['obtener', 'obtener_activas', 'crear', 'actualizar'];

    private const PERMISSION_BY_ACTION = [
        'obtener' => ['rifas.view'],
        'obtener_activas' => ['rifas.view'],
        'listar' => ['rifas.view'],
        'crear' => ['rifas.create'],
        'actualizar' => ['rifas.edit'],
        'pausar' => ['rifas.pause'],
        'reanudar' => ['rifas.pause'],
        'finalizar' => ['rifas.edit'],
        'ocultar' => ['rifas.edit'],
        'bloquear_ventas' => ['rifas.pause'],
        'eliminar' => ['rifas.delete'],
    ];

    public function __construct(
        private readonly RaffleService $service,
        private readonly RaffleRepositoryInterface $repository,
        private readonly PermissionMiddleware $permissions,
        private readonly CsrfMiddleware $csrf,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);
            $this->csrf->handle($request, ['crear', 'actualizar', 'pausar', 'reanudar', 'finalizar', 'ocultar', 'bloquear_ventas', 'eliminar']);

            if (in_array($action, self::LEGACY_ACTIONS, true)) {
                $result = $this->delegateLegacy($action, $request->all());
                $this->audit->log('raffle.' . $action, ['success' => (bool)($result['success'] ?? false), 'legacy' => true]);
                Response::json($result);
            }

            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $result = match ($action) {
                'listar' => ['success' => true, 'data' => $this->repository->findAllActive()],
                'pausar' => $this->actionVoid($action, (int)$request->input('id_raffle', 0), $adminId),
                'reanudar' => $this->actionVoid($action, (int)$request->input('id_raffle', 0), $adminId),
                'finalizar' => $this->actionVoid($action, (int)$request->input('id_raffle', 0), $adminId),
                'ocultar' => $this->actionVoid($action, (int)$request->input('id_raffle', 0), $adminId),
                'bloquear_ventas' => $this->bloquearVentas($request, $adminId),
                'eliminar' => $this->eliminar((int)$request->input('id_raffle', 0), $adminId),
                default => ['success' => false, 'message' => 'Acción no implementada'],
            };

            $this->audit->log('raffle.' . $action, ['success' => (bool)($result['success'] ?? true)]);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function delegateLegacy(string $action, array $payload): array
    {
        if (!class_exists(RifasController::class, false)) {
            require_once dirname(__DIR__, 4) . '/controllers/rifas.controller.php';
        }

        $_POST = array_merge($_POST, $payload);

        return match ($action) {
            'obtener' => RifasController::obtenerRifas(),
            'obtener_activas' => RifasController::obtenerRifasActivas(),
            'crear' => RifasController::crearRifa($payload),
            'actualizar' => RifasController::actualizarRifa($payload),
            default => ['success' => false, 'message' => 'Legacy action not found'],
        };
    }

    private function actionVoid(string $action, int $id, int $adminId): array
    {
        match ($action) {
            'pausar' => $this->service->pause($id, $adminId),
            'reanudar' => $this->service->resume($id, $adminId),
            'finalizar' => $this->service->finish($id, $adminId),
            'ocultar' => $this->service->hide($id, $adminId),
            default => null,
        };

        return ['success' => true];
    }

    private function bloquearVentas(Request $request, int $adminId): array
    {
        $id = (int)$request->input('id_raffle', 0);
        $blocked = filter_var($request->input('blocked', true), FILTER_VALIDATE_BOOLEAN);
        $this->service->blockSales($id, $adminId, $blocked);

        return ['success' => true, 'blocked' => $blocked];
    }

    private function eliminar(int $id, int $adminId): array
    {
        $this->service->delete($id, $adminId);

        return ['success' => true, 'message' => 'Rifa eliminada'];
    }
}
