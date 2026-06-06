<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payment\ValueObject\PaymentBackupStatus;
use PHPUnit\Framework\TestCase;

final class PaymentBackupStatusTest extends TestCase
{
    public function testStatusConstantsAreDistinct(): void
    {
        $statuses = [
            PaymentBackupStatus::PENDING,
            PaymentBackupStatus::APPROVED,
            PaymentBackupStatus::REJECTED,
            PaymentBackupStatus::ERROR,
            PaymentBackupStatus::EXPIRED,
        ];

        self::assertSame(count($statuses), count(array_unique($statuses)));
    }

    public function testPendingIsOne(): void
    {
        self::assertSame(1, PaymentBackupStatus::PENDING);
    }

    public function testRejectedIsThree(): void
    {
        self::assertSame(3, PaymentBackupStatus::REJECTED);
    }
}
