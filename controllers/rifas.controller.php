<?php

require_once __DIR__ . '/raffle_web_sync.php';

class RifasController
{
    public const TABLE = 'raffles';

    private const TICKET_INSERT_CHUNK = 400;

    /** Normaliza precio COP entero (evita "10000.00" → 1000000 al quitar solo el punto). */
    public static function normalizePriceCOP(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) max(0, (int) round((float) $value));
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        if (preg_match('/^\d+$/', $s)) {
            return (float) $s;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            return (float) str_replace('.', '', $s);
        }
        if (preg_match('/^\d+[.,]\d{1,2}$/', $s)) {
            return (float) round((float) str_replace(',', '.', $s), 0);
        }
        $digits = preg_replace('/\D/', '', $s);

        return $digits !== '' ? (float) $digits : 0.0;
    }

    public static function obtenerRifasActivas()
    {
        $rows = Db::fetchAll(
            'SELECT * FROM raffles
             WHERE status_raffle = 1 AND hidden_raffle = 0
             ORDER BY id_raffle DESC'
        );

        return ['success' => true, 'data' => $rows];
    }

    public static function obtenerRifas()
    {
        $search = !empty($_POST['search']) ? trim((string)$_POST['search']) : '';
        $status = (isset($_POST['status']) && $_POST['status'] !== '') ? $_POST['status'] : '';

        $where = ['1=1'];
        $params = [];

        if ($search !== '' && $status !== '') {
            $where[] = 'title_raffle LIKE :s AND status_raffle = :st';
            $params[':s'] = '%' . $search . '%';
            $params[':st'] = (int)$status;
        } elseif ($search !== '') {
            $where[] = 'title_raffle LIKE :s';
            $params[':s'] = '%' . $search . '%';
        } elseif ($status !== '') {
            $where[] = 'status_raffle = :st';
            $params[':st'] = (int)$status;
        }

        $sql = 'SELECT * FROM raffles WHERE ' . implode(' AND ', $where) . ' ORDER BY id_raffle DESC';
        $rows = Db::fetchAll($sql, $params);

        return ['success' => true, 'data' => $rows];
    }

    public static function crearRifa($data)
    {
        set_time_limit(0);

        $datos = [
            'title_raffle' => trim($data['title_raffle']),
            'description_raffle' => trim($data['description_raffle']),
            'price_raffle' => self::normalizePriceCOP($data['price_raffle'] ?? 0),
            'digits_raffle' => (int)$data['digits_raffle'],
            'date_raffle' => $data['date_raffle'],
            'status_raffle' => (int)$data['status_raffle'],
            'type_raffle' => in_array(($data['type_raffle'] ?? 'automatic'), ['manual', 'automatic'], true)
                ? $data['type_raffle']
                : 'automatic',
        ];

        $idRifa = Db::insert(self::TABLE, $datos);
        if ($idRifa <= 0) {
            return ['success' => false, 'message' => 'Error al crear la rifa.'];
        }

        $cifras = (int)$data['digits_raffle'];
        $totalBoletos = (int)pow(10, $cifras);

        $pdo = Db::pdo();
        $batch = [];
        for ($i = 0; $i < $totalBoletos; $i++) {
            $batch[] = [
                'number_ticket' => str_pad((string)$i, $cifras, '0', STR_PAD_LEFT),
                'status_ticket' => 0,
                'id_raffle_ticket' => $idRifa,
            ];
            if (count($batch) >= self::TICKET_INSERT_CHUNK) {
                self::insertTicketsBatch($pdo, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            self::insertTicketsBatch($pdo, $batch);
        }

        if ((int)$datos['status_raffle'] === 1) {
            RaffleWebSync::sync($idRifa);
        }

        return [
            'success' => true,
            'message' => "Rifa creada con $totalBoletos boletos.",
            'id_raffle' => $idRifa,
        ];
    }

    /** @param list<array{number_ticket:string,status_ticket:int,id_raffle_ticket:int}> $rows */
    private static function insertTicketsBatch(PDO $pdo, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($rows), '(?,?,?)'));
        $sql = 'INSERT INTO tickets (number_ticket, status_ticket, id_raffle_ticket) VALUES ' . $placeholders;
        $st = $pdo->prepare($sql);
        $flat = [];
        foreach ($rows as $r) {
            $flat[] = $r['number_ticket'];
            $flat[] = $r['status_ticket'];
            $flat[] = $r['id_raffle_ticket'];
        }
        $st->execute($flat);
    }

    public static function actualizarRifa($data)
    {
        $id = (int)$data['id_raffle'];
        $allowed = [
            'title_raffle', 'description_raffle', 'price_raffle',
            'digits_raffle', 'date_raffle', 'status_raffle', 'type_raffle',
            'min_quantity_raffle',
        ];
        $clean = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $clean[$k] = $k === 'price_raffle'
                    ? self::normalizePriceCOP($data[$k])
                    : $data[$k];
            }
        }
        if ($clean === []) {
            return ['success' => false];
        }
        $n = Db::update(self::TABLE, $clean, 'id_raffle = :id', [':id' => $id]);

        if ($n > 0) {
            $preferred = isset($clean['status_raffle']) && (int)$clean['status_raffle'] === 1
                ? $id
                : null;
            RaffleWebSync::sync($preferred);
        }

        return $n > 0 ? ['success' => true, 'message' => 'Rifa actualizada'] : ['success' => false];
    }

    public static function eliminarRifa($data)
    {
        $id = (int)$data['id_raffle'];
        Db::delete('tickets', 'id_raffle_ticket = :id', [':id' => $id]);
        $n = Db::delete(self::TABLE, 'id_raffle = :id', [':id' => $id]);

        return $n > 0 ? ['success' => true, 'message' => 'Rifa eliminada'] : ['success' => false];
    }
}
