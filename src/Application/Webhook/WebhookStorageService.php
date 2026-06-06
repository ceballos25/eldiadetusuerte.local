<?php
declare(strict_types=1);

namespace App\Application\Webhook;

use App\Domain\Webhook\Repository\WebhookRepositoryInterface;
use App\Shared\Exception\DomainException;

/**
 * Persistencia de webhooks en BD (servidor principal).
 * Los archivos JSON viven en accesorios (service-payment-server).
 */
final class WebhookStorageService
{
    public function __construct(
        private readonly WebhookRepositoryInterface $repository
    ) {
    }

    /**
     * @return array{id: int, uuid: string, filename: string, file: null}
     */
    public function store(string $source, array $payload, ?string $eventType = null): array
    {
        $uuid = $this->generateUuid();
        $eventType ??= (string)($payload['type'] ?? 'unknown');

        $id = $this->repository->store($uuid, $source, $eventType, $payload);

        return ['id' => $id, 'uuid' => $uuid, 'filename' => $uuid . '.json', 'file' => null];
    }

    public function moveToProcessed(string $filename): void
    {
        // Archivos en accesorios; principal solo BD.
    }

    public function moveToError(string $filename): void
    {
        // Archivos en accesorios; principal solo BD.
    }

    public function reprocessFromFile(string $uuid): ?array
    {
        return null;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
