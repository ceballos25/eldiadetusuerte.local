<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use NumerosController;
use RifasController;
use Throwable;

final class NumerosHttpController
{
    private const ALLOWED_ACTIONS = [
        'obtener_inventario',
        'obtener_rifas',
        'cambiar_estado',
        'obtener_progreso',
        'marcar_flags',
    ];

    private const PERMISSION_BY_ACTION = [
        'obtener_inventario' => ['rifas.view'],
        'obtener_rifas' => ['rifas.view'],
        'obtener_progreso' => ['rifas.view'],
        'cambiar_estado' => ['rifas.edit'],
        'marcar_flags' => ['rifas.edit'],
    ];

    private const CSRF_ACTIONS = ['cambiar_estado', 'marcar_flags'];

    public function __construct(
        private readonly PermissionMiddleware $permissions,
        private readonly CsrfMiddleware $csrf
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
            $this->csrf->handle($request, self::CSRF_ACTIONS);

            if (!class_exists(NumerosController::class, false)) {
                require_once dirname(__DIR__, 4) . '/controllers/numeros.controller.php';
            }
            if ($action === 'obtener_rifas' && !class_exists(RifasController::class, false)) {
                require_once dirname(__DIR__, 4) . '/controllers/rifas.controller.php';
            }

            $_POST = array_merge($_POST, $request->all());

            $result = match ($action) {
                'obtener_inventario' => NumerosController::obtenerInventario(),
                'obtener_rifas' => RifasController::obtenerRifas(),
                'cambiar_estado' => NumerosController::cambiarEstado(),
                'obtener_progreso' => NumerosController::obtenerProgreso((int)$request->input('id_raffle', 0)),
                'marcar_flags' => NumerosController::marcarTicketFlags(),
                default => ['success' => false, 'message' => 'Acción no implementada'],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
