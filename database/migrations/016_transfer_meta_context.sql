-- Contexto Meta (IP, fbp, fbc) al crear transferencia web.
ALTER TABLE transfers
    ADD COLUMN IF NOT EXISTS meta_transfer JSON NULL AFTER source_transfer;
