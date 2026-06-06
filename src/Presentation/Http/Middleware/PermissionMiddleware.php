<?php
declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\Repository\PdoPermissionRepository;
use App\Shared\Http\Response;

final class PermissionMiddleware
{
    public function __construct(
        private readonly PdoPermissionRepository $permissions
    ) {
    }

    /**
     * @param array<string, list<string>> $permissionsByAction  action => ['module.action', ...]
     */
    public function authorize(string $action, array $permissionsByAction): void
    {
        if (!isset($permissionsByAction[$action])) {
            return;
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) {
            Response::json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $required = $permissionsByAction[$action];
        foreach ($required as $perm) {
            [$module, $permAction] = array_pad(explode('.', $perm, 2), 2, '');
            if ($this->permissions->adminHasPermission($adminId, $module, $permAction)) {
                return;
            }
        }

        if ($this->fallbackLegacyRole($required)) {
            return;
        }

        Response::json(['success' => false, 'message' => 'No autorizado para esta acción'], 403);
    }

    /**
     * Fallback while migrating to DB permissions — maps legacy session roles.
     *
     * @param list<string> $required
     */
    private function fallbackLegacyRole(array $required): bool
    {
        $rawRole = strtolower((string)($_SESSION['user_role'] ?? ''));
        if ($rawRole === '') {
            return false;
        }

        $isSuper = str_contains($rawRole, 'superadmin');
        $isAdmin = $isSuper || str_contains($rawRole, 'admin');
        $isOperador = str_contains($rawRole, 'vendedor') || str_contains($rawRole, 'operador');

        foreach ($required as $perm) {
            if (str_ends_with($perm, '.view') && ($isAdmin || $isOperador)) {
                return true;
            }
            if (str_ends_with($perm, '.create') && ($isAdmin || $isOperador)) {
                return true;
            }
            if (str_ends_with($perm, '.edit') && $isAdmin) {
                return true;
            }
            if (str_ends_with($perm, '.delete') && $isAdmin) {
                return true;
            }
            if (str_ends_with($perm, '.pause') && $isAdmin) {
                return true;
            }
            if (str_ends_with($perm, '.manage') && $isSuper) {
                return true;
            }
            if (str_ends_with($perm, '.export') && $isAdmin) {
                return true;
            }
        }

        return $isSuper;
    }
}
