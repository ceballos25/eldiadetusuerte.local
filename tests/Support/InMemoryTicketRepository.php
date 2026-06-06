<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Ticket\Repository\TicketRepositoryInterface;
use App\Domain\Ticket\ValueObject\TicketStatus;

/**
 * Repositorio en memoria para pruebas unitarias de reservas.
 *
 * @phpstan-type TicketRow array{
 *   id_ticket: int,
 *   number_ticket: string,
 *   status_ticket: int,
 *   id_raffle_ticket: int,
 *   expires_at_ticket: ?string
 * }
 */
final class InMemoryTicketRepository implements TicketRepositoryInterface
{
    /** @var array<int, TicketRow> */
    private array $tickets = [];

    /** @param list<TicketRow> $tickets */
    public function __construct(array $tickets = [])
    {
        foreach ($tickets as $row) {
            $this->tickets[(int)$row['id_ticket']] = $row;
        }
    }

    public function seed(int $id, int $raffleId, string $number, int $status = TicketStatus::AVAILABLE): void
    {
        $this->tickets[$id] = [
            'id_ticket' => $id,
            'id_raffle_ticket' => $raffleId,
            'number_ticket' => $number,
            'status_ticket' => $status,
            'expires_at_ticket' => null,
        ];
    }

    public function findByIdsForUpdate(int $raffleId, array $ticketIds): array
    {
        $out = [];
        foreach ($ticketIds as $id) {
            $row = $this->tickets[(int)$id] ?? null;
            if ($row !== null && (int)$row['id_raffle_ticket'] === $raffleId) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function findAvailableRandom(int $raffleId, int $quantity): array
    {
        $available = [];
        foreach ($this->tickets as $row) {
            if ((int)$row['id_raffle_ticket'] === $raffleId
                && (int)$row['status_ticket'] === TicketStatus::AVAILABLE) {
                $available[] = [
                    'id_ticket' => (int)$row['id_ticket'],
                    'number_ticket' => (string)$row['number_ticket'],
                ];
            }
        }

        return array_slice($available, 0, $quantity);
    }

    public function countAvailable(int $raffleId): int
    {
        return count($this->findAvailableRandom($raffleId, PHP_INT_MAX));
    }

    public function reserve(int $raffleId, array $ticketIds, ?\DateTimeInterface $expiresAt): int
    {
        $count = 0;
        foreach ($ticketIds as $id) {
            $id = (int)$id;
            if (!isset($this->tickets[$id])) {
                continue;
            }
            if ((int)$this->tickets[$id]['status_ticket'] !== TicketStatus::AVAILABLE) {
                continue;
            }
            if ((int)$this->tickets[$id]['id_raffle_ticket'] !== $raffleId) {
                continue;
            }
            $this->tickets[$id]['status_ticket'] = TicketStatus::RESERVED;
            $this->tickets[$id]['expires_at_ticket'] = $expiresAt?->format('Y-m-d H:i:s');
            ++$count;
        }

        return $count;
    }

    public function confirmPaid(int $raffleId, array $ticketIds, int $customerId, int $saleId): int
    {
        $count = 0;
        foreach ($ticketIds as $id) {
            $id = (int)$id;
            if (!isset($this->tickets[$id])) {
                continue;
            }
            $this->tickets[$id]['status_ticket'] = TicketStatus::PAID;
            $this->tickets[$id]['expires_at_ticket'] = null;
            ++$count;
        }

        return $count;
    }

    public function release(int $raffleId, array $ticketIds): int
    {
        $count = 0;
        foreach ($ticketIds as $id) {
            $id = (int)$id;
            if (!isset($this->tickets[$id])) {
                continue;
            }
            if ((int)$this->tickets[$id]['status_ticket'] !== TicketStatus::RESERVED) {
                continue;
            }
            $this->tickets[$id]['status_ticket'] = TicketStatus::AVAILABLE;
            $this->tickets[$id]['expires_at_ticket'] = null;
            ++$count;
        }

        return $count;
    }

    public function releaseExpiredReservations(): int
    {
        $now = new \DateTimeImmutable('now');
        $count = 0;
        foreach ($this->tickets as &$row) {
            if ((int)$row['status_ticket'] !== TicketStatus::RESERVED) {
                continue;
            }
            $exp = $row['expires_at_ticket'] ?? null;
            if ($exp === null || $exp === '') {
                continue;
            }
            if (new \DateTimeImmutable($exp) >= $now) {
                continue;
            }
            $row['status_ticket'] = TicketStatus::AVAILABLE;
            $row['expires_at_ticket'] = null;
            ++$count;
        }
        unset($row);

        return $count;
    }

    public function findBySaleId(int $saleId): array
    {
        return [];
    }

    public function cancelFromSale(int $saleId, array $ticketIds, int $adminId): int
    {
        return 0;
    }

    public function statusOf(int $ticketId): ?int
    {
        return isset($this->tickets[$ticketId])
            ? (int)$this->tickets[$ticketId]['status_ticket']
            : null;
    }

    public function expiresAtOf(int $ticketId): ?string
    {
        return $this->tickets[$ticketId]['expires_at_ticket'] ?? null;
    }
}
