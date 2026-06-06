<?php

declare(strict_types=1);

/**
 * Mantiene web_id_raffle alineado con la rifa activa que debe verse en la landing.
 */
final class RaffleWebSync
{
    private const SETTING_KEY = 'web_id_raffle';

    public static function sync(?int $preferredId = null): void
    {
        try {
            $targetId = self::resolveTargetId($preferredId);
            if ($targetId <= 0) {
                return;
            }

            $row = Db::fetchOne(
                'SELECT id_setting FROM settings WHERE key_setting = :k LIMIT 1',
                [':k' => self::SETTING_KEY]
            );

            $now = date('Y-m-d H:i:s');
            if ($row) {
                Db::update(
                    'settings',
                    [
                        'value_setting' => (string)$targetId,
                        'date_updated_setting' => $now,
                    ],
                    'key_setting = :k',
                    [':k' => self::SETTING_KEY]
                );
            } else {
                Db::insert('settings', [
                    'key_setting' => self::SETTING_KEY,
                    'value_setting' => (string)$targetId,
                    'date_created_setting' => date('Y-m-d'),
                    'date_updated_setting' => $now,
                ]);
            }

            if (class_exists(\App\Shared\Config\DynamicConfig::class, false)) {
                (new \App\Shared\Config\DynamicConfig())->flush();
            }
        } catch (Throwable) {
            // No bloquear guardado de rifa por fallo de settings
        }
    }

    private static function resolveTargetId(?int $preferredId): int
    {
        if ($preferredId !== null && $preferredId > 0 && self::isPublicActive($preferredId)) {
            return $preferredId;
        }

        $row = Db::fetchOne(
            'SELECT id_raffle FROM raffles
             WHERE status_raffle = 1 AND hidden_raffle = 0
             ORDER BY date_created_raffle DESC
             LIMIT 1'
        );

        return $row ? (int)$row['id_raffle'] : 0;
    }

    private static function isPublicActive(int $id): bool
    {
        $row = Db::fetchOne(
            'SELECT id_raffle FROM raffles
             WHERE id_raffle = :id AND status_raffle = 1 AND hidden_raffle = 0
             LIMIT 1',
            [':id' => $id]
        );

        return $row !== null;
    }
}
