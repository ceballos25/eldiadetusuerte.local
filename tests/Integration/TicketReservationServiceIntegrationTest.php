<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Ticket\TicketReservationService;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoTicketRepository;
use Tests\Support\DatabaseTestCase;

final class TicketReservationServiceIntegrationTest extends DatabaseTestCase
{
    public function testHoldUntilReviewIntegration(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $id1 = $this->createAvailableTicket($raffleId, 'H1');
        $id2 = $this->createAvailableTicket($raffleId, 'H2');

        $raffles = new PdoRaffleRepository($this->pdo);
        $tickets = new PdoTicketRepository($this->pdo);
        $service = new TicketReservationService($tickets, $raffles, $this->pdo);

        $result = $service->reserveForPayment($raffleId, 2, [$id1, $id2], true);

        self::assertNull($result['expires_at']);
        self::assertSame(TicketStatus::RESERVED, $this->ticketStatus($id1));

        $tickets->release($raffleId, [$id1, $id2]);
    }

    public function testTimedReservationUsesRaffleMinutes(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $this->pdo->prepare(
            'UPDATE raffles SET reservation_minutes_raffle = 1 WHERE id_raffle = :id'
        )->execute([':id' => $raffleId]);

        $ticketId = $this->createAvailableTicket($raffleId, 'T1');

        $service = new TicketReservationService(
            new PdoTicketRepository($this->pdo),
            new PdoRaffleRepository($this->pdo),
            $this->pdo
        );

        $before = new \DateTimeImmutable('now');
        $result = $service->reserveForPayment($raffleId, 1, [$ticketId], false);
        $after = new \DateTimeImmutable('+2 minutes');

        self::assertNotNull($result['expires_at']);
        $expires = new \DateTimeImmutable($result['expires_at']);
        self::assertGreaterThan($before, $expires);
        self::assertLessThanOrEqual($after, $expires);

        (new PdoTicketRepository($this->pdo))->release($raffleId, [$ticketId]);
    }

    private function ticketStatus(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT status_ticket FROM tickets WHERE id_ticket = :id');
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
