-- Migration 012: Eliminar promociones (tabla, columna legacy y permisos)

DELETE rp FROM role_permissions rp
INNER JOIN permissions p ON p.id_permission = rp.id_permission
WHERE p.module_permission = 'promociones';

DELETE FROM permissions WHERE module_permission = 'promociones';

DROP TABLE IF EXISTS promotions;

ALTER TABLE raffles
    DROP COLUMN IF EXISTS promotions_raffle;
