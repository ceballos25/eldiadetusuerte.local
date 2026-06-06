<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Pricing\RaffleQuantityPricing;
use PHPUnit\Framework\TestCase;

final class RaffleQuantityPricingTest extends TestCase
{
    private function bulkPricing(): RaffleQuantityPricing
    {
        return RaffleQuantityPricing::forTest(true, 1200, 1200, 1000, 40);
    }

    public function testFifteenNumbersCost18000(): void
    {
        $result = $this->bulkPricing()->calculate(15);

        self::assertSame(18000, $result['total']);
        self::assertFalse($result['promo_active']);
    }

    public function testFortyNumbersCost40000WithBulkDiscount(): void
    {
        $result = $this->bulkPricing()->calculate(40);

        self::assertSame(40000, $result['total']);
        self::assertTrue($result['promo_active']);
        self::assertSame(40, $result['third_plus_count']);
    }

    public function testTwoHundredFiftyNumbersCost250000(): void
    {
        $result = $this->bulkPricing()->calculate(250);

        self::assertSame(250000, $result['total']);
        self::assertTrue($result['promo_active']);
    }

    public function testLegacyTwoNumbersCost120000At60000Each(): void
    {
        $pricing = RaffleQuantityPricing::forTest(true, 65000, 60000, 55000);
        $result = $pricing->calculate(2);

        self::assertSame(120000, $result['total']);
        self::assertTrue($result['promo_active']);
    }

    public function testDisabledUsesFallbackUnit(): void
    {
        $pricing = RaffleQuantityPricing::forTest(false, 1200, 1200, 1000, 40);
        $result = $pricing->calculate(3, 50000.0);

        self::assertSame(150000, $result['total']);
        self::assertFalse($result['promo_active']);
    }
}
