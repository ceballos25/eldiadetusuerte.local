-- Migration 017: Rebrand El Día de Tu Suerte + evento COMBO EXTREMO
-- No elimina datos históricos; oculta evento anterior y activa el nuevo.

SET NAMES utf8mb4;

START TRANSACTION;

-- ── Branding: imágenes CDN EDTS ──────────────────────────────────────────────
INSERT INTO site_images (key_image, url_image, fallback_url) VALUES
('logo', 'https://cdn.eldiadetusuerte.com/images/logos/logo.jpg', NULL),
('logo_white', 'https://cdn.eldiadetusuerte.com/images/logos/logo-blanco.jpg', NULL),
('favicon', 'https://cdn.eldiadetusuerte.com/images/logos/logo.ico', NULL),
('pse', 'https://cdn.eldiadetusuerte.com/images/logos/pse.png', NULL),
('hero_banner', 'https://cdn.eldiadetusuerte.com/images/principal/combo-extremo-mt15-1.jpg', NULL),
('hero_mobile', 'https://cdn.eldiadetusuerte.com/images/principal/combo-extremo-mt15-1.jpg', NULL)
ON DUPLICATE KEY UPDATE
    url_image = VALUES(url_image),
    fallback_url = VALUES(fallback_url);

-- ── Precios COMBO EXTREMO ────────────────────────────────────────────────────
-- Estándar $1.200 c/u (15–39 nums) · Desde 40 nums $1.000 c/u
INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tiered_enabled', '1', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tiered_enabled');

UPDATE settings SET value_setting = '1', date_updated_setting = NOW()
WHERE key_setting = 'pricing_tiered_enabled';

UPDATE settings SET value_setting = '1200', date_updated_setting = NOW()
WHERE key_setting = 'pricing_first_unit';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_first_unit', '1200', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_first_unit');

UPDATE settings SET value_setting = '1200', date_updated_setting = NOW()
WHERE key_setting = 'pricing_tier1_unit';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tier1_unit', '1200', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tier1_unit');

UPDATE settings SET value_setting = '1000', date_updated_setting = NOW()
WHERE key_setting = 'pricing_tier2_unit';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tier2_unit', '1000', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tier2_unit');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_bulk_threshold', '40', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_bulk_threshold');

UPDATE settings SET value_setting = '40', date_updated_setting = NOW()
WHERE key_setting = 'pricing_bulk_threshold';

-- ── Contacto y redes EDTS ────────────────────────────────────────────────────
INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'nombre_rifa', 'El Día de Tu Suerte', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'nombre_rifa');

UPDATE settings SET value_setting = 'El Día de Tu Suerte', date_updated_setting = NOW()
WHERE key_setting = 'nombre_rifa';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'whatsapp', '573001234567', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'whatsapp');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'whatsapp_chat_url', 'https://api.whatsapp.com/send/?phone=573001234567', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'whatsapp_chat_url');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'social_instagram_url', 'https://www.instagram.com/eldiadetusuerte', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'social_instagram_url');

UPDATE settings SET value_setting = 'https://www.instagram.com/eldiadetusuerte', date_updated_setting = NOW()
WHERE key_setting = 'social_instagram_url';

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'social_facebook_url', 'https://www.facebook.com/eldiadetusuerte', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'social_facebook_url');

UPDATE settings SET value_setting = 'https://www.facebook.com/eldiadetusuerte', date_updated_setting = NOW()
WHERE key_setting = 'social_facebook_url';

-- ── Ocultar evento anterior (conservar historial) ────────────────────────────
UPDATE raffles SET hidden_raffle = 1, sales_blocked_raffle = 1, date_updated_raffle = NOW()
WHERE id_raffle = 1;

-- ── Nuevo evento: COMBO EXTREMO ──────────────────────────────────────────────
INSERT INTO raffles (
    title_raffle,
    description_raffle,
    price_raffle,
    digits_raffle,
    date_raffle,
    status_raffle,
    type_raffle,
    min_quantity_raffle,
    sales_blocked_raffle,
    hidden_raffle,
    reservation_minutes_raffle,
    date_created_raffle,
    date_updated_raffle
)
SELECT
    'COMBO EXTREMO',
    'Yamaha MT 15 · Número invertido Yamaha XTZ 150 · 10 ganadores de $500.000 · Juega 10 de Julio · Medellín',
    1200.00,
    4,
    '2026-07-10 20:00:00',
    1,
    'automatic',
    15,
    0,
    0,
    15,
    CURDATE(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM raffles WHERE title_raffle LIKE '%COMBO EXTREMO%' AND status_raffle = 1
);

SET @new_raffle_id = (
    SELECT id_raffle FROM raffles
    WHERE title_raffle LIKE '%COMBO EXTREMO%' AND status_raffle = 1
    ORDER BY id_raffle DESC LIMIT 1
);

-- Activar nuevo evento en web
INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'web_id_raffle', CAST(@new_raffle_id AS CHAR), CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'web_id_raffle');

UPDATE settings SET value_setting = CAST(@new_raffle_id AS CHAR), date_updated_setting = NOW()
WHERE key_setting = 'web_id_raffle';

-- Generar números 0000–9999 si el evento es nuevo (sin tickets previos)
INSERT INTO tickets (number_ticket, status_ticket, id_raffle_ticket, date_created_ticket)
SELECT LPAD(seq.n, 4, '0'), 0, @new_raffle_id, NOW()
FROM (
    SELECT a.i + b.i * 10 + c.i * 100 + d.i * 1000 AS n
    FROM
        (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
    CROSS JOIN
        (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
    CROSS JOIN
        (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
    CROSS JOIN
        (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d
) seq
WHERE @new_raffle_id IS NOT NULL
  AND seq.n < 10000
  AND NOT EXISTS (
      SELECT 1 FROM tickets t WHERE t.id_raffle_ticket = @new_raffle_id LIMIT 1
  );

COMMIT;
