-- Migration 002: Roles and permissions system
CREATE TABLE IF NOT EXISTS roles (
    id_role INT AUTO_INCREMENT PRIMARY KEY,
    name_role VARCHAR(50) NOT NULL,
    slug_role VARCHAR(50) NOT NULL,
    description_role VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id_permission INT AUTO_INCREMENT PRIMARY KEY,
    module_permission VARCHAR(50) NOT NULL,
    action_permission VARCHAR(50) NOT NULL,
    description_permission VARCHAR(255) NULL,
    UNIQUE KEY uk_module_action (module_permission, action_permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id_role INT NOT NULL,
    id_permission INT NOT NULL,
    PRIMARY KEY (id_role, id_permission),
    CONSTRAINT fk_rp_role FOREIGN KEY (id_role) REFERENCES roles(id_role) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (id_permission) REFERENCES permissions(id_permission) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
