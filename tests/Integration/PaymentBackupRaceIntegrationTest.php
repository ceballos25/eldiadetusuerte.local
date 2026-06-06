<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Ticket\RaffleCheckoutAllocationService;
use App\Application\Ticket\TicketReservationService;
use App\Domain\Payment\ValueObject\PaymentBackupStatus;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoTicketRepository;
use PaymentBackupsController;
use Tests\Support\DatabaseTestCase;

/**
 * Reservas OpenPay con TTL y liberación por cron.
 */
final class PaymentBackupRaceIntegrationTest extends DatabaseTestCase
{
    public function testOpenPayReservationUsesRaffleTtl(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'OP1');

        $service = new RaffleCheckoutAllocationService(
            new TicketReservationService(
                new PdoTicketRepository($this->pdo),
                new PdoRaffleRepository($this->pdo),
                $this->pdo
            ),
            new PdoRaffleRepository($this->pdo),
            $this->pdo
        );

        $result = $service->reserveForPendingPayment($raffleId, 1, [$ticketId], false);

        self::assertNotNull($result['expires_at']);
        self::assertSame(TicketStatus::RESERVED, $this->ticketStatus($ticketId));

        (new PdoTicketRepository($this->pdo))->release($raffleId, [$ticketId]);
    }

    public function testReleaseExpiredSkipsTicketsLinkedToActivePendingBackup(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'OP2');
        $repo = new PdoTicketRepository($this->pdo);

        $repo->reserve($raffleId, [$ticketId], new \DateTimeImmutable('-10 minutes'));
        $backupId = $this->insertPendingPaymentBackup(
            $raffleId,
            [$ticketId],
            (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s')
        );

        self::assertSame(0, $repo->releaseExpiredReservations());
        self::assertSame(TicketStatus::RESERVED, $this->ticketStatus($ticketId));

        $this->pdo->prepare('DELETE FROM payment_backup_tickets WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
        $this->pdo->prepare('DELETE FROM payment_backups WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
        $repo->release($raffleId, [$ticketId]);
    }

    public function testReleaseExpiredStillFreesUnlinkedExpiredTickets(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'OP3');
        $repo = new PdoTicketRepository($this->pdo);

        $repo->reserve($raffleId, [$ticketId], new \DateTimeImmutable('-10 minutes'));

        self::assertSame(1, $repo->releaseExpiredReservations());
        self::assertSame(TicketStatus::AVAILABLE, $this->ticketStatus($ticketId));
    }

    public function testExpireStalePendingBackupReleasesTickets(): void
    {
        if (!class_exists(PaymentBackupsController::class)) {
            require_once dirname(__DIR__, 2) . '/controllers/paymentBackupsController.php';
        }

        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'OP4');
        $repo = new PdoTicketRepository($this->pdo);

        $repo->reserve($raffleId, [$ticketId], new \DateTimeImmutable('+5 minutes'));
        $backupId = $this->insertPendingPaymentBackup(
            $raffleId,
            [$ticketId],
            (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s')
        );

        self::assertGreaterThanOrEqual(1, PaymentBackupsController::expireStalePendingBackups());
        self::assertSame(TicketStatus::AVAILABLE, $this->ticketStatus($ticketId));

        $status = $this->pdo->prepare('SELECT status_payment_backup FROM payment_backups WHERE id_payment_backup = :id');
        $status->execute([':id' => $backupId]);
        self::assertSame(PaymentBackupStatus::EXPIRED, (int)$status->fetchColumn());

        $this->pdo->prepare('DELETE FROM payment_backup_tickets WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
        $this->pdo->prepare('DELETE FROM payment_backups WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
    }

    /**
     * @param list<int> $ticketIds
     */
    private function insertPendingPaymentBackup(int $raffleId, array $ticketIds, ?string $expiresAt = null): int
    {
        $customerId = $this->findAnyCustomerId();
        $code = 'PB-TEST-' . bin2hex(random_bytes(4));

        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_backups
             (code_payment_backup, id_raffle_payment_backup, id_customer_payment_backup,
              quantity_payment_backup, amount_payment_backup, status_payment_backup,
              expires_at_payment_backup, date_created_payment_backup)
             VALUES (:code, :raffle, :customer, :qty, :amount, 1, :expires, NOW())'
        );
        $stmt->execute([
            ':code' => $code,
            ':raffle' => $raffleId,
            ':customer' => $customerId,
            ':qty' => count($ticketIds),
            ':amount' => 1000,
            ':expires' => $expiresAt,
        ]);
        $backupId = (int)$this->pdo->lastInsertId();

        $link = $this->pdo->prepare(
            'INSERT INTO payment_backup_tickets (id_payment_backup, id_ticket) VALUES (:b, :t)'
        );
        foreach ($ticketIds as $ticketId) {
            $link->execute([':b' => $backupId, ':t' => $ticketId]);
        }

        return $backupId;
    }

    private function findAnyCustomerId(): int
    {
        $id = $this->pdo->query('SELECT id_customer FROM customers ORDER BY id_customer ASC LIMIT 1')?->fetchColumn();
        if ($id === false) {
            self::markTestSkipped('No hay clientes en la BD de pruebas.');
        }

        return (int)$id;
    }

    private function ticketStatus(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT status_ticket FROM tickets WHERE id_ticket = :id');
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
