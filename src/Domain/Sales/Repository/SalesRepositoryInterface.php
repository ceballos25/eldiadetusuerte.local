<?php
declare(strict_types=1);

namespace App\Domain\Sales\Repository;

interface SalesRepositoryInterface
{
    public function createSale(array $payload): array;

    public function createMixedSale(array $payload): array;

    public function getSales(): array;

    public function getSaleByCode(string $code): array;
}
