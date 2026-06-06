-- Migration 007: Seed default roles and permissions

INSERT IGNORE INTO roles (name_role, slug_role, description_role, is_system) VALUES
('Super Administrador', 'superadmin', 'Acceso total al sistema', 1),
('Administrador', 'admin', 'Gestión operativa completa', 1),
('Operador', 'operador', 'Ventas y consultas', 1),
('Auditor', 'auditor', 'Solo lectura', 1);

INSERT IGNORE INTO permissions (module_permission, action_permission, description_permission) VALUES
('dashboard', 'view', 'Ver dashboard'),
('rifas', 'view', 'Ver rifas'),
('rifas', 'create', 'Crear rifas'),
('rifas', 'edit', 'Editar rifas'),
('rifas', 'delete', 'Eliminar rifas'),
('rifas', 'pause', 'Pausar/reanudar rifas'),
('ventas', 'view', 'Ver ventas'),
('ventas', 'create', 'Crear ventas'),
('ventas', 'cancel', 'Anular ventas'),
('ventas', 'cancel_partial', 'Anulación parcial'),
('clientes', 'view', 'Ver clientes'),
('clientes', 'edit', 'Editar clientes'),
('usuarios', 'view', 'Ver usuarios'),
('usuarios', 'manage', 'Gestionar usuarios'),
('auditoria', 'view', 'Ver auditoría'),
('configuracion', 'view', 'Ver configuración'),
('configuracion', 'manage', 'Gestionar configuración'),
('reportes', 'view', 'Ver reportes'),
('reportes', 'export', 'Exportar reportes'),
('transferencias', 'view', 'Ver transferencias'),
('transferencias', 'approve', 'Aprobar transferencias');

-- Superadmin: all permissions
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission
FROM roles r
CROSS JOIN permissions p
WHERE r.slug_role = 'superadmin';

-- Admin: all except user management delete
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission
FROM roles r
CROSS JOIN permissions p
WHERE r.slug_role = 'admin'
  AND NOT (p.module_permission = 'usuarios' AND p.action_permission = 'manage');

-- Operador: sales and queries
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission
FROM roles r
JOIN permissions p ON (
    (p.module_permission = 'dashboard' AND p.action_permission = 'view') OR
    (p.module_permission = 'ventas') OR
    (p.module_permission = 'clientes' AND p.action_permission = 'view') OR
    (p.module_permission = 'rifas' AND p.action_permission = 'view') OR
    (p.module_permission = 'transferencias')
)
WHERE r.slug_role = 'operador';

-- Auditor: read-only
INSERT IGNORE INTO role_permissions (id_role, id_permission)
SELECT r.id_role, p.id_permission
FROM roles r
JOIN permissions p ON p.action_permission = 'view'
WHERE r.slug_role = 'auditor';

-- Map existing admins to roles based on rol_admin text
UPDATE admins a
JOIN roles r ON (
    (a.rol_admin LIKE '%superadmin%' AND r.slug_role = 'superadmin') OR
    (a.rol_admin LIKE '%administrador%' AND r.slug_role = 'admin') OR
    (a.rol_admin LIKE '%admin%' AND a.rol_admin NOT LIKE '%superadmin%' AND r.slug_role = 'admin') OR
    (a.rol_admin LIKE '%vendedor%' AND r.slug_role = 'operador') OR
    (a.rol_admin LIKE '%operador%' AND r.slug_role = 'operador')
)
SET a.id_role = r.id_role
WHERE a.id_role IS NULL;

UPDATE admins SET id_role = (SELECT id_role FROM roles WHERE slug_role = 'admin' LIMIT 1)
WHERE id_role IS NULL;
