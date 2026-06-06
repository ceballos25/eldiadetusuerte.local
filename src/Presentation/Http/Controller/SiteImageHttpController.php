<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Audit\AuditService;
use App\Infrastructure\Repository\PdoSiteImageRepository;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Exception\DomainException;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use App\Shared\Validation\ImageUrlValidator;
use Throwable;

final class SiteImageHttpController
{
    private const ACTIONS = ['listar', 'guardar', 'eliminar', 'publico'];

    private const PERMS = [
        'listar' => ['configuracion.view'],
        'guardar' => ['configuracion.manage'],
        'eliminar' => ['configuracion.manage'],
    ];

    public const KNOWN_KEYS = [
        'logo' => 'Logo principal',
        'favicon' => 'Favicon',
        'hero_banner' => 'Banner principal',
        'hero_mobile' => 'Banner móvil',
        'section_info_1' => 'Imagen sección informativa 1',
        'section_info_2' => 'Imagen sección informativa 2',
        'fallback_image' => 'Imagen de respaldo global',
    ];

    public function __construct(
        private readonly PdoSiteImageRepository $images,
        private readonly PermissionMiddleware $permissions,
        private readonly CsrfMiddleware $csrf,
        private readonly AuditService $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            if ($action === 'publico') {
                Response::json(['success' => true, 'data' => $this->images->asKeyMap()]);
            }

            $this->permissions->authorize($action, self::PERMS);
            $this->csrf->handle($request, ['guardar', 'eliminar']);

            $result = match ($action) {
                'listar' => ['success' => true, 'data' => $this->images->findAll(), 'keys' => self::KNOWN_KEYS],
                'guardar' => $this->guardar($request),
                'eliminar' => $this->eliminar($request),
                default => ['success' => false],
            };

            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function guardar(Request $request): array
    {
        $key = trim((string)$request->input('key_image', ''));
        $url = trim((string)$request->input('url_image', ''));
        $fallback = trim((string)$request->input('fallback_url', '')) ?: null;

        if ($key === '' || $url === '') {
            throw new DomainException('Clave y URL son obligatorias');
        }
        if (!ImageUrlValidator::isValidFormat($url)) {
            throw new DomainException('URL de imagen inválida');
        }
        if (!ImageUrlValidator::isReachable($url)) {
            throw new DomainException('La URL de imagen no responde o no está disponible');
        }
        if ($fallback !== null && !ImageUrlValidator::isValidFormat($fallback)) {
            throw new DomainException('URL de respaldo inválida');
        }

        $old = $this->images->findByKey($key);
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $this->images->upsert($key, $url, $fallback, $adminId ?: null);
        $this->audit->record('site_image.updated', 'site_image', null, $old, [
            'key' => $key, 'url' => $url, 'fallback' => $fallback,
        ], $adminId);

        return ['success' => true, 'message' => 'Imagen guardada'];
    }

    private function eliminar(Request $request): array
    {
        $key = trim((string)$request->input('key_image', ''));
        if ($key === '') {
            throw new DomainException('Clave obligatoria');
        }
        $old = $this->images->findByKey($key);
        $this->images->delete($key);
        $this->audit->record('site_image.deleted', 'site_image', null, $old, null);

        return ['success' => true];
    }
}
