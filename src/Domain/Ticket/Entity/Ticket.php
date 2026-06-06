<?php
declare(strict_types=1);

namespace App\Domain\Ticket\Entity;

final class Ticket
{
    public function __construct(
        public readonly int $id,
        public readonly string $number,
        public readonly int $status,
        public readonly int $raffleId
    ) {
    }
}
