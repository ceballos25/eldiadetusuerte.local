<?php
declare(strict_types=1);

namespace App\Domain\Raffle\ValueObject;

final class RaffleStatus
{
    public const DRAFT = 0;
    public const ACTIVE = 1;
    public const PAUSED = 2;
    public const FINISHED = 3;
    public const HIDDEN = 4;

    private const LABELS = [
        self::DRAFT => 'borrador',
        self::ACTIVE => 'activa',
        self::PAUSED => 'pausada',
        self::FINISHED => 'finalizada',
        self::HIDDEN => 'oculta',
    ];

    private function __construct()
    {
    }

    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'desconocido';
    }

    public static function allowsSales(int $status, bool $salesBlocked): bool
    {
        return $status === self::ACTIVE && !$salesBlocked;
    }
}
