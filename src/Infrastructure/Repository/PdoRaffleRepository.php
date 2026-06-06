<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Raffle\ValueObject\RaffleStatus;
use App\Infrastructure\Database\PdoFactory;
use PDO;

final class PdoRaffleRepository implements RaffleRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM raffles WHERE id_raffle = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM raffles
             WHERE status_raffle = ' . RaffleStatus::ACTIVE . '
               AND hidden_raffle = 0
             ORDER BY date_created_raffle DESC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function canAcceptSales(int $id): bool
    {
        $raffle = $this->findById($id);
        if ($raffle === null) {
            return false;
        }

        return RaffleStatus::allowsSales(
            (int)$raffle['status_raffle'],
            (bool)($raffle['sales_blocked_raffle'] ?? false)
        );
    }

    public function hasSales(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sales WHERE id_raffle_sale = :id');
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function deleteIfNoSales(int $id): bool
    {
        if ($this->hasSales($id)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM tickets WHERE id_raffle_ticket = :id')->execute([':id' => $id]);
            $this->pdo->prepare('DELETE FROM raffles WHERE id_raffle = :id')->execute([':id' => $id]);
            $this->pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
