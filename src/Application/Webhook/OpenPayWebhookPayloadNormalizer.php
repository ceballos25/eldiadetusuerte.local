<?php
declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Normaliza el JSON que envía OpenPay al webhook (varía según tipo de evento).
 */
final class OpenPayWebhookPayloadNormalizer
{
    /**
     * @return array{
     *   type: string|null,
     *   transaction: array<string, mixed>|null,
     *   raw: array<string, mixed>
     * }
     */
    public static function normalize(array $raw): array
    {
        $type = isset($raw['type']) ? (string)$raw['type'] : null;
        if (($type === null || $type === '') && isset($raw['event_type'])) {
            $type = (string)$raw['event_type'];
        }

        $tx = null;
        if (isset($raw['transaction']) && is_array($raw['transaction'])) {
            $tx = $raw['transaction'];
        } elseif (isset($raw['data']) && is_array($raw['data'])) {
            $tx = $raw['data'];
        }

        if ($tx !== null && isset($tx['order_id']) === false && isset($tx['orderId'])) {
            $tx['order_id'] = $tx['orderId'];
        }

        return [
            'type' => $type,
            'transaction' => $tx,
            'raw' => $raw,
        ];
    }
}
