<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\OpenPayWebhookEventTypes;
use App\Application\Webhook\OpenPayWebhookGuard;
use App\Domain\Payment\ValueObject\PaymentBackupStatus;
use PHPUnit\Framework\TestCase;

final class OpenPayWebhookGuardTest extends TestCase
{
    public function testRejectEventWithCompletedTxIsIgnored(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_FAILED,
            ['status' => 'completed', 'order_id' => 'PB-1'],
            PaymentBackupStatus::PENDING
        );

        self::assertSame('ignore', $decision['decision']);
        self::assertSame('reject_event_with_approved_tx_status', $decision['reason']);
    }

    public function testRejectEventWithFailedTxOnPendingBackupRejects(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_CANCELLED,
            ['status' => 'cancelled', 'order_id' => 'PB-1'],
            PaymentBackupStatus::PENDING
        );

        self::assertSame('reject', $decision['decision']);
    }

    public function testRejectEventWithInProgressTxIsIgnoredAsApproved(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_FAILED,
            ['status' => 'in_progress', 'order_id' => 'PB-1'],
            PaymentBackupStatus::PENDING
        );

        self::assertSame('ignore', $decision['decision']);
        self::assertSame('reject_event_with_approved_tx_status', $decision['reason']);
    }

    public function testRejectEventWithoutTerminalStatusIsIgnored(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_FAILED,
            ['status' => 'pending', 'order_id' => 'PB-1'],
            PaymentBackupStatus::PENDING
        );

        self::assertSame('ignore', $decision['decision']);
        self::assertSame('reject_event_without_terminal_tx_status', $decision['reason']);
    }

    public function testRejectEventOnAlreadyRejectedBackupReleasesReserved(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_FAILED,
            ['status' => 'failed', 'order_id' => 'PB-1'],
            PaymentBackupStatus::REJECTED
        );

        self::assertSame('release_reserved', $decision['decision']);
    }

    public function testRejectEventOnApprovedBackupIsIgnored(): void
    {
        $decision = OpenPayWebhookGuard::evaluateRejectedEvent(
            OpenPayWebhookEventTypes::CHARGE_CANCELLED,
            ['status' => 'cancelled', 'order_id' => 'PB-1'],
            PaymentBackupStatus::APPROVED
        );

        self::assertSame('ignore', $decision['decision']);
        self::assertSame('reject_not_applicable', $decision['reason']);
    }

    public function testCanApproveFromPendingBackup(): void
    {
        self::assertTrue(
            OpenPayWebhookGuard::canApproveBackup(
                PaymentBackupStatus::PENDING,
                OpenPayWebhookEventTypes::CHARGE_SUCCEEDED
            )
        );
    }

    public function testCanApproveFromRejectedBackupAfterLateSuccess(): void
    {
        self::assertTrue(
            OpenPayWebhookGuard::canApproveBackup(
                PaymentBackupStatus::REJECTED,
                OpenPayWebhookEventTypes::CHARGE_SUCCEEDED
            )
        );
    }

    public function testCannotApproveFromRejectedBackupOnFailureEvent(): void
    {
        self::assertFalse(
            OpenPayWebhookGuard::canApproveBackup(
                PaymentBackupStatus::REJECTED,
                OpenPayWebhookEventTypes::CHARGE_FAILED
            )
        );
    }

    public function testCanApproveFromErrorBackup(): void
    {
        self::assertTrue(
            OpenPayWebhookGuard::canApproveBackup(
                PaymentBackupStatus::ERROR,
                OpenPayWebhookEventTypes::CHARGE_SUCCEEDED
            )
        );
    }
}
