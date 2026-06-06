<?php
declare(strict_types=1);

namespace App\Application\Raffle;

use App\Application\Audit\AuditService;
use App\Domain\Raffle\Repository\RaffleRepositoryInterface;
use App\Domain\Raffle\ValueObject\RaffleStatus;
use App\Domain\Raffle\ValueObject\RaffleType;
use App\Infrastructure\Database\PdoFactory;
use App\Shared\Exception\DomainException;
use PDO;

final class RaffleService
{
    private PDO $pdo;

    public function __construct(
        private readonly RaffleRepositoryInterface $raffles,
        private readonly AuditService $audit,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? PdoFactory::get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $adminId): int
    {
        $this->validateRaffleData($data);

        $digits = (int)$data['digits_raffle'];
        $type = (string)($data['type_raffle'] ?? RaffleType::AUTOMATIC);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO raffles (title_raffle, description_raffle, price_raffle, digits_raffle,
                 date_raffle, status_raffle, type_raffle, min_quantity_raffle,
                 reservation_minutes_raffle)
                 VALUES (:title, :desc, :price, :digits, :date, :status, :type, :min_qty, :res_min)'
            );
            $stmt->execute([
                ':title' => trim((string)$data['title_raffle']),
                ':desc' => trim((string)($data['description_raffle'] ?? '')),
                ':price' => (float)$data['price_raffle'],
                ':digits' => $digits,
                ':date' => $data['date_raffle'] ?? date('Y-m-d H:i:s'),
                ':status' => RaffleStatus::DRAFT,
                ':type' => $type,
                ':min_qty' => (int)($data['min_quantity_raffle'] ?? 1),
                ':res_min' => (int)($data['reservation_minutes_raffle'] ?? 15),
            ]);
            $raffleId = (int)$this->pdo->lastInsertId();

            $this->generateTickets($raffleId, $digits);

            $this->pdo->commit();

            $this->audit->record('raffle.created', 'raffle', $raffleId, null, $data, $adminId);

            return $raffleId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function pause(int $raffleId, int $adminId): void
    {
        $this->changeStatus($raffleId, RaffleStatus::PAUSED, $adminId, 'raffle.paused');
    }

    public function resume(int $raffleId, int $adminId): void
    {
        $this->changeStatus($raffleId, RaffleStatus::ACTIVE, $adminId, 'raffle.resumed');
    }

    public function finish(int $raffleId, int $adminId): void
    {
        $this->changeStatus($raffleId, RaffleStatus::FINISHED, $adminId, 'raffle.finished');
    }

    public function hide(int $raffleId, int $adminId): void
    {
        $old = $this->raffles->findById($raffleId);
        $stmt = $this->pdo->prepare('UPDATE raffles SET hidden_raffle = 1 WHERE id_raffle = :id');
        $stmt->execute([':id' => $raffleId]);
        $this->audit->record('raffle.hidden', 'raffle', $raffleId, $old, ['hidden' => true], $adminId);
    }

    public function blockSales(int $raffleId, int $adminId, bool $blocked): void
    {
        $old = $this->raffles->findById($raffleId);
        $stmt = $this->pdo->prepare('UPDATE raffles SET sales_blocked_raffle = :b WHERE id_raffle = :id');
        $stmt->execute([':b' => $blocked ? 1 : 0, ':id' => $raffleId]);
        $this->audit->record(
            $blocked ? 'raffle.sales_blocked' : 'raffle.sales_unblocked',
            'raffle',
            $raffleId,
            $old,
            ['sales_blocked' => $blocked],
            $adminId
        );
    }

    public function delete(int $raffleId, int $adminId): bool
    {
        $old = $this->raffles->findById($raffleId);
        if ($old === null) {
            throw new DomainException('Rifa no encontrada', 'RAFFLE_NOT_FOUND');
        }
        if (!$this->raffles->deleteIfNoSales($raffleId)) {
            throw new DomainException('No se puede eliminar una rifa con ventas', 'RAFFLE_HAS_SALES');
        }
        $this->audit->record('raffle.deleted', 'raffle', $raffleId, $old, null, $adminId);

        return true;
    }

    private function changeStatus(int $raffleId, int $status, int $adminId, string $action): void
    {
        $old = $this->raffles->findById($raffleId);
        if ($old === null) {
            throw new DomainException('Rifa no encontrada', 'RAFFLE_NOT_FOUND');
        }
        $stmt = $this->pdo->prepare('UPDATE raffles SET status_raffle = :s WHERE id_raffle = :id');
        $stmt->execute([':s' => $status, ':id' => $raffleId]);
        $this->audit->record($action, 'raffle', $raffleId, $old, ['status' => $status], $adminId);

        if (!class_exists('RaffleWebSync', false)) {
            require_once dirname(__DIR__, 3) . '/controllers/raffle_web_sync.php';
        }
        $preferred = $status === RaffleStatus::ACTIVE ? $raffleId : null;
        RaffleWebSync::sync($preferred);
    }

    private function generateTickets(int $raffleId, int $digits): void
    {
        if ($digits < 1 || $digits > 6) {
            throw new DomainException('Dígitos inválidos (1-6)', 'INVALID_DIGITS');
        }

        $total = 10 ** $digits;
        if ($total > 100000) {
            throw new DomainException('Demasiados nros para generar de una vez', 'TOO_MANY_TICKETS');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (number_ticket, status_ticket, id_raffle_ticket) VALUES (:num, 0, :raffle)'
        );

        for ($i = 0; $i < $total; $i++) {
            $stmt->execute([
                ':num' => str_pad((string)$i, $digits, '0', STR_PAD_LEFT),
                ':raffle' => $raffleId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateRaffleData(array $data): void
    {
        if (empty($data['title_raffle'])) {
            throw new DomainException('El título es obligatorio', 'VALIDATION');
        }
        if (!isset($data['digits_raffle']) || (int)$data['digits_raffle'] < 1) {
            throw new DomainException('Dígitos inválidos', 'VALIDATION');
        }
        $type = (string)($data['type_raffle'] ?? RaffleType::AUTOMATIC);
        if (!RaffleType::isValid($type)) {
            throw new DomainException('Tipo de rifa inválido', 'VALIDATION');
        }
    }
}
