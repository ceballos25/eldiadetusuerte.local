<?php
declare(strict_types=1);

namespace App\Domain\Webhook\Repository;

interface WebhookRepositoryInterface
{
    public function store(string $uuid, string $source, ?string $eventType, array $payload): int;

    public function markProcessing(int $id): bool;

    public function markProcessed(int $id): void;

    public function markError(int $id, string $message): void;

    public function findByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findPendingForReprocess(int $limit = 50): array;
}
