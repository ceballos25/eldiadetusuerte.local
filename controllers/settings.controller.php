<?php

class SettingsController
{
    public const TABLE = 'settings';

    public static function obtenerSettings()
    {
        $rows = Db::fetchAll(
            'SELECT id_setting, key_setting, value_setting FROM settings ORDER BY id_setting ASC'
        );

        return ['success' => true, 'data' => $rows];
    }

    public static function actualizarSettings($data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, ['action', 'csrf_token', '_csrf'], true)) {
                continue;
            }

            $key = trim((string)$key);
            $value = trim((string)$value);

            $n = Db::update(
                self::TABLE,
                [
                    'value_setting' => $value,
                    'date_updated_setting' => date('Y-m-d H:i:s'),
                ],
                'key_setting = :k',
                [':k' => $key]
            );

            if ($n < 1) {
                return [
                    'success' => false,
                    'message' => "Setting no encontrado o sin cambios: $key",
                ];
            }
        }

        return ['success' => true, 'message' => 'Settings actualizados'];
    }

    public static function crearSetting($data)
    {
        if (trim((string)($data['key_setting'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Key requerida'];
        }

        $key = strtolower(trim($data['key_setting']));
        $key = preg_replace('/\s+/', '_', $key);
        $value = isset($data['value_setting']) ? trim((string)$data['value_setting']) : '';

        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            return ['success' => false, 'message' => 'Key inválida'];
        }

        $exists = Db::fetchOne(
            'SELECT id_setting FROM settings WHERE key_setting = :k LIMIT 1',
            [':k' => $key]
        );

        if ($exists) {
            return ['success' => false, 'message' => 'La key ya existe'];
        }

        Db::insert(self::TABLE, [
            'key_setting' => $key,
            'value_setting' => $value,
            'date_created_setting' => date('Y-m-d'),
            'date_updated_setting' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Setting creado'];
    }

    public static function eliminarSetting($data)
    {
        if (empty($data['id_setting'])) {
            return ['success' => false, 'message' => 'ID requerido'];
        }

        $row = Db::fetchOne(
            'SELECT key_setting FROM settings WHERE id_setting = :id LIMIT 1',
            [':id' => (int)$data['id_setting']]
        );

        if (!$row) {
            return ['success' => false, 'message' => 'Setting no encontrado'];
        }

        $key = (string)$row->key_setting;
        $protected = ['precio_ticket', 'max_tickets', 'min_tickets'];

        if (in_array($key, $protected, true)) {
            return ['success' => false, 'message' => 'Este setting no se puede eliminar'];
        }

        $n = Db::delete(self::TABLE, 'id_setting = :id', [':id' => (int)$data['id_setting']]);

        return $n > 0
            ? ['success' => true, 'message' => 'Setting eliminado']
            : ['success' => false, 'message' => 'Error al eliminar'];
    }
}
