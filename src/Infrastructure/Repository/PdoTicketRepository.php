<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Ticket\Repository\TicketRepositoryInterface;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoTicketRepository implements TicketRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function findByIdsForUpdate(int $raffleId, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT id_ticket, number_ticket, status_ticket, id_raffle_ticket
                FROM tickets
                WHERE id_raffle_ticket = ? AND id_ticket IN ({$placeholders})
                FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$raffleId], $ticketIds);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAvailableRandom(int $raffleId, int $quantity): array
    {
        $sql = 'SELECT id_ticket, number_ticket
                FROM tickets
                WHERE id_raffle_ticket = :r AND status_ticket = :s
                ORDER BY id_ticket ASC
                LIMIT :lim
                FOR UPDATE SKIP LOCKED';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':r', $raffleId, PDO::PARAM_INT);
        $stmt->bindValue(':s', TicketStatus::AVAILABLE, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $quantity, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAvailable(int $raffleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = :s'
        );
        $stmt->execute([':r' => $raffleId, ':s' => TicketStatus::AVAILABLE]);

        return (int)$stmt->fetchColumn();
    }

    public function reserve(int $raffleId, array $ticketIds, ?\DateTimeInterface $expiresAt): int
    {
        if ($ticketIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "UPDATE tickets
                SET status_ticket = ?, expires_at_ticket = ?, date_updated_ticket = NOW()
                WHERE id_raffle_ticket = ? AND id_ticket IN ({$placeholders}) AND status_ticket = ?";
        $params = array_merge(
            [TicketStatus::RESERVED, $expiresAt?->format('Y-m-d H:i:s'), $raffleId],
            $ticketIds,
            [TicketStatus::AVAILABLE]
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function confirmPaid(int $raffleId, array $ticketIds, int $customerId, int $saleId): int
    {
        if ($ticketIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "UPDATE tickets
                SET status_ticket = ?, id_customer_ticket = ?, id_sale_ticket = ?,
                    expires_at_ticket = NULL, date_updated_ticket = NOW()
                WHERE id_raffle_ticket = ? AND id_ticket IN ({$placeholders})
                  AND status_ticket IN (?, ?)";
        $params = array_merge(
            [TicketStatus::PAID, $customerId, $saleId, $raffleId],
            $ticketIds,
            [TicketStatus::RESERVED, TicketStatus::AVAILABLE]
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function release(int $raffleId, array $ticketIds): int
    {
        if ($ticketIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "UPDATE tickets
                SET status_ticket = ?, id_customer_ticket = NULL, id_sale_ticket = NULL,
                    expires_at_ticket = NULL, date_updated_ticket = NOW()
                WHERE id_raffle_ticket = ? AND id_ticket IN ({$placeholders}) AND status_ticket = ?";
        $params = array_merge(
            [TicketStatus::AVAILABLE, $raffleId],
            $ticketIds,
            [TicketStatus::RESERVED]
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function releaseExpiredReservations(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tickets t
             SET t.status_ticket = :available, t.expires_at_ticket = NULL, t.date_updated_ticket = NOW()
             WHERE t.status_ticket = :reserved
               AND t.expires_at_ticket IS NOT NULL
               AND t.expires_at_ticket < NOW()
               AND NOT EXISTS (
                   SELECT 1 FROM payment_backup_tickets pbt
                   INNER JOIN payment_backups pb ON pb.id_payment_backup = pbt.id_payment_backup
                   WHERE pbt.id_ticket = t.id_ticket
                     AND (
                       pb.status_payment_backup = :approved
                       OR (
                         pb.status_payment_backup = :pending
                         AND (
                           pb.expires_at_payment_backup IS NULL
                           OR pb.expires_at_payment_backup > NOW()
                         )
                       )
                     )
               )'
        );
        $stmt->execute([
            ':available' => TicketStatus::AVAILABLE,
            ':reserved' => TicketStatus::RESERVED,
            ':approved' => \App\Domain\Payment\ValueObject\PaymentBackupStatus::APPROVED,
            ':pending' => \App\Domain\Payment\ValueObject\PaymentBackupStatus::PENDING,
        ]);

        return $stmt->rowCount();
    }

    public function findBySaleId(int $saleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_ticket, number_ticket, status_ticket FROM tickets WHERE id_sale_ticket = :s ORDER BY number_ticket'
        );
        $stmt->execute([':s' => $saleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cancelFromSale(int $saleId, array $ticketIds, int $adminId): int
    {
        if ($ticketIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "UPDATE tickets
                SET status_ticket = ?, id_customer_ticket = NULL, id_sale_ticket = NULL,
                    expires_at_ticket = NULL, date_updated_ticket = NOW()
                WHERE id_sale_ticket = ? AND id_ticket IN ({$placeholders}) AND status_ticket = ?";
        $params = array_merge([TicketStatus::AVAILABLE, $saleId], $ticketIds, [TicketStatus::PAID]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $released = $stmt->rowCount();

        if ($released > 0) {
            $cancelSql = "UPDATE sale_items SET status_item = 'cancelled', cancelled_at = NOW(), cancelled_by = ?
                          WHERE id_sale = ? AND id_ticket IN ({$placeholders}) AND status_item = 'active'";
            $cancelParams = array_merge([$adminId, $saleId], $ticketIds);
            $cancelStmt = $this->pdo->prepare($cancelSql);
            $cancelStmt->execute($cancelParams);
        }

        return $released;
    }
}
