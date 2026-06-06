<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Ticket\TicketReservationService;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoTicketRepository;
use Tests\Support\DatabaseTestCase;

final class PdoTicketRepositoryTest extends DatabaseTestCase
{
    public function testReserveAndReleaseWithExpiry(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'A');
        $repo = new PdoTicketRepository($this->pdo);

        $expires = new \DateTimeImmutable('+1 minute');
        self::assertSame(1, $repo->reserve($raffleId, [$ticketId], $expires));

        $row = $this->fetchTicket($ticketId);
        self::assertSame(TicketStatus::RESERVED, (int)$row['status_ticket']);
        self::assertNotNull($row['expires_at_ticket']);

        self::assertSame(1, $repo->release($raffleId, [$ticketId]));
        $row = $this->fetchTicket($ticketId);
        self::assertSame(TicketStatus::AVAILABLE, (int)$row['status_ticket']);
        self::assertNull($row['expires_at_ticket']);
    }

    public function testReserveWithoutExpiryForTransfer(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'B');
        $repo = new PdoTicketRepository($this->pdo);

        self::assertSame(1, $repo->reserve($raffleId, [$ticketId], null));

        $row = $this->fetchTicket($ticketId);
        self::assertSame(TicketStatus::RESERVED, (int)$row['status_ticket']);
        self::assertNull($row['expires_at_ticket']);

        // Forzar expiración en otro ticket y verificar que este NO se libera
        $timedId = $this->createAvailableTicket($raffleId, 'C');
        $repo->reserve($raffleId, [$timedId], new \DateTimeImmutable('-1 minute'));

        self::assertSame(1, $repo->releaseExpiredReservations());

        $holdRow = $this->fetchTicket($ticketId);
        $timedRow = $this->fetchTicket($timedId);
        self::assertSame(TicketStatus::RESERVED, (int)$holdRow['status_ticket']);
        self::assertSame(TicketStatus::AVAILABLE, (int)$timedRow['status_ticket']);

        $repo->release($raffleId, [$ticketId]);
    }

    public function testCannotReserveAlreadyReservedTicket(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'D');
        $repo = new PdoTicketRepository($this->pdo);

        $repo->reserve($raffleId, [$ticketId], new \DateTimeImmutable('+5 minutes'));
        self::assertSame(0, $repo->reserve($raffleId, [$ticketId], new \DateTimeImmutable('+5 minutes')));

        $repo->release($raffleId, [$ticketId]);
    }

    /** @return array<string, mixed> */
    private function fetchTicket(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT status_ticket, expires_at_ticket FROM tickets WHERE id_ticket = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
