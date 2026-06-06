<?php
declare(strict_types=1);

namespace App\Domain\Raffle\Repository;

interface RaffleRepositoryInterface
{
    public function findById(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllActive(): array;

    public function canAcceptSales(int $id): bool;

    public function hasSales(int $id): bool;

    public function deleteIfNoSales(int $id): bool;
}
