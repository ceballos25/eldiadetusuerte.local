<?php
declare(strict_types=1);

namespace App\Domain\Raffle\ValueObject;

final class RaffleType
{
    public const MANUAL = 'manual';
    public const AUTOMATIC = 'automatic';

    private function __construct()
    {
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, [self::MANUAL, self::AUTOMATIC], true);
    }
}
