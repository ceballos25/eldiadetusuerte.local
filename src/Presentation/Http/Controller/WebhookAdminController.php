<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Webhook\OpenPayWebhookProcessor;
use App\Infrastructure\Repository\PdoWebhookRepository;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class WebhookAdminController
{
    private const ALLOWED_ACTIONS = [
        'listar_pendientes',
        'reprocesar',
        'openpay_listar',
        'openpay_registrar',
        'openpay_eliminar',
    ];

    private const PERMISSION_BY_ACTION = [
        'listar_pendientes' => ['configuracion.manage'],
        'reprocesar' => ['configuracion.manage'],
        'openpay_listar' => ['configuracion.manage'],
        'openpay_registrar' => ['configuracion.manage'],
        'openpay_eliminar' => ['configuracion.manage'],
    ];

    public function __construct(
        private readonly OpenPayWebhookProcessor $processor,
        private readonly PdoWebhookRepository $webhooks,
        private readonly \App\Application\Webhook\OpenPayWebhookRegistrationService $openPayRegistration,
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
            $this->csrf->handle($request, ['reprocesar', 'openpay_registrar', 'openpay_eliminar']);

            $result = match ($action) {
                'listar_pendientes' => [
                    'success' => true,
                    'data' => $this->webhooks->findPendingForReprocess(100),
                ],
                'reprocesar' => $this->processor->reprocess(trim((string)$request->input('uuid', ''))),
                'openpay_listar' => [
                    'success' => true,
                    'data' => $this->openPayRegistration->list(),
                    'webhook_url' => $this->openPayRegistration->defaultWebhookUrl(),
                    'event_types' => \App\Application\Webhook\OpenPayWebhookEventTypes::forRegistration(),
                ],
                'openpay_registrar' => [
                    'success' => true,
                    'data' => $this->openPayRegistration->create(),
                    'message' => 'Webhook registrado en OpenPay. Debe quedar en estado verified.',
                ],
                'openpay_eliminar' => $this->deleteOpenPayWebhook($request),
                default => ['success' => false],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function deleteOpenPayWebhook(Request $request): array
    {
        $id = trim((string)$request->input('webhook_id', ''));
        if ($id === '') {
            return ['success' => false, 'message' => 'webhook_id requerido'];
        }
        $this->openPayRegistration->delete($id);

        return ['success' => true, 'message' => 'Webhook eliminado'];
    }
}
