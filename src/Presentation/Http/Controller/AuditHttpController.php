<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Infrastructure\Repository\PdoAuditQueryRepository;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class AuditHttpController
{
    public function __construct(
        private readonly PdoAuditQueryRepository $audit,
        private readonly PermissionMiddleware $permissions
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', 'listar'));
            if ($action !== 'listar') {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $this->permissions->authorize($action, ['listar' => ['auditoria.view']]);

            $page = max(1, (int)$request->input('page', 1));
            $limit = min(200, max(1, (int)$request->input('limit', 50)));

            $result = $this->audit->search([
                'action' => trim((string)$request->input('search_action', '')),
                'entity_type' => trim((string)$request->input('entity_type', '')),
                'admin_id' => (int)$request->input('admin_id', 0) ?: null,
                'date_from' => trim((string)$request->input('date_from', '')),
                'date_to' => trim((string)$request->input('date_to', '')),
            ], $page, $limit);

            Response::json(['success' => true, 'data' => $result['data'], 'total' => $result['total']]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
