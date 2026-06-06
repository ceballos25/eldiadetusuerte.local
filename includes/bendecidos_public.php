<?php

declare(strict_types=1);

/**
 * Números "bendecidos" en la landing: clase `premiado` solo si en BD están
 * is_premium_ticket = 1 y status_ticket = 1 (vendido).
 *
 * Requiere config/config.php (clase Db) — se carga desde includes/landing/bootstrap.php.
 */
function edts_public_raffle_id(): int
{
    try {
        $row = Db::fetchOne(
            "SELECT value_setting FROM settings WHERE key_setting = 'web_id_raffle' LIMIT 1"
        );
        if ($row && (int)$row->value_setting > 0) {
            return (int)$row->value_setting;
        }

        $r = Db::fetchOne(
            'SELECT id_raffle FROM raffles WHERE status_raffle = 1 ORDER BY id_raffle DESC LIMIT 1'
        );
        if ($r) {
            return (int)$r->id_raffle;
        }

        $r2 = Db::fetchOne('SELECT id_raffle FROM raffles ORDER BY id_raffle DESC LIMIT 1');

        return $r2 ? (int)$r2->id_raffle : 0;
    } catch (Throwable) {
        return 0;
    }
}

/**
 * @return list<array{number: string, premiado_vendido: bool}> 
 */
function edts_bendecidos_cards(): array
{
    $idRifa = edts_public_raffle_id();
    if ($idRifa <= 0) {
        return [];
    }

    try {
        $rows = Db::fetchAll(
            'SELECT number_ticket, status_ticket, is_premium_ticket
             FROM tickets
             WHERE id_raffle_ticket = :r
               AND is_premium_ticket = 1
             ORDER BY CAST(number_ticket AS UNSIGNED) ASC',
            [':r' => $idRifa]
        );
    } catch (Throwable) {
        return [];
    }

    return array_map(
        static function ($row): array {
            $number = (string)($row->number_ticket ?? '');
            return [
                'number' => $number,
                'premiado_vendido' => (int)($row->status_ticket ?? 0) === 1
                    && (int)($row->is_premium_ticket ?? 0) === 1,
            ];
        },
        $rows
    );
}
