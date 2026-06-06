<?php
declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Repository\AuditRepositoryInterface;
use App\Shared\Audit\AuditLogger;

final class AuditService
{
    public function __construct(
        private readonly AuditRepositoryInterface $repository,
        private readonly AuditLogger $fileLogger
    ) {
    }

    public function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $adminId = null
    ): void {
        try {
            $this->repository->log($action, $entityType, $entityId, $oldData, $newData, $adminId);
        } catch (\Throwable $e) {
            if (function_exists('writeAppLog')) {
                writeAppLog('audit.log', 'DB audit failed: ' . $e->getMessage());
            }
        }

        $this->fileLogger->log($action, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old' => $oldData,
            'new' => $newData,
        ]);
    }
}
