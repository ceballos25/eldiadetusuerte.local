<?php
declare(strict_types=1);

namespace App\Application\Sale;

use App\Application\Audit\AuditService;
use App\Domain\Ticket\Repository\TicketRepositoryInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;

final class SaleCancellationService
{
    private PDO $pdo;

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly AuditService $audit,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * Cancel entire sale — all numbers return to available.
     */
    public function cancelTotal(int $saleId, int $adminId, ?string $notes = null): array
    {
        $sale = $this->findSale($saleId);
        if ($sale === null) {
            throw new DomainException('Venta no encontrada', 'SALE_NOT_FOUND');
        }

        $cancellationType = (string)($sale['cancellation_type_sale'] ?? 'none');
        if ($cancellationType === 'total' || (int)($sale['status_sale'] ?? 1) === 0) {
            throw new DomainException('Esta venta ya fue anulada totalmente', 'ALREADY_CANCELLED');
        }

        $tickets = $this->tickets->findBySaleId($saleId);
        $ticketIds = array_map(static fn (array $t) => (int)$t['id_ticket'], $tickets);

        $this->pdo->beginTransaction();
        try {
            $released = $ticketIds !== []
                ? $this->tickets->cancelFromSale($saleId, $ticketIds, $adminId)
                : 0;

            $stmt = $this->pdo->prepare(
                "UPDATE sales SET cancellation_type_sale = 'total', quantity_sale = 0, total_sale = 0,
                 cancelled_at_sale = NOW(), cancelled_by_sale = :admin, notes_sale = :notes, status_sale = 0
                 WHERE id_sale = :id"
            );
            $stmt->execute([':admin' => $adminId, ':notes' => $notes, ':id' => $saleId]);

            $this->pdo->commit();

            $this->audit->record('sale.cancelled.total', 'sale', $saleId, $sale, [
                'released_tickets' => $released,
                'admin_id' => $adminId,
                'notes' => $notes,
                'after_partial' => $cancellationType === 'partial',
            ]);

            return ['success' => true, 'released' => $released, 'sale_id' => $saleId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Partial cancellation — remove specific numbers from a sale.
     *
     * @param list<int> $ticketIds
     */
    public function cancelPartial(int $saleId, array $ticketIds, int $adminId, ?string $notes = null): array
    {
        $sale = $this->findSale($saleId);
        if ($sale === null) {
            throw new DomainException('Venta no encontrada', 'SALE_NOT_FOUND');
        }
        if (($sale['cancellation_type_sale'] ?? 'none') === 'total') {
            throw new DomainException('La venta ya fue anulada totalmente', 'ALREADY_CANCELLED');
        }

        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            throw new DomainException('Debe seleccionar al menos un nro', 'NO_TICKETS');
        }

        $saleTickets = $this->tickets->findBySaleId($saleId);
        $saleTicketIds = array_map(static fn (array $t) => (int)$t['id_ticket'], $saleTickets);

        foreach ($ticketIds as $tid) {
            if (!in_array($tid, $saleTicketIds, true)) {
                throw new DomainException('El nro no pertenece a esta venta', 'TICKET_NOT_IN_SALE');
            }
        }

        if (count($ticketIds) >= count($saleTicketIds)) {
            return $this->cancelTotal($saleId, $adminId, $notes);
        }

        $this->pdo->beginTransaction();
        try {
            $released = $this->tickets->cancelFromSale($saleId, $ticketIds, $adminId);

            $originalCount = count($saleTicketIds);
            $remaining = $originalCount - $released;
            $newTotal = self::recalculateTotalAfterPartialCancel(
                (float)($sale['total_sale'] ?? 0),
                $originalCount,
                $remaining
            );

            $stmt = $this->pdo->prepare(
                "UPDATE sales SET cancellation_type_sale = 'partial', quantity_sale = :qty,
                 total_sale = :total, cancelled_by_sale = :admin, notes_sale = :notes
                 WHERE id_sale = :id"
            );
            $stmt->execute([
                ':qty' => $remaining,
                ':total' => $newTotal,
                ':admin' => $adminId,
                ':notes' => $notes,
                ':id' => $saleId,
            ]);

            $this->pdo->commit();

            $this->audit->record('sale.cancelled.partial', 'sale', $saleId, $sale, [
                'released_tickets' => $released,
                'ticket_ids' => $ticketIds,
                'remaining' => $remaining,
                'previous_total' => (float)($sale['total_sale'] ?? 0),
                'new_total' => $newTotal,
                'admin_id' => $adminId,
            ]);

            return [
                'success' => true,
                'released' => $released,
                'remaining' => $remaining,
                'total_sale' => $newTotal,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function findSale(int $saleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE id_sale = :id LIMIT 1');
        $stmt->execute([':id' => $saleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Reparte el total pagado entre los números que quedan (mismo valor unitario efectivo).
     */
    private static function recalculateTotalAfterPartialCancel(
        float $originalTotal,
        int $originalCount,
        int $remainingCount
    ): float {
        if ($remainingCount <= 0 || $originalCount <= 0) {
            return 0.0;
        }

        if ($remainingCount >= $originalCount) {
            return max(0.0, $originalTotal);
        }

        return (float) (int) round($originalTotal * ($remainingCount / $originalCount));
    }
}
