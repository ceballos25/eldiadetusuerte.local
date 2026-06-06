<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use ClientesController;
use Throwable;

final class ClientesHttpController
{
    private const ALLOWED_ACTIONS = ['obtener', 'crear', 'actualizar', 'eliminar'];

    private const PERMISSION_BY_ACTION = [
        'obtener' => ['clientes.view'],
        'crear' => ['clientes.edit'],
        'actualizar' => ['clientes.edit'],
        'eliminar' => ['clientes.edit'],
    ];

    private const CSRF_ACTIONS = ['crear', 'actualizar', 'eliminar'];

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

            if (!class_exists(ClientesController::class, false)) {
                require_once dirname(__DIR__, 4) . '/controllers/clientes.controller.php';
            }

            $payload = $request->all();
            $_POST = array_merge($_POST, $payload);
            $result = match ($action) {
                'obtener' => ClientesController::obtenerClientes(),
                'crear' => ClientesController::crearCliente($payload),
                'actualizar' => ClientesController::actualizarCliente($payload),
                'eliminar' => ClientesController::eliminarCliente($payload),
                default => ['success' => false, 'message' => 'Acción no implementada'],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
