<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Webhook\Repository\WebhookRepositoryInterface;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoWebhookRepository implements WebhookRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function store(string $uuid, string $source, ?string $eventType, array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_events (uuid_webhook, source_webhook, event_type_webhook, payload_webhook)
             VALUES (:uuid, :source, :type, :payload)'
        );
        $stmt->execute([
            ':uuid' => $uuid,
            ':source' => $source,
            ':type' => $eventType,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function markProcessing(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_events SET status_webhook = 'processing', attempts = attempts + 1
             WHERE id_webhook = :id AND status_webhook IN ('pending','error')"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function markProcessed(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_events SET status_webhook = 'processed', processed_at = NOW(), error_message = NULL
             WHERE id_webhook = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function markError(int $id, string $message): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE webhook_events SET status_webhook = 'error', error_message = :msg WHERE id_webhook = :id"
        );
        $stmt->execute([':id' => $id, ':msg' => mb_substr($message, 0, 2000)]);
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_events WHERE uuid_webhook = :uuid LIMIT 1');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPendingForReprocess(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhook_events WHERE status_webhook IN ('pending','error') ORDER BY created_at ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
