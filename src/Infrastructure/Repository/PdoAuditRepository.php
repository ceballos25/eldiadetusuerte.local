<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Audit\Repository\AuditRepositoryInterface;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoAuditRepository implements AuditRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function log(
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $oldData,
        ?array $newData,
        ?int $adminId = null,
        ?string $ip = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (admin_id, ip_address, action_audit, entity_type, entity_id, old_data, new_data)
             VALUES (:admin, :ip, :action, :etype, :eid, :old, :new)'
        );
        $stmt->execute([
            ':admin' => $adminId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null),
            ':ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ':action' => $action,
            ':etype' => $entityType,
            ':eid' => $entityId,
            ':old' => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            ':new' => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
