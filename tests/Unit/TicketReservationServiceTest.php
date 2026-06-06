<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Ticket\TicketReservationService;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryRaffleRepository;
use Tests\Support\InMemoryTicketRepository;

final class TicketReservationServiceTest extends TestCase
{
    public function testReserveMarksTicketsAsReserved(): void
    {
        $raffles = InMemoryRaffleRepository::manual(1, 15, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(1, 1, '001');
        $tickets->seed(2, 1, '002');

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $result = $service->reserveForPayment(1, 2, [1, 2], false);

        self::assertSame([1, 2], $result['ticket_ids']);
        self::assertSame(TicketStatus::RESERVED, $tickets->statusOf(1));
        self::assertSame(TicketStatus::RESERVED, $tickets->statusOf(2));
        self::assertNotNull($result['expires_at']);
    }

    public function testHoldUntilReviewSetsNullExpiry(): void
    {
        $raffles = InMemoryRaffleRepository::manual(2, 1, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(3, 2, '003');

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $result = $service->reserveForPayment(2, 1, [3], true);

        self::assertNull($result['expires_at']);
        self::assertNull($tickets->expiresAtOf(3));
    }

    public function testReleaseReturnsTicketsToAvailable(): void
    {
        $raffles = InMemoryRaffleRepository::manual(3, 15, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(4, 3, '004', TicketStatus::RESERVED);

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $released = $service->release(3, [4]);

        self::assertSame(1, $released);
        self::assertSame(TicketStatus::AVAILABLE, $tickets->statusOf(4));
    }

    public function testReleaseExpiredSkipsHoldUntilReview(): void
    {
        $raffles = InMemoryRaffleRepository::manual(4, 1, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(5, 4, '005');
        $tickets->seed(6, 4, '006');

        $tickets->reserve(4, [5], new \DateTimeImmutable('-5 minutes'));
        $tickets->reserve(4, [6], null);

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());
        $released = $service->releaseExpired();

        self::assertSame(1, $released);
        self::assertSame(TicketStatus::AVAILABLE, $tickets->statusOf(5));
        self::assertSame(TicketStatus::RESERVED, $tickets->statusOf(6));
    }

    public function testUnavailableTicketThrows(): void
    {
        $raffles = InMemoryRaffleRepository::manual(5, 15, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(7, 5, '007', TicketStatus::PAID);

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no está disponible');

        $service->reserveForPayment(5, 1, [7], false);
    }

    public function testQuantityMismatchThrows(): void
    {
        $raffles = InMemoryRaffleRepository::manual(6, 15, 1);
        $tickets = new InMemoryTicketRepository();
        $tickets->seed(8, 6, '008');

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cantidad no coincide');

        $service->reserveForPayment(6, 3, [8], false);
    }

    public function testBlockedRaffleThrows(): void
    {
        $raffles = new InMemoryRaffleRepository([
            99 => [
                'id_raffle' => 99,
                'type_raffle' => 'manual',
                'status_raffle' => 0,
                'min_quantity_raffle' => 1,
            ],
        ]);
        $tickets = new InMemoryTicketRepository();

        $service = new TicketReservationService($tickets, $raffles, $this->mockPdo());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no acepta ventas');

        $service->reserveForPayment(99, 1, [1], false);
    }

    private function mockPdo(): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }
}
