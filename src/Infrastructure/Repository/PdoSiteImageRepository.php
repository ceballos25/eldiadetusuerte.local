<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoSiteImageRepository
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
        $stmt = $this->pdo->query('SELECT * FROM site_images ORDER BY key_image ASC');

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function findByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM site_images WHERE key_image = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsert(string $key, string $url, ?string $fallbackUrl, ?int $adminId): void
    {
        $existing = $this->findByKey($key);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE site_images SET url_image = :url, fallback_url = :fb, updated_by = :admin WHERE key_image = :k'
            );
            $stmt->execute([':url' => $url, ':fb' => $fallbackUrl, ':admin' => $adminId, ':k' => $key]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO site_images (key_image, url_image, fallback_url, updated_by) VALUES (:k, :url, :fb, :admin)'
            );
            $stmt->execute([':k' => $key, ':url' => $url, ':fb' => $fallbackUrl, ':admin' => $adminId]);
        }
    }

    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM site_images WHERE key_image = :k');
        $stmt->execute([':k' => $key]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, array{url: string, fallback: ?string}>
     */
    public function asKeyMap(): array
    {
        $map = [];
        foreach ($this->findAll() as $row) {
            $map[$row['key_image']] = [
                'url' => $row['url_image'],
                'fallback' => $row['fallback_url'],
            ];
        }

        return $map;
    }
}
