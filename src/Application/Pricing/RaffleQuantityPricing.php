<?php
declare(strict_types=1);

namespace App\Application\Pricing;

use App\Shared\Config\DynamicConfig;

/**
 * Precios por cantidad.
 * Modo bulk (pricing_bulk_threshold > 0): tarifa estándar hasta el umbral; desde ahí tarifa reducida c/u.
 * Modo legacy: 1 → first, 2 → second c/u, 3+ → third c/u.
 */
final class RaffleQuantityPricing
{
    public const SETTING_ENABLED = 'pricing_tiered_enabled';
    public const SETTING_FIRST_UNIT = 'pricing_first_unit';
    public const SETTING_SECOND_UNIT = 'pricing_tier1_unit';
    public const SETTING_THIRD_PLUS_UNIT = 'pricing_tier2_unit';
    public const SETTING_BULK_THRESHOLD = 'pricing_bulk_threshold';

    /** @deprecated Solo compatibilidad en respuestas públicas */
    public const SETTING_TIER1_QTY = 'pricing_tier1_qty';

    private function __construct(
        private readonly bool $enabled,
        private readonly int $firstUnit,
        private readonly int $secondUnit,
        private readonly int $thirdPlusUnit,
        private readonly int $bulkThreshold
    ) {
    }

    public static function fromConfig(?DynamicConfig $config = null): self
    {
        $config ??= new DynamicConfig();

        $enabled = self::isTruthy($config->get(self::SETTING_ENABLED, '1'));
        $firstUnit = max(0, (int)round((float)$config->get(self::SETTING_FIRST_UNIT, '1200')));
        $secondUnit = max(0, (int)round((float)$config->get(self::SETTING_SECOND_UNIT, '1200')));
        $thirdPlusUnit = max(0, (int)round((float)$config->get(self::SETTING_THIRD_PLUS_UNIT, '1000')));
        $bulkThreshold = max(0, (int)$config->get(self::SETTING_BULK_THRESHOLD, '0'));

        return new self($enabled, $firstUnit, $secondUnit, $thirdPlusUnit, $bulkThreshold);
    }

    /** @internal tests */
    public static function forTest(
        bool $enabled,
        int $firstUnit,
        int $secondUnit,
        int $thirdPlusUnit,
        int $bulkThreshold = 0
    ): self {
        return new self(
            $enabled,
            max(0, $firstUnit),
            max(0, $secondUnit),
            max(0, $thirdPlusUnit),
            max(0, $bulkThreshold)
        );
    }

