<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoAuditQueryRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return array{data: list<array>, total: int}
     */
    public function search(array $filters, int $page = 1, int $limit = 50): array
    {
        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action_audit LIKE :action';
            $params[':action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = :etype';
            $params[':etype'] = $filters['entity_type'];
        }
        if (!empty($filters['admin_id'])) {
            $where[] = 'a.admin_id = :admin';
            $params[':admin'] = (int)$filters['admin_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= :df';
            $params[':df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= :dt';
            $params[':dt'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT a.*, ad.email_admin
                FROM audit_logs a
                LEFT JOIN admins ad ON ad.id_admin = a.admin_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }
}
