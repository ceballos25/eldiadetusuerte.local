<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Database\PdoFactory;
use PDO;

abstract class DatabaseTestCase extends IntegrationTestCase
{
    protected PDO $pdo;

    /** @var list<int> */
    private array $ticketIdsToCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = PdoFactory::get();
    }

    protected function tearDown(): void
    {
        if ($this->ticketIdsToCleanup !== []) {
            $placeholders = implode(',', array_fill(0, count($this->ticketIdsToCleanup), '?'));
            $stmt = $this->pdo->prepare(
                "DELETE FROM tickets WHERE id_ticket IN ({$placeholders})"
            );
            $stmt->execute($this->ticketIdsToCleanup);
        }
    }

    protected function findManualRaffleId(): ?int
    {
        $stmt = $this->pdo->query(
            "SELECT id_raffle FROM raffles WHERE type_raffle = 'manual' AND status_raffle = 1 ORDER BY id_raffle DESC LIMIT 1"
        );
        $id = $stmt?->fetchColumn();

        return $id !== false ? (int)$id : null;
    }

    protected function createAvailableTicket(int $raffleId, string $suffix): int
    {
        $number = '9' . str_pad(dechex(random_int(0, 0xFFFFF)), 5, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (id_raffle_ticket, number_ticket, status_ticket, date_created_ticket)
             VALUES (:r, :n, 0, NOW())'
        );
        $stmt->execute([':r' => $raffleId, ':n' => $number]);
        $id = (int)$this->pdo->lastInsertId();
        if ($id <= 0) {
            self::fail('No se pudo crear ticket de prueba (número: ' . $number . ')');
        }
        $this->ticketIdsToCleanup[] = $id;

        return $id;
    }

    protected function skipIfNoManualRaffle(): int
    {
        $id = $this->findManualRaffleId();
        if ($id === null) {
            self::markTestSkipped('No hay rifa manual activa en la BD de pruebas.');
        }

        return $id;
    }
}
