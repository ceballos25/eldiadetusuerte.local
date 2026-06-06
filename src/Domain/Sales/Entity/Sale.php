<?php
declare(strict_types=1);

namespace App\Domain\Sales\Entity;

final class Sale
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly float $total,
        public readonly int $quantity,
        public readonly string $paymentMethod,
        public readonly int $raffleId,
        public readonly int $customerId
    ) {
    }
}
