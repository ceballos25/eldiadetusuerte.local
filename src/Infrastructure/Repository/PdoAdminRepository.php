<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoAdminRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return list<array>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT a.id_admin, a.email_admin, a.rol_admin, a.status_admin, a.id_role,
                    a.date_created_admin, r.name_role, r.slug_role
             FROM admins a
             LEFT JOIN roles r ON r.id_role = a.id_role
             ORDER BY a.id_admin ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, r.slug_role FROM admins a LEFT JOIN roles r ON r.id_role = a.id_role WHERE a.id_admin = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(string $email, string $password, int $roleId, ?string $legacyRole = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admins (email_admin, password_admin, rol_admin, id_role, status_admin, date_created_admin)
             VALUES (:email, :pass, :rol, :role_id, 1, CURDATE())'
        );
        $stmt->execute([
            ':email' => $email,
            ':pass' => password_hash($password, PASSWORD_BCRYPT),
            ':rol' => $legacyRole ?? 'operador',
            ':role_id' => $roleId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['email_admin'])) {
            $fields[] = 'email_admin = :email';
            $params[':email'] = $data['email_admin'];
        }
        if (isset($data['password_admin']) && $data['password_admin'] !== '') {
            $fields[] = 'password_admin = :pass';
            $params[':pass'] = password_hash((string)$data['password_admin'], PASSWORD_BCRYPT);
        }
        if (isset($data['id_role'])) {
            $fields[] = 'id_role = :role_id';
            $params[':role_id'] = (int)$data['id_role'];
        }
        if (isset($data['rol_admin'])) {
            $fields[] = 'rol_admin = :rol';
            $params[':rol'] = $data['rol_admin'];
        }
        if (isset($data['status_admin'])) {
            $fields[] = 'status_admin = :status';
            $params[':status'] = (int)$data['status_admin'];
        }

        if ($fields === []) {
            return;
        }

        $sql = 'UPDATE admins SET ' . implode(', ', $fields) . ' WHERE id_admin = :id';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM admins WHERE email_admin = :email';
        $params = [':email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id_admin != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array>
     */
    public function findRoles(): array
    {
        $stmt = $this->pdo->query('SELECT id_role, name_role, slug_role FROM roles ORDER BY id_role');

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
