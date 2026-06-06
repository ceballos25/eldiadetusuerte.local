<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\OpenPayTransactionStatus;
use PHPUnit\Framework\TestCase;

final class OpenPayTransactionStatusTest extends TestCase
{
    public function testApprovedStatuses(): void
    {
        self::assertTrue(OpenPayTransactionStatus::isApprovedForSale('completed'));
        self::assertTrue(OpenPayTransactionStatus::isApprovedForSale('paid'));
        self::assertTrue(OpenPayTransactionStatus::transactionIsApprovedForSale(['status' => 'completed']));
    }

    public function testTerminalFailureStatuses(): void
    {
        self::assertTrue(OpenPayTransactionStatus::isTerminalFailure('failed'));
        self::assertTrue(OpenPayTransactionStatus::isTerminalFailure('cancelled'));
        self::assertTrue(OpenPayTransactionStatus::transactionIsTerminalFailure(['status' => 'failed']));
    }

    public function testRejectEventWithCompletedTxIsNotTerminalFailure(): void
    {
        self::assertFalse(OpenPayTransactionStatus::transactionIsTerminalFailure(['status' => 'completed']));
        self::assertTrue(OpenPayTransactionStatus::transactionIsApprovedForSale(['status' => 'completed']));
    }

    public function testEmptyTransactionIsNotApproved(): void
    {
        self::assertFalse(OpenPayTransactionStatus::transactionIsApprovedForSale(null));
        self::assertFalse(OpenPayTransactionStatus::transactionIsTerminalFailure(null));
    }
}
