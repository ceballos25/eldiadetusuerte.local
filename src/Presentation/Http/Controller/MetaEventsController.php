<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Marketing\MetaEventsService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class MetaEventsController
{
    public function __construct(
        private readonly MetaEventsService $service,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, MetaEventsService::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $result = $this->service->execute($action, $request->all());
            $this->audit->log('meta_events.' . $action, [
                'success' => (bool)($result['success'] ?? false),
                'event_name' => (string)($result['event_name'] ?? $request->input('event_name', '')),
            ]);
            Response::json($result);
        } catch (Throwable $exception) {
            $this->audit->log('meta_events.error', ['error' => $exception->getMessage()]);
            Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
