<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Auth\Repository\PermissionRepositoryInterface;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoPermissionRepository implements PermissionRepositoryInterface
{
    private PDO $pdo;

    /** @var array<int, list<string>> */
    private array $cache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function adminHasPermission(int $adminId, string $module, string $action): bool
    {
        $permissions = $this->getAdminPermissions($adminId);
        $key = strtolower($module . '.' . $action);

        return in_array($key, $permissions, true);
    }

    public function getAdminPermissions(int $adminId): array
    {
        if (isset($this->cache[$adminId])) {
            return $this->cache[$adminId];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.module_permission, p.action_permission
                 FROM admins a
                 JOIN role_permissions rp ON rp.id_role = a.id_role
                 JOIN permissions p ON p.id_permission = rp.id_permission
                 WHERE a.id_admin = :id'
            );
            $stmt->execute([':id' => $adminId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $this->cache[$adminId] = [];

            return [];
        }

        $permissions = [];
        foreach ($rows as $row) {
            $permissions[] = strtolower($row['module_permission'] . '.' . $row['action_permission']);
        }

        $this->cache[$adminId] = $permissions;

        return $permissions;
    }
}
