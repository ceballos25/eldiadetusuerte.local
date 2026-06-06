<?php
declare(strict_types=1);

namespace App\Application\Webhook;

use App\Domain\Payment\ValueObject\PaymentBackupStatus;

/**
 * Reglas de decisión del webhook OpenPay (testeable sin BD).
 */
final class OpenPayWebhookGuard
{
    /**
     * @param array<string, mixed>|null $transaction
     * @return array{decision: string, reason?: string, tx_status?: string}
     */
    public static function evaluateRejectedEvent(string $eventType, ?array $transaction, int $backupStatus): array
    {
        if (!in_array($eventType, OpenPayWebhookEventTypes::rejectedEvents(), true)) {
            return ['decision' => 'not_rejected_event'];
        }

        $txStatus = OpenPayTransactionStatus::statusFromTransaction($transaction);

        if (OpenPayTransactionStatus::transactionIsApprovedForSale($transaction)) {
            return [
                'decision' => 'ignore',
                'reason' => 'reject_event_with_approved_tx_status',
                'tx_status' => $txStatus,
            ];
        }

        if ($backupStatus === PaymentBackupStatus::PENDING) {
            if (!OpenPayTransactionStatus::transactionIsTerminalFailure($transaction)) {
                return [
                    'decision' => 'ignore',
                    'reason' => 'reject_event_without_terminal_tx_status',
                    'tx_status' => $txStatus,
                ];
            }

            return ['decision' => 'reject'];
        }

        if ($backupStatus === PaymentBackupStatus::REJECTED) {
            return ['decision' => 'release_reserved'];
        }

        return ['decision' => 'ignore', 'reason' => 'reject_not_applicable'];
    }

    public static function canApproveBackup(int $backupStatus, ?string $eventType): bool
    {
        $isApprovedEvent = $eventType !== null
            && in_array($eventType, OpenPayWebhookEventTypes::approvedEvents(), true);

        if (!$isApprovedEvent) {
            return false;
        }

        if (in_array(
            $backupStatus,
            [PaymentBackupStatus::PENDING, PaymentBackupStatus::ERROR, PaymentBackupStatus::REJECTED],
            true
        )) {
            return true;
        }

        return $backupStatus === PaymentBackupStatus::APPROVED;
    }
}
