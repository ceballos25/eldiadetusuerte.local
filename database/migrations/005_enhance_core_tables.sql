-- Migration 005: Enhance existing core tables for v2 platform

-- Raffles: multi-type, sales control
ALTER TABLE raffles
    ADD COLUMN IF NOT EXISTS type_raffle ENUM('manual','automatic') NOT NULL DEFAULT 'automatic' AFTER status_raffle,
    ADD COLUMN IF NOT EXISTS min_quantity_raffle INT UNSIGNED NOT NULL DEFAULT 1 AFTER type_raffle,
    ADD COLUMN IF NOT EXISTS sales_blocked_raffle TINYINT(1) NOT NULL DEFAULT 0 AFTER min_quantity_raffle,
    ADD COLUMN IF NOT EXISTS hidden_raffle TINYINT(1) NOT NULL DEFAULT 0 AFTER sales_blocked_raffle,
    ADD COLUMN IF NOT EXISTS reservation_minutes_raffle INT UNSIGNED NOT NULL DEFAULT 15 AFTER hidden_raffle;

-- Tickets: reservation expiry + unique number per raffle
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS expires_at_ticket DATETIME NULL AFTER status_ticket;

-- Sales: cancellation support
ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS cancelled_at_sale DATETIME NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by_sale INT NULL,
    ADD COLUMN IF NOT EXISTS cancellation_type_sale ENUM('none','total','partial') NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS notes_sale TEXT NULL;

-- Admins: link to roles table
ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS id_role INT UNSIGNED NULL AFTER rol_admin;

-- Payment backups: manual ticket selection + expiry
ALTER TABLE payment_backups
    ADD COLUMN IF NOT EXISTS ticket_ids_payment_backup JSON NULL AFTER quantity_payment_backup,
    ADD COLUMN IF NOT EXISTS expires_at_payment_backup DATETIME NULL AFTER status_payment_backup;

-- Payment backup ↔ reserved tickets junction
CREATE TABLE IF NOT EXISTS payment_backup_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_payment_backup INT NOT NULL,
    id_ticket INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pbt_ticket (id_ticket),
    INDEX idx_pbt_backup (id_payment_backup),
    CONSTRAINT fk_pbt_backup FOREIGN KEY (id_payment_backup) REFERENCES payment_backups(id_payment_backup) ON DELETE CASCADE,
    CONSTRAINT fk_pbt_ticket FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale items for partial cancellation tracking
CREATE TABLE IF NOT EXISTS sale_items (
    id_sale_item INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sale INT NOT NULL,
    id_ticket INT NOT NULL,
    number_ticket VARCHAR(10) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status_item ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    cancelled_at TIMESTAMP NULL,
    cancelled_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_si_ticket (id_ticket),
    INDEX idx_si_sale (id_sale),
    CONSTRAINT fk_si_sale FOREIGN KEY (id_sale) REFERENCES sales(id_sale) ON DELETE CASCADE,
    CONSTRAINT fk_si_ticket FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
