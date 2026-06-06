<?php
declare(strict_types=1);

namespace App\Application\Reservation;

use App\Application\Ticket\RaffleCheckoutAllocationService;
use App\Infrastructure\Database\PdoFactory;
use PDO;

/**
 * Listado unificado de reservas pendientes (respaldos PSE + transferencias).
 */
final class PendingReservationService
{
    private PDO $pdo;

    public function __construct(
        private readonly RaffleCheckoutAllocationService $allocation,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @return array{success: true, data: list<array<string, mixed>>, total: int, pending_total: int}
     */
    public function listPending(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 15)));
        $search = trim((string)($params['search'] ?? ''));
        $source = trim((string)($params['source'] ?? ''));

        $rows = $this->fetchAllPending($search, $source);
        $total = count($rows);
        $offset = ($page - 1) * $limit;
        $pageRows = array_slice($rows, $offset, $limit);

        return [
            'success' => true,
            'data' => $pageRows,
            'total' => $total,
            'pending_total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllPending(string $search, string $sourceFilter): array
    {
        $items = [];

        if ($sourceFilter === '' || $sourceFilter === 'pse') {
            $items = array_merge($items, $this->fetchPaymentBackupsPending($search));
        }
        if ($sourceFilter === '' || $sourceFilter === 'transferencia') {
            $items = array_merge($items, $this->fetchTransfersPending($search));
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($b['date_created'] ?? ''), (string)($a['date_created'] ?? ''));
        });

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPaymentBackupsPending(string $search): array
    {
        $where = ['pb.status_payment_backup = 1'];
        $bind = [];

        if ($search !== '') {
            $where[] = '(pb.code_payment_backup LIKE :q OR c.name_customer LIKE :q OR c.lastname_customer LIKE :q
                OR c.phone_customer LIKE :q OR c.email_customer LIKE :q)';
            $bind[':q'] = '%' . $search . '%';
        }

        $sql = 'SELECT pb.id_payment_backup AS id, pb.code_payment_backup AS code,
            pb.quantity_payment_backup AS quantity, pb.amount_payment_backup AS amount,
            pb.date_created_payment_backup AS date_created, pb.expires_at_payment_backup AS expires_at,
            pb.source_payment_backup AS source, pb.id_raffle_payment_backup AS id_raffle,
            pb.ticket_ids_payment_backup AS ticket_ids_json,
            c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer,
            r.title_raffle, r.type_raffle
            FROM payment_backups pb
            INNER JOIN customers c ON c.id_customer = pb.id_customer_payment_backup
            INNER JOIN raffles r ON r.id_raffle = pb.id_raffle_payment_backup
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY pb.id_payment_backup DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $ticketIds = $this->ticketIdsForPaymentBackup((int)$row['id'], $row['ticket_ids_json'] ?? null);
            $out[] = $this->mapRow($row, 'pse', $ticketIds);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTransfersPending(string $search): array
    {
        $where = ['t.status_transfer = 1'];
        $bind = [];

        if ($search !== '') {
            $where[] = '(t.code_transfer LIKE :q OR c.name_customer LIKE :q OR c.lastname_customer LIKE :q
                OR c.phone_customer LIKE :q OR c.email_customer LIKE :q)';
            $bind[':q'] = '%' . $search . '%';
        }

        $sql = 'SELECT t.id_transfer AS id, t.code_transfer AS code,
            t.quantity_transfer AS quantity, t.amount_transfer AS amount,
            t.date_created_transfer AS date_created, t.expires_at_transfer AS expires_at,
            t.source_transfer AS source, t.id_raffle_transfer AS id_raffle,
            t.ticket_ids_transfer AS ticket_ids_json, t.url_transfer,
            c.name_customer, c.lastname_customer, c.phone_customer, c.email_customer,
            r.title_raffle, r.type_raffle
            FROM transfers t
            INNER JOIN customers c ON c.id_customer = t.id_customer_transfer
            INNER JOIN raffles r ON r.id_raffle = t.id_raffle_transfer
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY t.id_transfer DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $ticketIds = RaffleCheckoutAllocationService::decodeTicketIds($row['ticket_ids_json'] ?? null);
            $out[] = $this->mapRow($row, 'transferencia', $ticketIds);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int> $ticketIds
     * @return array<string, mixed>
     */
    private function mapRow(array $row, string $sourceKind, array $ticketIds): array
    {
        $idRaffle = (int)($row['id_raffle'] ?? 0);
        $numbers = $ticketIds !== []
            ? $this->allocation->numbersForTicketIds($idRaffle, $ticketIds)
            : [];

        $typeRaffle = (string)($row['type_raffle'] ?? 'automatic');

        return [
            'id' => (int)$row['id'],
            'source_kind' => $sourceKind,
            'code' => (string)$row['code'],
            'quantity' => (int)$row['quantity'],
            'amount' => (float)$row['amount'],
            'date_created' => (string)$row['date_created'],
            'expires_at' => $row['expires_at'] ?? null,
            'source' => $row['source'] ?? null,
            'id_raffle' => $idRaffle,
            'title_raffle' => (string)($row['title_raffle'] ?? ''),
            'type_raffle' => $typeRaffle,
            'name_customer' => (string)($row['name_customer'] ?? ''),
            'lastname_customer' => (string)($row['lastname_customer'] ?? ''),
            'phone_customer' => (string)($row['phone_customer'] ?? ''),
            'email_customer' => (string)($row['email_customer'] ?? ''),
            'ticket_ids' => $ticketIds,
            'reserved_numbers' => $numbers,
            'reserved_numbers_label' => $numbers !== []
                ? implode(', ', $numbers)
                : ($typeRaffle === 'manual' ? '—' : 'Automático al aprobar'),
            'url_proof' => $row['url_transfer'] ?? null,
        ];
    }

    /**
     * @return list<int>
     */
    private function ticketIdsForPaymentBackup(int $idBackup, mixed $jsonFallback): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_ticket FROM payment_backup_tickets WHERE id_payment_backup = :id ORDER BY id_ticket'
        );
        $stmt->execute([':id' => $idBackup]);
        $ids = array_map(static fn (array $r): int => (int)$r['id_ticket'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        if ($ids !== []) {
            return $ids;
        }

        return RaffleCheckoutAllocationService::decodeTicketIds($jsonFallback);
    }
}
