<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Réplica de VentasController::montosEquivalentesCOP para prueba aislada.
 */
final class VentasAmountTest extends TestCase
{
    private static function montosEquivalentesCOP(float $a, float $b): bool
    {
        return abs((int) round($a) - (int) round($b)) <= 1;
    }

    public function testMontosEquivalentesExactMatch(): void
    {
        self::assertTrue(self::montosEquivalentesCOP(15000.0, 15000.0));
    }

    public function testMontosEquivalentesWithinOnePesoTolerance(): void
    {
        self::assertTrue(self::montosEquivalentesCOP(15000.4, 15001.0));
        self::assertTrue(self::montosEquivalentesCOP(14999.0, 15000.0));
    }

    public function testMontosNotEquivalentBeyondTolerance(): void
    {
        self::assertFalse(self::montosEquivalentesCOP(15000.0, 15002.0));
        self::assertFalse(self::montosEquivalentesCOP(10000.0, 20000.0));
    }
}
