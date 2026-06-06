<?php
declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Shared\Http\Response;

final class RbacMiddleware
{
    /**
     * @param array<string, list<string>> $permissionsByAction
     */
    public function authorize(string $action, array $permissionsByAction): void
    {
        if (!isset($permissionsByAction[$action])) {
            return;
        }

        $rawRole = (string)($_SESSION['user_role'] ?? '');
        $roles = $this->parseRoles($rawRole);
        $allowed = $permissionsByAction[$action];

        $isAuthorized = false;
        foreach ($roles as $role) {
            if (in_array($role, $allowed, true)) {
                $isAuthorized = true;
                break;
            }
        }

        if (!$isAuthorized) {
            Response::json(['success' => false, 'message' => 'No autorizado para esta accion'], 403);
        }
    }

    /**
     * @return list<string>
     */
    private function parseRoles(string $rawRole): array
    {
        $rawRole = strtolower(trim($rawRole));
        if ($rawRole === '') {
            return [];
        }

        $parts = preg_split('/\s*[,;|]+\s*/', $rawRole) ?: [];
        if ($parts === []) {
            return [$rawRole];
        }

        $parts = array_map(static fn (string $r) => trim($r), $parts);
        $parts = array_values(array_filter($parts, static fn (string $r) => $r !== ''));

        return $parts === [] ? [$rawRole] : $parts;
    }
}
