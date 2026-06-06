-- Migration 003: Audit logs and webhook event tracking
CREATE TABLE IF NOT EXISTS audit_logs (
    id_audit BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    ip_address VARCHAR(45) NULL,
    action_audit VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_admin (admin_id),
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action_audit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
    id_webhook BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid_webhook CHAR(36) NOT NULL,
    source_webhook VARCHAR(50) NOT NULL DEFAULT 'openpay',
    event_type_webhook VARCHAR(100) NULL,
    payload_webhook JSON NOT NULL,
    status_webhook ENUM('pending','processing','processed','error') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE KEY uk_uuid (uuid_webhook),
    INDEX idx_webhook_status (status_webhook),
    INDEX idx_webhook_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