    /**
     * @return array{
     *   enabled: bool,
     *   first_unit: int,
     *   second_unit: int,
     *   third_plus_unit: int,
     *   bulk_threshold: int,
     *   tier1_qty: int,
     *   tier1_unit: int,
     *   tier2_unit: int,
     *   label_first: string,
     *   label_second: string,
     *   label_third_plus: string
     * }
     */
    public function toPublicArray(): array
    {
        if ($this->bulkThreshold > 0) {
            return [
                'enabled' => $this->enabled,
                'first_unit' => $this->firstUnit,
                'second_unit' => $this->firstUnit,
                'third_plus_unit' => $this->thirdPlusUnit,
                'bulk_threshold' => $this->bulkThreshold,
                'tier1_qty' => $this->bulkThreshold,
                'tier1_unit' => $this->firstUnit,
                'tier2_unit' => $this->thirdPlusUnit,
                'label_first' => '15–39 nros',
                'label_second' => 'Desde ' . $this->bulkThreshold . ' nros',
                'label_third_plus' => $this->thirdPlusUnit . ' c/u',
            ];
        }

        return [
            'enabled' => $this->enabled,
            'first_unit' => $this->firstUnit,
            'second_unit' => $this->secondUnit,
            'third_plus_unit' => $this->thirdPlusUnit,
            'bulk_threshold' => 0,
            'tier1_qty' => 2,
            'tier1_unit' => $this->secondUnit,
            'tier2_unit' => $this->thirdPlusUnit,
            'label_first' => '1 nro',
            'label_second' => '2 nros',
            'label_third_plus' => '3 o más',
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   first_count: int,
     *   second_count: int,
     *   third_plus_count: int,
     *   tier1_count: int,
     *   tier2_count: int,
     *   promo_active: bool,
     *   first_unit: int,
     *   second_unit: int,
     *   third_plus_unit: int,
     *   tier1_unit: int,
     *   tier2_unit: int,
     *   enabled: bool,
     *   bulk_threshold?: int
     * }
     */
    public function calculate(int $quantity, float $fallbackUnitPrice = 0): array
    {
        if ($quantity <= 0) {
            return $this->emptyBreakdown();
        }

        if (!$this->enabled) {
            $unit = (int)round($fallbackUnitPrice);
            if ($unit <= 0) {
                return $this->emptyBreakdown(false);
            }

            return [
                'total' => $quantity * $unit,
                'first_count' => $quantity,
                'second_count' => 0,
                'third_plus_count' => 0,
                'pair_count' => 0,
                'tier1_count' => $quantity,
                'tier2_count' => 0,
                'promo_active' => false,
                'pair_promo_active' => false,
                'first_unit' => $unit,
                'second_unit' => $unit,
                'third_plus_unit' => $unit,
                'tier1_unit' => $unit,
                'tier2_unit' => $unit,
                'enabled' => false,
            ];
        }

        if ($this->bulkThreshold > 0) {
            $unit = $quantity >= $this->bulkThreshold ? $this->thirdPlusUnit : $this->firstUnit;
            $promo = $quantity >= $this->bulkThreshold;

            return [
                'total' => $quantity * $unit,
                'first_count' => $promo ? 0 : $quantity,
                'second_count' => 0,
                'third_plus_count' => $promo ? $quantity : 0,
                'pair_count' => 0,
                'tier1_count' => $promo ? 0 : $quantity,
                'tier2_count' => $promo ? $quantity : 0,
                'promo_active' => $promo,
                'pair_promo_active' => false,
                'first_unit' => $this->firstUnit,
                'second_unit' => $this->firstUnit,
                'third_plus_unit' => $this->thirdPlusUnit,
                'tier1_unit' => $this->firstUnit,
                'tier2_unit' => $this->thirdPlusUnit,
                'enabled' => true,
                'bulk_threshold' => $this->bulkThreshold,
            ];
        }

        if ($quantity === 1) {
            return [
                'total' => $this->firstUnit,
                'first_count' => 1,
                'second_count' => 0,
                'third_plus_count' => 0,
                'pair_count' => 0,
                'tier1_count' => 1,
                'tier2_count' => 0,
                'promo_active' => false,
                'pair_promo_active' => false,
                'first_unit' => $this->firstUnit,
                'second_unit' => $this->secondUnit,
                'third_plus_unit' => $this->thirdPlusUnit,
                'tier1_unit' => $this->secondUnit,
                'tier2_unit' => $this->thirdPlusUnit,
                'enabled' => true,
            ];
        }

        if ($quantity === 2) {
            return [
                'total' => $this->secondUnit * 2,
                'first_count' => 0,
                'second_count' => 2,
                'third_plus_count'  => 0,
                'pair_count' => 2,
                'tier1_count' => 2,
                'tier2_count' => 0,
                'promo_active' => true,
                'pair_promo_active' => true,
                'first_unit' => $this->firstUnit,
                'second_unit' => $this->secondUnit,
                'third_plus_unit' => $this->thirdPlusUnit,
                'tier1_unit' => $this->secondUnit,
                'tier2_unit' => $this->thirdPlusUnit,
                'enabled' => true,
            ];
        }

        $total = $quantity * $this->thirdPlusUnit;

        return [
            'total' => $total,
            'first_count' => 0,
            'second_count' => 0,
            'third_plus_count' => $quantity,
            'pair_count' => 0,
            'tier1_count' => 0,
            'tier2_count' => $quantity,
            'promo_active' => true,
            'pair_promo_active' => false,
            'first_unit' => $this->firstUnit,
            'second_unit' => $this->secondUnit,
            'third_plus_unit' => $this->thirdPlusUnit,
            'tier1_unit' => $this->secondUnit,
            'tier2_unit' => $this->thirdPlusUnit,
            'enabled' => true,
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   first_count: int,
     *   second_count: int,
     *   third_plus_count: int,
     *   tier1_count: int,
     *   tier2_count: int,
     *   promo_active: bool,
     *   first_unit: int,
     *   second_unit: int,
     *   third_plus_unit: int,
     *   tier1_unit: int,
     *   tier2_unit: int,
     *   enabled: bool
     * }
     */
    private function emptyBreakdown(bool $enabled = true): array
    {
        return [
            'total' => 0,
            'first_count' => 0,
            'second_count' => 0,
            'third_plus_count' => 0,
            'tier1_count' => 0,
            'tier2_count' => 0,
            'promo_active' => false,
            'first_unit' => 0,
            'second_unit' => 0,
            'third_plus_unit' => 0,
            'tier1_unit' => 0,
            'tier2_unit' => 0,
            'enabled' => $enabled,
        ];
    }

    private static function isTruthy(mixed $value): bool
    {
        $v = strtolower(trim((string)$value));

        return !in_array($v, ['0', 'false', 'no', 'off', ''], true);
    }
}
