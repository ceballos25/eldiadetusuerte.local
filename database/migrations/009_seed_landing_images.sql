-- Migration 009: Landing images from legacy CDN → BD (site_images)

INSERT INTO site_images (key_image, url_image, fallback_url) VALUES
('logo', 'https://cdn.eldiadetusuerte.com/images/logos/logo.jpg', NULL),
('logo_white', 'https://cdn.eldiadetusuerte.com/images/logos/logo-blanco.jpg', NULL),
('favicon', 'https://cdn.eldiadetusuerte.com/images/logos/logo.ico', NULL),
('pse', 'https://cdn.eldiadetusuerte.com/images/logos/pse.png', NULL)
ON DUPLICATE KEY UPDATE
    url_image = VALUES(url_image),
    fallback_url = VALUES(fallback_url);
