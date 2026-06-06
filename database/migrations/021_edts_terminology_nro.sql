-- Migration 021: Reemplazar "número/números" por nro/nros en textos de BD

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE raffles SET
    description_raffle = REPLACE(
        REPLACE(description_raffle, 'número invertido', 'nro invertido'),
        'Número invertido', 'nro invertido'
    ),
    date_updated_raffle = NOW()
WHERE description_raffle LIKE '%número invertido%'
   OR description_raffle LIKE '%Número invertido%';

UPDATE raffles SET
    description_raffle = REPLACE(description_raffle, '4 cifras', '0000–9999'),
    date_updated_raffle = NOW()
WHERE description_raffle LIKE '%4 cifras%';

COMMIT;
