<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use TransfersController;
use Throwable;

final class TransferenciasHttpController
{
    private const ALLOWED_ACTIONS = ['obtener', 'aprobar', 'rechazar'];

    private const PERMISSION_BY_ACTION = [
        'obtener' => ['transferencias.view'],
        'aprobar' => ['transferencias.approve'],
        'rechazar' => ['transferencias.approve'],
    ];

    private const CSRF_ACTIONS = ['aprobar', 'rechazar'];

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

            if (!class_exists(TransfersController::class, false)) {
                require_once dirname(__DIR__, 4) . '/controllers/transfersController.php';
            }

            $payload = $request->all();
            $_POST = array_merge($_POST, $payload);
            $result = match ($action) {
                'obtener' => TransfersController::obtenerTransferencias($payload),
                'aprobar' => TransfersController::aprobarTransferencia($payload),
                'rechazar' => TransfersController::rechazarTransferencia($payload),
                default => ['success' => false, 'message' => 'Acción no implementada'],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
