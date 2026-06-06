<?php
declare(strict_types=1);

/**
 * Acceso directo a MySQL (sin API REST). Usar solo desde controladores de la app.
 */
final class Db
{
    private const TABLES = [
        'sales', 'sale_items', 'tickets', 'customers', 'raffles', 'settings', 'admins',
        'payment_backups', 'payment_backup_tickets', 'transfers', 'saved_reports',
        'roles', 'permissions', 'role_permissions', 'audit_logs', 'webhook_events',
        'site_images', 'migrations',
    ];

    public static function pdo(): PDO
    {
        return App\Infrastructure\Database\PdoFactory::get();
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_OBJ);
    }

    public static function fetchAllAssoc(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchOne(string $sql, array $params = []): ?object
    {
        $rows = self::fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    public static function fetchOneAssoc(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $table = self::assertTable($table);
        if ($data === []) {
            throw new InvalidArgumentException('insert vacío');
        }
        $cols = [];
        $ph = [];
        foreach (array_keys($data) as $k) {
            if (!preg_match('/^[a-z0-9_]+$/i', (string)$k)) {
                throw new InvalidArgumentException('Columna inválida');
            }
            $cols[] = '`' . $k . '`';
            $ph[] = ':' . $k;
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
        $pdo = self::pdo();
        $st = $pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();
        return (int)$pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $whereParams prefixed keys e.g. :id
     */
    public static function update(string $table, array $data, string $whereSql, array $whereParams = []): int
    {
        $table = self::assertTable($table);
        if ($data === []) {
            return 0;
        }
        $set = [];
        $bind = [];
        $i = 0;
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-z0-9_]+$/i', (string)$k)) {
                throw new InvalidArgumentException('Columna inválida');
            }
            $key = '_u' . $i++;
            $set[] = '`' . $k . '` = :' . $key;
            $bind[':' . $key] = $v;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE ' . $whereSql;
        $st = self::pdo()->prepare($sql);
        foreach (array_merge($bind, $whereParams) as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return $st->rowCount();
    }

    /** @param array<string, mixed> $whereParams */
    public static function delete(string $table, string $whereSql, array $whereParams = []): int
    {
        $table = self::assertTable($table);
        $sql = 'DELETE FROM `' . $table . '` WHERE ' . $whereSql;
        $st = self::pdo()->prepare($sql);
        $st->execute($whereParams);
        return $st->rowCount();
    }

    private static function assertTable(string $t): string
    {
        if (!in_array($t, self::TABLES, true)) {
            throw new InvalidArgumentException('Tabla no permitida: ' . $t);
        }
        return $t;
    }
}
