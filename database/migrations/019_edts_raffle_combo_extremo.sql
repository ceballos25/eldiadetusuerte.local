-- Migration 019: Asegurar rifa activa COMBO EXTREMO coherente

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE raffles SET
    title_raffle = 'COMBO EXTREMO',
    description_raffle = 'MT 15 · Número invertido XTZ 150 · 10 bendecidos de $500.000 · 10 de Julio — Medellín',
    price_raffle = 1200.00,
    min_quantity_raffle = 15,
    digits_raffle = 4,
    date_raffle = '2026-07-10 20:00:00',
    status_raffle = 1,
    type_raffle = 'automatic',
    sales_blocked_raffle = 0,
    hidden_raffle = 0,
    reservation_minutes_raffle = 15,
    date_updated_raffle = NOW()
WHERE id_raffle = 2;

UPDATE settings SET value_setting = '2', date_updated_setting = NOW()
WHERE key_setting = 'web_id_raffle';

UPDATE settings SET value_setting = '1200', date_updated_setting = NOW() WHERE key_setting = 'pricing_first_unit';
UPDATE settings SET value_setting = '1200', date_updated_setting = NOW() WHERE key_setting = 'pricing_tier1_unit';
UPDATE settings SET value_setting = '1000', date_updated_setting = NOW() WHERE key_setting = 'pricing_tier2_unit';
UPDATE settings SET value_setting = '40', date_updated_setting = NOW() WHERE key_setting = 'pricing_bulk_threshold';

COMMIT;
