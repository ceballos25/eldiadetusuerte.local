<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Raffle\ValueObject\RaffleType;
use PHPUnit\Framework\TestCase;

final class RaffleTypeTest extends TestCase
{
    public function testValidTypes(): void
    {
        self::assertTrue(RaffleType::isValid(RaffleType::MANUAL));
        self::assertTrue(RaffleType::isValid(RaffleType::AUTOMATIC));
    }

    public function testInvalidType(): void
    {
        self::assertFalse(RaffleType::isValid('random'));
        self::assertFalse(RaffleType::isValid(''));
    }
}
