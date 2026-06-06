<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Raffle\ValueObject\RaffleType;

final class InMemoryRaffleRepository implements RaffleRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $raffles */
    public function __construct(private array $raffles = [])
    {
    }

    public function findById(int $id): ?array
    {
        return $this->raffles[$id] ?? null;
    }

    public function findAllActive(): array
    {
        return array_values($this->raffles);
    }

    public function canAcceptSales(int $id): bool
    {
        $raffle = $this->findById($id);
        if ($raffle === null) {
            return false;
        }

        return (int)($raffle['status_raffle'] ?? 1) === 1;
    }

    public function hasSales(int $id): bool
    {
        return false;
    }

    public function deleteIfNoSales(int $id): bool
    {
        unset($this->raffles[$id]);

        return true;
    }

    public static function manual(int $id, int $reservationMinutes = 15, int $minQty = 1): self
    {
        return new self([
            $id => [
                'id_raffle' => $id,
                'type_raffle' => RaffleType::MANUAL,
                'status_raffle' => 1,
                'reservation_minutes_raffle' => $reservationMinutes,
                'min_quantity_raffle' => $minQty,
            ],
        ]);
    }

    public static function automatic(int $id): self
    {
        return new self([
            $id => [
                'id_raffle' => $id,
                'type_raffle' => RaffleType::AUTOMATIC,
                'status_raffle' => 1,
                'min_quantity_raffle' => 1,
            ],
        ]);
    }
}
