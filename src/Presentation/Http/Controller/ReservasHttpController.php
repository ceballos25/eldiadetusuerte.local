<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class ReservasHttpController
{
    private const ALLOWED_ACTIONS = ['obtener'];

    private const PERMISSION_BY_ACTION = [
        'obtener' => ['transferencias.view'],
    ];

    public function __construct(
        private readonly \App\Application\Reservation\PendingReservationService $reservations,
        private readonly PermissionMiddleware $permissions
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

            $result = match ($action) {
                'obtener' => $this->reservations->listPending($request->all()),
                default => ['success' => false, 'message' => 'Acción no implementada'],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
