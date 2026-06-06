-- Migration 020: Copy oficial COMBO EXTREMO (sin términos restringidos)

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE raffles SET
    title_raffle = 'COMBO EXTREMO',
    description_raffle = 'MT 15 mayor · XTZ 150 nro invertido · 10 bendecidos $500.000 · Juega 10 Jul Medellín · 0000–9999 · min 15 · $1.200 · desde 40 a $1.000',
    price_raffle = 1200.00,
    min_quantity_raffle = 15,
    digits_raffle = 4,
    date_raffle = '2026-07-10 20:00:00',
    date_updated_raffle = NOW()
WHERE id_raffle = 2;

COMMIT;
