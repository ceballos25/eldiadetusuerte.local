<?php
declare(strict_types=1);

namespace App\Domain\Audit\Repository;

interface AuditRepositoryInterface
{
    public function log(
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $oldData,
        ?array $newData,
        ?int $adminId = null,
        ?string $ip = null
    ): void;
}
