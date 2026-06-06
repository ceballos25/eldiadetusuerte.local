-- Migration 010: Eliminar URLs de imágenes por rifa (hardcode en frontend)

DROP TABLE IF EXISTS raffle_images;

ALTER TABLE raffles
    DROP COLUMN IF EXISTS image_url_raffle,
    DROP COLUMN IF EXISTS fallback_image_url_raffle;
