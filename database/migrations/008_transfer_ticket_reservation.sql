-- Migration 008: Manual raffle support on transfers

ALTER TABLE transfers
    ADD COLUMN IF NOT EXISTS ticket_ids_transfer JSON NULL AFTER quantity_transfer,
    ADD COLUMN IF NOT EXISTS expires_at_transfer DATETIME NULL AFTER ticket_ids_transfer;
