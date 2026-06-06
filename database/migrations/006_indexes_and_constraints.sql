-- Migration 006: Performance indexes and critical constraints

-- Unique number per raffle (prevents duplicate assignments)
-- Skip if already exists (MariaDB/MySQL may error on duplicate index)
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tickets'
      AND index_name = 'uk_raffle_number'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tickets ADD UNIQUE KEY uk_raffle_number (id_raffle_ticket, number_ticket)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ticket status + raffle lookup for inventory queries
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tickets'
      AND index_name = 'idx_ticket_raffle_status'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tickets ADD INDEX idx_ticket_raffle_status (id_raffle_ticket, status_ticket, expires_at_ticket)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Raffle status for active raffle queries
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'raffles'
      AND index_name = 'idx_raffle_status'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE raffles ADD INDEX idx_raffle_status (status_raffle, hidden_raffle, sales_blocked_raffle)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Expired reservations cleanup
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tickets'
      AND index_name = 'idx_ticket_expires'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tickets ADD INDEX idx_ticket_expires (status_ticket, expires_at_ticket)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
