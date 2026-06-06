<?php

class NumerosController
{
    public const TABLE = 'tickets';

    public static function obtenerInventario()
    {
        $idRaffle = $_POST['id_raffle'] ?? '';
        $search = trim($_POST['search'] ?? '');
        $estado = $_POST['status'] ?? '';

        if (empty($idRaffle)) {
            return ['success' => true, 'data' => [], 'price_raffle' => 0];
        }

        $where = ['id_raffle_ticket = :r'];
        $params = [':r' => $idRaffle];

        if ($estado !== '') {
            $where[] = 'status_ticket = :st';
            $params[':st'] = (int)$estado;
        }

        if ($search !== '') {
            $where[] = 'number_ticket = :num';
            $params[':num'] = $search;
        }

        $sql = 'SELECT id_ticket,number_ticket,status_ticket,is_winner_ticket,is_premium_ticket,id_raffle_ticket FROM tickets WHERE '
            . implode(' AND ', $where) . ' ORDER BY number_ticket ASC';

        $data = Db::fetchAll($sql, $params);

        $grilla = !empty($_POST['grilla']);
        if ($grilla && $data !== []) {
            usort($data, static function ($a, $b) {
                $na = (int)($a->number_ticket ?? 0);
                $nb = (int)($b->number_ticket ?? 0);
                if ($na !== $nb) {
                    return $na <=> $nb;
                }
                return strcmp((string)($a->number_ticket ?? ''), (string)($b->number_ticket ?? ''));
            });
        } elseif ($data !== []) {
            shuffle($data);
        }

        $priceRow = Db::fetchOne(
            'SELECT price_raffle FROM raffles WHERE id_raffle = :id LIMIT 1',
            [':id' => (int)$idRaffle]
        );
        $priceRaffle = $priceRow ? (float)($priceRow->price_raffle ?? 0) : 0.0;

        $result = ['success' => true, 'data' => $data, 'price_raffle' => $priceRaffle];

        if ($grilla) {
            $stats = ['disponibles' => 0, 'reservados' => 0, 'vendidos' => 0, 'total' => count($data)];
            foreach ($data as $row) {
                switch ((int)($row->status_ticket ?? -1)) {
                    case 0:
                        $stats['disponibles']++;
                        break;
                    case 2:
                        $stats['reservados']++;
                        break;
                    case 1:
                        $stats['vendidos']++;
                        break;
                }
            }
            $result['stats'] = $stats;
        }

        return $result;
    }

    public static function cambiarEstado()
    {
        $idTicket = (int)($_POST['id_ticket'] ?? 0);
        $nuevoEstado = (int)($_POST['status'] ?? -1);

        if ($idTicket <= 0 || $nuevoEstado < 0) {
            return ['success' => false, 'message' => 'Datos inválidos'];
        }

        $row = Db::fetchOne(
            'SELECT status_ticket FROM tickets WHERE id_ticket = :id LIMIT 1',
            [':id' => $idTicket]
        );

        if ($row && (int)$row->status_ticket === 1) {
            return ['success' => false, 'message' => 'No se puede modificar un nro vendido.'];
        }

        $n = Db::update(
            self::TABLE,
            ['status_ticket' => $nuevoEstado],
            'id_ticket = :id',
            [':id' => $idTicket]
        );

        return ['success' => $n > 0];
    }

    public static function listarRifas()
    {
        $rows = Db::fetchAll('SELECT id_raffle, title_raffle FROM raffles ORDER BY id_raffle DESC');

        return ['success' => true, 'data' => $rows];
    }

    public static function obtenerNumerosVendidos()
    {
        return VentasController::obtenerNumerosVendidos();
    }

    public static function obtenerProgreso($idRaffle)
    {
        if (empty($idRaffle)) {
            return ['success' => false, 'message' => 'ID de rifa requerido'];
        }

        try {
            $idRaffle = (int)$idRaffle;

            $totalRow = Db::fetchOne(
                'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r',
                [':r' => $idRaffle]
            );
            $total = (int)($totalRow->c ?? 0);

            $vendRow = Db::fetchOne(
                'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 1',
                [':r' => $idRaffle]
            );
            $vendidos = (int)($vendRow->c ?? 0);

            $porcentaje = $total > 0 ? round(($vendidos * 100) / $total, 2) : 0;

            return [
                'success' => true,
                'total' => $total,
                'vendidos' => $vendidos,
                'porcentaje' => $porcentaje,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function marcarTicketFlags(): array
    {
        $id = (int)($_POST['id_ticket'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID inválido'];
        }

        $ticket = Db::fetchOne(
            'SELECT status_ticket FROM tickets WHERE id_ticket = :id LIMIT 1',
            [':id' => $id]
        );
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado'];
        }

        $data = [];
        if (array_key_exists('is_winner_ticket', $_POST)) {
            $data['is_winner_ticket'] = (int)(bool)$_POST['is_winner_ticket'];
        }
        if (array_key_exists('is_premium_ticket', $_POST)) {
            $premiumValue = (int)(bool)$_POST['is_premium_ticket'];
            if ((int)$ticket->status_ticket === 1) {
                return ['success' => false, 'message' => 'No se puede editar premium en nros vendidos'];
            }
            $data['is_premium_ticket'] = $premiumValue;
        }

        if ($data === []) {
            return ['success' => false, 'message' => 'Sin campos para actualizar'];
        }

        try {
            $n = Db::update(self::TABLE, $data, 'id_ticket = :id', [':id' => $id]);

            return ['success' => $n > 0];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
