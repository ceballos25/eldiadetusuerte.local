<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\OpenPayWebhookPayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class OpenPayWebhookPayloadNormalizerTest extends TestCase
{
    public function testNormalizesStandardChargePayload(): void
    {
        $raw = [
            'type' => 'charge.succeeded',
            'transaction' => [
                'id' => 'trx1',
                'order_id' => 'PB-20260101120000123',
                'status' => 'completed',
            ],
        ];

        $normalized = OpenPayWebhookPayloadNormalizer::normalize($raw);

        self::assertSame('charge.succeeded', $normalized['type']);
        self::assertSame('PB-20260101120000123', $normalized['transaction']['order_id']);
    }

    public function testUsesEventTypeFallback(): void
    {
        $raw = [
            'event_type' => 'charge.failed',
            'data' => ['order_id' => 'PB-999', 'status' => 'failed'],
        ];

        $normalized = OpenPayWebhookPayloadNormalizer::normalize($raw);

        self::assertSame('charge.failed', $normalized['type']);
        self::assertSame('PB-999', $normalized['transaction']['order_id']);
    }

    public function testMapsOrderIdCamelCase(): void
    {
        $raw = [
            'type' => 'charge.succeeded',
            'transaction' => ['orderId' => 'PB-CAMEL', 'status' => 'completed'],
        ];

        $normalized = OpenPayWebhookPayloadNormalizer::normalize($raw);

        self::assertSame('PB-CAMEL', $normalized['transaction']['order_id']);
    }

    public function testIncompletePayloadReturnsNullTransaction(): void
    {
        $normalized = OpenPayWebhookPayloadNormalizer::normalize(['type' => 'verification']);

        self::assertSame('verification', $normalized['type']);
        self::assertNull($normalized['transaction']);
    }
}
