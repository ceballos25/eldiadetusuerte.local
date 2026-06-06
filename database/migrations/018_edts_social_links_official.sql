-- Migration 018: Redes sociales oficiales (eldiadetusuerte.com) + evento COMBO EXTREMO coherente

SET NAMES utf8mb4;

START TRANSACTION;

-- Redes oficiales extraídas de eldiadetusuerte.com/front/ajax/settings.ajax.php
UPDATE settings SET value_setting = 'https://www.instagram.com/eldiadetusuertecol', date_updated_setting = NOW()
WHERE key_setting = 'social_instagram_url';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'social_instagram_url', 'https://www.instagram.com/eldiadetusuertecol', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'social_instagram_url');

UPDATE settings SET value_setting = 'https://www.facebook.com/profile.php?id=100092247236425', date_updated_setting = NOW()
WHERE key_setting = 'social_facebook_url';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'social_facebook_url', 'https://www.facebook.com/profile.php?id=100092247236425', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'social_facebook_url');

UPDATE settings SET value_setting = 'https://api.whatsapp.com/send/?phone=573171684127', date_updated_setting = NOW()
WHERE key_setting = 'whatsapp_chat_url';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'whatsapp_chat_url', 'https://api.whatsapp.com/send/?phone=573171684127', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'whatsapp_chat_url');

UPDATE settings SET value_setting = '573171684127', date_updated_setting = NOW()
WHERE key_setting = 'whatsapp';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'whatsapp', '573171684127', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'whatsapp');

UPDATE settings SET value_setting = 'https://chat.whatsapp.com/L8cLyUtv64GCdgb1GaxEm2?mode=gi_t', date_updated_setting = NOW()
WHERE key_setting = 'whatsapp_group_url';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'whatsapp_group_url', 'https://chat.whatsapp.com/L8cLyUtv64GCdgb1GaxEm2?mode=gi_t', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'whatsapp_group_url');

-- Evento activo coherente
UPDATE raffles SET
    title_raffle = 'COMBO EXTREMO',
    description_raffle = 'MT 15 · Número invertido XTZ 150 · 10 bendecidos de $500.000 · 10 de Julio — Medellín',
    price_raffle = 1200.00,
    min_quantity_raffle = 15,
    date_updated_raffle = NOW()
WHERE id_raffle = (SELECT CAST(value_setting AS UNSIGNED) FROM settings WHERE key_setting = 'web_id_raffle' LIMIT 1);

-- Precios oficiales (validados)
UPDATE settings SET value_setting = '1200', date_updated_setting = NOW() WHERE key_setting = 'pricing_first_unit';
UPDATE settings SET value_setting = '1200', date_updated_setting = NOW() WHERE key_setting = 'pricing_tier1_unit';
UPDATE settings SET value_setting = '1000', date_updated_setting = NOW() WHERE key_setting = 'pricing_tier2_unit';
UPDATE settings SET value_setting = '40', date_updated_setting = NOW() WHERE key_setting = 'pricing_bulk_threshold';
UPDATE settings SET value_setting = '1', date_updated_setting = NOW() WHERE key_setting = 'pricing_tiered_enabled';

COMMIT;
