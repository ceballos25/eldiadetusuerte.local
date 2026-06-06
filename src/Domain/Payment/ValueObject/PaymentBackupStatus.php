<?php
declare(strict_types=1);

namespace App\Domain\Payment\ValueObject;

final class PaymentBackupStatus
{
    public const PENDING = 1;
    public const APPROVED = 2;
    public const REJECTED = 3;
    public const ERROR = 4;
    public const EXPIRED = 5;

    private function __construct()
    {
    }
}
