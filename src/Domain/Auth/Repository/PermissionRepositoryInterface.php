<?php
declare(strict_types=1);

namespace App\Domain\Auth\Repository;

interface PermissionRepositoryInterface
{
    public function adminHasPermission(int $adminId, string $module, string $action): bool;

    /**
     * @return list<string>
     */
    public function getAdminPermissions(int $adminId): array;
}
