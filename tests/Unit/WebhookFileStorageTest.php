<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../service-payment-server/lib/WebhookFileStorage.php';

final class WebhookFileStorageTest extends TestCase
{
    public function testExtractRawPayloadFromEnvelope(): void
    {
        $raw = \PaymentWebhookFileStorage::extractRawPayloadFromFile([
            'order_code' => 'PB-1',
            'payload' => [
                'type' => 'charge.succeeded',
                'transaction' => ['order_id' => 'PB-1'],
            ],
        ]);

        self::assertIsString($raw);
        self::assertStringContainsString('charge.succeeded', $raw);
        self::assertStringContainsString('PB-1', $raw);
    }

    public function testExtractRawPayloadFromRootEvent(): void
    {
        $raw = \PaymentWebhookFileStorage::extractRawPayloadFromFile([
            'type' => 'charge.succeeded',
            'transaction' => ['order_id' => 'PB-2'],
        ]);

        self::assertIsString($raw);
        self::assertStringContainsString('PB-2', $raw);
    }

    public function testExtractRawPayloadReturnsNullForInvalid(): void
    {
        self::assertNull(\PaymentWebhookFileStorage::extractRawPayloadFromFile(null));
        self::assertNull(\PaymentWebhookFileStorage::extractRawPayloadFromFile(['foo' => 'bar']));
    }

    public function testShouldPersistAllExceptVerification(): void
    {
        self::assertTrue(\PaymentWebhookFileStorage::shouldPersist(['type' => 'charge.succeeded']));
        self::assertTrue(\PaymentWebhookFileStorage::shouldPersist(['type' => 'charge.failed']));
        self::assertFalse(\PaymentWebhookFileStorage::shouldPersist(['type' => 'verification']));
    }
}
