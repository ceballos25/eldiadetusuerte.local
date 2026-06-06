<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Settings\SettingsService;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\RbacMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class SettingsHttpController
{
    private const ALLOWED_ACTIONS = ['obtener', 'actualizar', 'crear', 'eliminar'];

    private const ROLE_BY_ACTION = [
        // obtener: sin RBAC — la landing pública lee settings (redes, web_id_raffle, barra…).
        'actualizar' => ['admin', 'administrador', 'superadmin'],
        'crear' => ['admin', 'administrador', 'superadmin'],
        'eliminar' => ['admin', 'administrador', 'superadmin'],
    ];

    private const CSRF_ACTIONS = ['actualizar', 'crear', 'eliminar'];

    public function __construct(
        private readonly SettingsService $service,
        private readonly RbacMiddleware $rbac,
        private readonly CsrfMiddleware $csrf,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Accion invalida'], 422);
            }

            $this->csrf->handle($request, self::CSRF_ACTIONS);
            $this->rbac->authorize($action, self::ROLE_BY_ACTION);
            $result = $this->service->execute($action, $this->sanitizePayload($request->all()));
            $this->audit->log('settings.' . $action, ['success' => (bool)($result['success'] ?? false)]);
            Response::json($result);
        } catch (Throwable $exception) {
            $this->audit->log('settings.error', ['error' => $exception->getMessage()]);
            Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $skip = ['action', 'csrf_token', '_csrf'];
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $key = (string)$key;
            if (in_array($key, $skip, true)) {
                continue;
            }
            $clean[$key] = trim((string)$value);
        }

        return $clean;
    }
}
