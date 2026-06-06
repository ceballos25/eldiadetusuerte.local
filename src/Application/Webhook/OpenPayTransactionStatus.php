<?php
declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Estados de transacción OpenPay relevantes para aprobar o rechazar una venta.
 */
final class OpenPayTransactionStatus
{
    /**
     * @param array<string, mixed>|null $transaction
     */
    public static function statusFromTransaction(?array $transaction): string
    {
        if ($transaction === null) {
            return '';
        }

        return strtolower(trim((string)($transaction['status'] ?? '')));
    }

    public static function isApprovedForSale(string $status): bool
    {
        return in_array($status, ['completed', 'paid', 'in_progress', 'charge_pending'], true);
    }

    public static function isTerminalFailure(string $status): bool
    {
        return in_array($status, ['failed', 'cancelled', 'canceled', 'expired', 'declined', 'error'], true);
    }

    /**
     * @param array<string, mixed>|null $transaction
     */
    public static function transactionIsApprovedForSale(?array $transaction): bool
    {
        return self::isApprovedForSale(self::statusFromTransaction($transaction));
    }

    /**
     * @param array<string, mixed>|null $transaction
     */
    public static function transactionIsTerminalFailure(?array $transaction): bool
    {
        $status = self::statusFromTransaction($transaction);

        return $status !== '' && self::isTerminalFailure($status);
    }
}
