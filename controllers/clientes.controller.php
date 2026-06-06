<?php

class ClientesController
{
    public const TABLE = 'customers';

    public static function obtenerClientes()
    {
        $search = !empty($_POST['search']) ? trim((string)$_POST['search']) : '';
        $status = (isset($_POST['status']) && $_POST['status'] !== '') ? $_POST['status'] : '';

        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'status_customer = :st';
            $params[':st'] = (int)$status;
        }

        if ($search !== '') {
            $telefonoLimpio = preg_replace('/[^0-9]/', '', $search);
            if (strpos($search, '@') !== false) {
                $where[] = 'email_customer LIKE :s';
                $params[':s'] = '%' . $search . '%';
            } elseif (is_numeric($telefonoLimpio) && strlen($telefonoLimpio) >= 3) {
                $where[] = 'phone_customer LIKE :p';
                $params[':p'] = '%' . $telefonoLimpio . '%';
            } else {
                $where[] = '(name_customer LIKE :n OR lastname_customer LIKE :n2)';
                $params[':n'] = '%' . $search . '%';
                $params[':n2'] = '%' . $search . '%';
            }
        }

        $sql = 'SELECT id_customer,name_customer,lastname_customer,phone_customer,email_customer,department_customer,city_customer,status_customer
            FROM customers WHERE ' . implode(' AND ', $where) . ' ORDER BY id_customer DESC';

        $rows = Db::fetchAll($sql, $params);

        return [
            'success' => true,
            'data' => $rows,
            'total' => count($rows),
        ];
    }

    public static function crearCliente($data)
    {
        if (
            empty($data['name_customer']) ||
            empty($data['lastname_customer']) ||
            empty($data['phone_customer']) ||
            empty($data['email_customer']) ||
            empty($data['department_customer']) ||
            empty($data['city_customer'])
        ) {
            return ['success' => false, 'message' => 'Error: Todos los campos son obligatorios'];
        }

        $datos = [
            'name_customer' => trim($data['name_customer']),
            'lastname_customer' => trim($data['lastname_customer']),
            'phone_customer' => trim($data['phone_customer']),
            'email_customer' => trim($data['email_customer']),
            'department_customer' => trim($data['department_customer']),
            'city_customer' => trim($data['city_customer']),
            'status_customer' => isset($data['status_customer']) ? (int)$data['status_customer'] : 1,
        ];

        $id = Db::insert(self::TABLE, $datos);

        return [
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'id_customer' => $id,
        ];
    }

    public static function actualizarCliente($data)
    {
        if (empty($data['id_customer'])) {
            return ['success' => false, 'message' => 'ID requerido'];
        }

        if (
            empty($data['name_customer']) ||
            empty($data['lastname_customer']) ||
            empty($data['phone_customer']) ||
            empty($data['email_customer']) ||
            empty($data['department_customer']) ||
            empty($data['city_customer'])
        ) {
            return ['success' => false, 'message' => 'Error: No puedes dejar campos vacíos'];
        }

        $datosActualizar = [
            'name_customer' => trim($data['name_customer']),
            'lastname_customer' => trim($data['lastname_customer']),
            'phone_customer' => trim($data['phone_customer']),
            'email_customer' => trim($data['email_customer']),
            'department_customer' => trim($data['department_customer']),
            'city_customer' => trim($data['city_customer']),
            'status_customer' => isset($data['status_customer']) ? (int)$data['status_customer'] : 1,
        ];

        $n = Db::update(self::TABLE, $datosActualizar, 'id_customer = :id', [':id' => (int)$data['id_customer']]);

        return $n > 0
            ? ['success' => true, 'message' => 'Cliente actualizado correctamente']
            : ['success' => false, 'message' => 'Error al actualizar'];
    }

    public static function eliminarCliente($data)
    {
        if (empty($data['id_customer'])) {
            return ['success' => false, 'message' => 'ID requerido'];
        }

        $n = Db::delete(self::TABLE, 'id_customer = :id', [':id' => (int)$data['id_customer']]);

        return $n > 0
            ? ['success' => true, 'message' => 'Cliente eliminado']
            : ['success' => false, 'message' => 'Error al eliminar'];
    }

    public static function obtenerOCrearCliente(array $data): int
    {
        $_POST['search'] = $data['phone_customer'];
        $_POST['status'] = '';

        $res = self::obtenerClientes();

        if (!empty($res['data'])) {
            return (int)$res['data'][0]->id_customer;
        }

        $crear = self::crearCliente($data);

        if (!$crear['success']) {
            throw new Exception('No se pudo crear el cliente');
        }

        if (!empty($crear['id_customer'])) {
            return (int)$crear['id_customer'];
        }

        $_POST['search'] = $data['phone_customer'];
        $res = self::obtenerClientes();

        if (empty($res['data'])) {
            throw new Exception('Cliente creado pero no encontrado');
        }

        return (int)$res['data'][0]->id_customer;
    }
}
