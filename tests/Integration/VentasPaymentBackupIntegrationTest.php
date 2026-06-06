<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;
use Tests\Support\IntegrationTestCase;

/**
 * Verifica que crearVenta no confirme tickets reservados por otro respaldo OpenPay.
 */
final class VentasPaymentBackupIntegrationTest extends DatabaseTestCase
{
    public function testCannotConfirmTicketsReservedByAnotherPaymentBackup(): void
    {
        $raffleId = $this->skipIfNoManualRaffle();
        $ticketId = $this->createAvailableTicket($raffleId, 'VB1');
        $customerId = $this->findAnyCustomerId();

        $backupA = $this->insertPendingPaymentBackup($raffleId, $customerId, [$ticketId], 'PB-A-');
        $backupB = $this->insertPendingPaymentBackup($raffleId, $customerId, [], 'PB-B-');

        $this->pdo->prepare(
            'UPDATE tickets SET status_ticket = 2, expires_at_ticket = NULL WHERE id_ticket = :id'
        )->execute([':id' => $ticketId]);

        $this->pdo->prepare(
            'DELETE FROM payment_backup_tickets WHERE id_payment_backup = :id'
        )->execute([':id' => $backupA]);
        $this->pdo->prepare(
            'INSERT INTO payment_backup_tickets (id_payment_backup, id_ticket) VALUES (:b, :t)'
        )->execute([':b' => $backupB, ':t' => $ticketId]);

        require_once dirname(__DIR__, 2) . '/controllers/ventas.controller.php';

        $result = \VentasController::crearVenta([
            'id_customer' => $customerId,
            'id_raffle' => $raffleId,
            'quantity_sale' => 1,
            'total_sale' => 1000,
            'code_sale' => 'PB-A-TEST',
            'payment_method_sale' => 'Página Web',
            'id_admin_sale' => 99,
            'id_payment_backup' => $backupA,
        ], [$ticketId], false);

        self::assertFalse($result['success'] ?? true);

        $this->cleanupBackup($backupA);
        $this->cleanupBackup($backupB);
        $this->pdo->prepare(
            'UPDATE tickets SET status_ticket = 0, id_customer_ticket = NULL, id_sale_ticket = NULL WHERE id_ticket = :id'
        )->execute([':id' => $ticketId]);
    }

    /**
     * @param list<int> $ticketIds
     */
    private function insertPendingPaymentBackup(int $raffleId, int $customerId, array $ticketIds, string $prefix): int
    {
        $code = $prefix . bin2hex(random_bytes(3));
        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_backups
             (code_payment_backup, id_raffle_payment_backup, id_customer_payment_backup,
              quantity_payment_backup, amount_payment_backup, status_payment_backup, date_created_payment_backup)
             VALUES (:code, :raffle, :customer, :qty, :amount, 1, NOW())'
        );
        $stmt->execute([
            ':code' => $code,
            ':raffle' => $raffleId,
            ':customer' => $customerId,
            ':qty' => max(1, count($ticketIds)),
            ':amount' => 1000,
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

    private function cleanupBackup(int $backupId): void
    {
        $this->pdo->prepare('DELETE FROM payment_backup_tickets WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
        $this->pdo->prepare('DELETE FROM payment_backups WHERE id_payment_backup = :id')
            ->execute([':id' => $backupId]);
    }

    private function findAnyCustomerId(): int
    {
        $id = $this->pdo->query('SELECT id_customer FROM customers ORDER BY id_customer ASC LIMIT 1')?->fetchColumn();
        if ($id === false) {
            self::markTestSkipped('No hay clientes en la BD de pruebas.');
        }

        return (int)$id;
    }
}
