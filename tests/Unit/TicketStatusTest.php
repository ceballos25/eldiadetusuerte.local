<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Ticket\ValueObject\TicketStatus;
use PHPUnit\Framework\TestCase;

final class TicketStatusTest extends TestCase
{
    public function testLabels(): void
    {
        self::assertSame('disponible', TicketStatus::label(TicketStatus::AVAILABLE));
        self::assertSame('reservado', TicketStatus::label(TicketStatus::RESERVED));
        self::assertSame('pagado', TicketStatus::label(TicketStatus::PAID));
    }

    public function testPredicates(): void
    {
        self::assertTrue(TicketStatus::isAvailable(TicketStatus::AVAILABLE));
        self::assertTrue(TicketStatus::isReserved(TicketStatus::RESERVED));
        self::assertTrue(TicketStatus::isPaid(TicketStatus::PAID));
        self::assertFalse(TicketStatus::isAvailable(TicketStatus::RESERVED));
    }

    public function testAllStatusesAreUnique(): void
    {
        self::assertSame(count(TicketStatus::all()), count(array_unique(TicketStatus::all())));
    }
}
