<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Sales\Repository\SalesRepositoryInterface;
use VentasController;

final class LegacySalesRepository implements SalesRepositoryInterface
{
    public function createSale(array $payload): array
    {
        $ticketIds = $payload['ticket_ids'] ?? null;
        if (is_array($ticketIds) && $ticketIds !== []) {
            return VentasController::crearVenta($payload, array_values(array_map('intval', $ticketIds)));
        }

        return VentasController::crearVenta($payload);
    }

    public function createMixedSale(array $payload): array
    {
        return VentasController::crearVentaMixta($payload);
    }

    public function getSales(): array
    {
        return VentasController::obtenerVentas();
    }

    public function getSaleByCode(string $code): array
    {
        return VentasController::obtenerVentaPorCodigo($code);
    }
}
