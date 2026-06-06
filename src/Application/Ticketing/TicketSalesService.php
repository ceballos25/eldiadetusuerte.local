<?php
declare(strict_types=1);

namespace App\Application\Ticketing;

use App\Application\Sale\SaleCancellationService;
use App\Domain\Sales\Repository\SalesRepositoryInterface;
use InvalidArgumentException;
use VentasController;
use NumerosController;

final class TicketSalesService
{
    public function __construct(
        private readonly SalesRepositoryInterface $salesRepository,
        private readonly ?SaleCancellationService $cancellation = null
    ) {
    }

    public function execute(string $action, array $payload): array
    {
        return match ($action) {
            'obtener' => $this->salesRepository->getSales(),
            'obtener_rifas' => VentasController::listarRifas(),
            'crear_venta' => $this->salesRepository->createSale($payload),
            'crear_venta_mixta' => $this->salesRepository->createMixedSale($payload),
            'obtener_por_codigo' => $this->salesRepository->getSaleByCode($this->stringOrFail($payload, 'code_sale')),
            'obtener_disponibles' => VentasController::obtenerTicketsDisponibles((int)($payload['id_raffle'] ?? 0)),
            'detalle_venta' => VentasController::obtenerDetalleVenta((int)($payload['id_sale'] ?? 0)),
            'obtener_por_celular' => VentasController::buscarTicketsPorCelular($this->stringOrFail($payload, 'phone_customer')),
            'numeros_vendidos' => NumerosController::obtenerNumerosVendidos(),
            'obtener_admins' => VentasController::obtenerAdmins(),
            'anular' => $this->anularTotal($payload),
            'anular_parcial' => $this->anularParcial($payload),
            'obtener_origenes' => VentasController::obtenerOrigenesUnicos(),
            default => throw new InvalidArgumentException('Accion no valida'),
        };
    }

    private function anularTotal(array $payload): array
    {
        $id = (int)($payload['id_sale'] ?? 0);
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($this->cancellation !== null && $adminId > 0) {
            return $this->cancellation->cancelTotal($id, $adminId, $notes !== '' ? $notes : null);
        }

        return ['success' => false, 'message' => 'Servicio de anulación no disponible o sesión inválida'];
    }

    private function anularParcial(array $payload): array
    {
        if ($this->cancellation === null) {
            return ['success' => false, 'message' => 'Servicio de anulación no disponible'];
        }

        $ticketIds = $payload['ticket_ids'] ?? [];
        if (is_string($ticketIds)) {
            $ticketIds = array_filter(array_map('intval', explode(',', $ticketIds)));
        }

        return $this->cancellation->cancelPartial(
            (int)($payload['id_sale'] ?? 0),
            is_array($ticketIds) ? $ticketIds : [],
            (int)($_SESSION['user_id'] ?? 0),
            trim((string)($payload['notes'] ?? '')) ?: null
        );
    }

    private function stringOrFail(array $payload, string $key): string
    {
        $value = trim((string)($payload[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException("Parametro requerido: {$key}");
        }

        return $value;
    }
}
