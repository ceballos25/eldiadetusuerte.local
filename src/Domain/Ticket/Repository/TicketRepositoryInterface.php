<?php
declare(strict_types=1);

namespace App\Domain\Ticket\Repository;

interface TicketRepositoryInterface
{
    /**
     * @param list<int> $ticketIds
     * @return list<array<string, mixed>>
     */
    public function findByIdsForUpdate(int $raffleId, array $ticketIds): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAvailableRandom(int $raffleId, int $quantity): array;

    public function countAvailable(int $raffleId): int;

    /**
     * @param list<int> $ticketIds
     */
    public function reserve(int $raffleId, array $ticketIds, ?\DateTimeInterface $expiresAt): int;

    /**
     * @param list<int> $ticketIds
     */
    public function confirmPaid(int $raffleId, array $ticketIds, int $customerId, int $saleId): int;

    /**
     * @param list<int> $ticketIds
     */
    public function release(int $raffleId, array $ticketIds): int;

    public function releaseExpiredReservations(): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function findBySaleId(int $saleId): array;

    /**
     * @param list<int> $ticketIds
     */
    public function cancelFromSale(int $saleId, array $ticketIds, int $adminId): int;
}
