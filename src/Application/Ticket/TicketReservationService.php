<?php
declare(strict_types=1);

namespace App\Application\Ticket;

use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Ticket\Repository\TicketRepositoryInterface;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;

final class TicketReservationService
{
    private PDO $pdo;

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly RaffleRepositoryInterface $raffles,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * Reserve tickets for payment initiation.
     *
     * @param list<int>|null $ticketIds Explicit IDs for manual selection; null for automatic.
     * @return array{ticket_ids: list<int>, numbers: list<string>, expires_at: string|null}
     */
    public function reserveForPayment(
        int $raffleId,
        int $quantity,
        ?array $ticketIds = null,
        bool $holdUntilReview = false
    ): array
    {
        if (!$this->raffles->canAcceptSales($raffleId)) {
            throw new DomainException('La rifa no acepta ventas en este momento', 'RAFFLE_SALES_BLOCKED');
        }

        $raffle = $this->raffles->findById($raffleId);
        if ($raffle === null) {
            throw new DomainException('Rifa no encontrada', 'RAFFLE_NOT_FOUND');
        }

        $minQty = (int)($raffle['min_quantity_raffle'] ?? 1);
        if ($quantity < $minQty) {
            throw new DomainException("La cantidad mínima es {$minQty}", 'MIN_QUANTITY');
        }

        $expiresAt = $holdUntilReview
            ? null
            : new \DateTimeImmutable('+' . (int)($raffle['reservation_minutes_raffle'] ?? 15) . ' minutes');

        $this->pdo->beginTransaction();
        try {
            $this->tickets->releaseExpiredReservations();
            $this->cleanupOrphanedPaymentBackupTicketLinks();

            if ($ticketIds !== null && $ticketIds !== []) {
                $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
                if (count($ticketIds) !== $quantity) {
                    throw new DomainException('La cantidad no coincide con los nros seleccionados', 'QUANTITY_MISMATCH');
                }
                $rows = $this->tickets->findByIdsForUpdate($raffleId, $ticketIds);
                if (count($rows) !== count($ticketIds)) {
                    throw new DomainException('Algunos nros no existen', 'TICKETS_NOT_FOUND');
                }
                foreach ($rows as $row) {
                    if ((int)$row['status_ticket'] !== TicketStatus::AVAILABLE) {
                        throw new DomainException(
                            'El nro ' . $row['number_ticket'] . ' no está disponible',
                            'TICKET_UNAVAILABLE'
                        );
                    }
                }
            } else {
                $rows = $this->tickets->findAvailableRandom($raffleId, $quantity);
                if (count($rows) < $quantity) {
                    throw new DomainException('No hay suficientes nros disponibles', 'INSUFFICIENT_TICKETS');
                }
                $ticketIds = array_map(static fn (array $r) => (int)$r['id_ticket'], $rows);
            }

            $reserved = $this->tickets->reserve($raffleId, $ticketIds, $expiresAt);
            if ($reserved !== count($ticketIds)) {
                throw new DomainException(
                    'No se pudieron reservar todos los nros. Intenta de nuevo.',
                    'RESERVATION_RACE'
                );
            }

            $numbers = array_map(static fn (array $r) => (string)$r['number_ticket'], $rows);
            if ($numbers === [] && $ticketIds !== []) {
                $rows = $this->tickets->findByIdsForUpdate($raffleId, $ticketIds);
                $numbers = array_map(static fn (array $r) => (string)$r['number_ticket'], $rows);
            }

            $this->pdo->commit();

            return [
                'ticket_ids' => $ticketIds,
                'numbers' => $numbers,
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<int> $ticketIds
     */
    public function release(int $raffleId, array $ticketIds): int
    {
        $this->pdo->beginTransaction();
        try {
            $released = $this->tickets->release($raffleId, $ticketIds);
            $this->pdo->commit();

            return $released;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function releaseExpired(): int
    {
        $released = $this->tickets->releaseExpiredReservations();
        $this->cleanupOrphanedPaymentBackupTicketLinks();

        return $released;
    }

    private function cleanupOrphanedPaymentBackupTicketLinks(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE pbt FROM payment_backup_tickets pbt
             INNER JOIN tickets t ON t.id_ticket = pbt.id_ticket
             WHERE t.status_ticket = :available'
        );
        $stmt->execute([':available' => TicketStatus::AVAILABLE]);

        return $stmt->rowCount();
    }
}
