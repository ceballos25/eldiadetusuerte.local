<?php
declare(strict_types=1);

namespace App\Domain\Ticket\ValueObject;

final class TicketStatus
{
    public const AVAILABLE = 0;
    public const PAID = 1;
    public const RESERVED = 2;
    public const CANCELLED = 3;

    private const LABELS = [
        self::AVAILABLE => 'disponible',
        self::RESERVED => 'reservado',
        self::PAID => 'pagado',
        self::CANCELLED => 'anulado',
    ];

    private function __construct()
    {
    }

    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'desconocido';
    }

    public static function isAvailable(int $status): bool
    {
        return $status === self::AVAILABLE;
    }

    public static function isReserved(int $status): bool
    {
        return $status === self::RESERVED;
    }

    public static function isPaid(int $status): bool
    {
        return $status === self::PAID;
    }

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        return [self::AVAILABLE, self::RESERVED, self::PAID, self::CANCELLED];
    }
}
