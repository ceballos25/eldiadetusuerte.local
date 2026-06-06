<?php
declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Infrastructure\Database\PdoFactory;
use PDO;

/**
 * KPIs y series del dashboard leyendo directamente MySQL.
 */
final class DashboardDbService
{
    public function obtenerDashboard(string $fechaDesde, string $fechaHasta, ?int $idRaffle): array
    {
        $pdo = PdoFactory::get();
        $between1 = $fechaDesde . ' 00:00:00';
        $between2 = $fechaHasta . ' 23:59:59';

        $sql = <<<'SQL'
SELECT s.id_sale, s.total_sale, s.quantity_sale, s.date_created_sale, s.payment_method_sale,
       s.source_sale, s.id_admin_sale,
       c.name_customer, c.lastname_customer, c.phone_customer, c.city_customer,
       r.title_raffle, s.code_sale,
       COALESCE(a.email_admin, 'Sistema') AS email_admin
FROM sales s
LEFT JOIN customers c ON c.id_customer = s.id_customer_sale
LEFT JOIN raffles r ON r.id_raffle = s.id_raffle_sale
LEFT JOIN admins a ON a.id_admin = s.id_admin_sale
WHERE s.date_created_sale BETWEEN :d1 AND :d2
SQL;
        if ($idRaffle !== null && $idRaffle > 0) {
            $sql .= ' AND s.id_raffle_sale = :rid';
        }
        $sql .= ' ORDER BY s.id_sale DESC LIMIT 100000';

        $st = $pdo->prepare($sql);
        $st->bindValue(':d1', $between1);
        $st->bindValue(':d2', $between2);
        if ($idRaffle !== null && $idRaffle > 0) {
            $st->bindValue(':rid', $idRaffle, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();

        $response = [
            'kpis' => [
                'totalVentas' => 0.0,
                'numerosVendidos' => 0,
                'numerosDisponibles' => 0,
                'numerosLibres' => 0,
                'numerosReservados' => 0,
                'totalClientes' => 0,
                'porcentajeReal' => 0.0,
                'numerosVendidosRifa' => 0,
                'totalNumerosRifa' => 0,
                'transferenciasPendientes' => 0,
                'tituloRifaProgreso' => '',
            ],
            'graficas' => [
                'tendencia' => [],
                'mediosPagoTransacciones' => [],
                'mediosPagoTickets' => [],
                'mediosPagoDinero' => [],
                'mediosPagoLabels' => [],
                'topClientes' => [],
                'topCiudades' => [],
                'heatmap' => [],
                'paquetes' => [],
            ],
            'ultimasVentas' => [],
        ];

        $tendenciaMap = [];
        $mediosTrans = [];
        $mediosTickets = [];
        $mediosDinero = [];
        $ciudadesMap = [];
        $clientesDetalle = [];
        $heatmapRaw = [];
        for ($d = 1; $d <= 7; $d++) {
            for ($h = 0; $h <= 23; $h++) {
                $heatmapRaw[$d][$h] = 0;
            }
        }
        $paquetesMap = [];

        foreach ($rows as $v) {
            $monto = (float)$v['total_sale'];
            $cantidad = (int)$v['quantity_sale'];
            $ts = strtotime((string)$v['date_created_sale']);

            $response['kpis']['totalVentas'] += $monto;
            $response['kpis']['numerosVendidos'] += $cantidad;

            $fecha = date('Y-m-d', $ts);
            $tendenciaMap[$fecha] = ($tendenciaMap[$fecha] ?? 0) + $monto;

            $metodo = $v['payment_method_sale'] !== '' && $v['payment_method_sale'] !== null
                ? (string)$v['payment_method_sale'] : 'Otros';
            $mediosDinero[$metodo] = ($mediosDinero[$metodo] ?? 0) + $monto;
            $mediosTickets[$metodo] = ($mediosTickets[$metodo] ?? 0) + $cantidad;
            $mediosTrans[$metodo] = ($mediosTrans[$metodo] ?? 0) + 1;

            $nombreFull = trim((string)$v['name_customer'] . ' ' . (string)$v['lastname_customer']);
            if (!isset($clientesDetalle[$nombreFull])) {
                $clientesDetalle[$nombreFull] = [
                    'total' => 0.0,
                    'cantidad' => 0,
                    'telefono' => $v['phone_customer'] ?: 'N/A',
                    'ciudad' => $v['city_customer'] ?: 'N/A',
                ];
            }
            $clientesDetalle[$nombreFull]['total'] += $monto;
            $clientesDetalle[$nombreFull]['cantidad'] += $cantidad;

            $ciudad = strtoupper((string)($v['city_customer'] ?: 'NO REGISTRADA'));
            $ciudadesMap[$ciudad] = ($ciudadesMap[$ciudad] ?? 0) + $cantidad;

            $diaSemana = (int)date('N', $ts);
            $horaDia = (int)date('G', $ts);
            $heatmapRaw[$diaSemana][$horaDia]++;

            $keyPaquete = $cantidad . ' Ticket' . ($cantidad > 1 ? 's' : '');
            $paquetesMap[$keyPaquete] = ($paquetesMap[$keyPaquete] ?? 0) + 1;
        }

        // Objetos tipo API para últimas ventas (compatibilidad con dashboard.js)
        $ultimas = [];
        foreach (array_slice($rows, 0, 10) as $r) {
            $ultimas[] = (object)[
                'id_sale' => (int)$r['id_sale'],
                'code_sale' => $r['code_sale'],
                'name_customer' => $r['name_customer'],
                'lastname_customer' => $r['lastname_customer'],
                'phone_customer' => $r['phone_customer'],
                'quantity_sale' => (int)$r['quantity_sale'],
                'payment_method_sale' => $r['payment_method_sale'],
                'source_sale' => $r['source_sale'],
                'email_admin' => $r['email_admin'],
                'title_raffle' => $r['title_raffle'],
                'total_sale' => $r['total_sale'],
                'date_created_sale' => $r['date_created_sale'],
            ];
        }
        $response['ultimasVentas'] = $ultimas;

        // Stock no vendido: disponibles (0) + reservados (2).
        $available = TicketStatus::AVAILABLE;
        $reserved = TicketStatus::RESERVED;
        $sqlAvail = "SELECT
            SUM(CASE WHEN status_ticket = {$available} THEN 1 ELSE 0 END) AS libres,
            SUM(CASE WHEN status_ticket = {$reserved} THEN 1 ELSE 0 END) AS reservados
            FROM tickets WHERE status_ticket IN ({$available}, {$reserved})";
        $paramsAvail = [];
        if ($idRaffle !== null && $idRaffle > 0) {
            $sqlAvail .= ' AND id_raffle_ticket = :rid';
            $paramsAvail[':rid'] = $idRaffle;
        }
        $stA = $pdo->prepare($sqlAvail);
        foreach ($paramsAvail as $k => $val) {
            $stA->bindValue($k, $val, PDO::PARAM_INT);
        }
        $stA->execute();
        $stock = $stA->fetch(PDO::FETCH_ASSOC) ?: [];
        $libres = (int)($stock['libres'] ?? 0);
        $reservados = (int)($stock['reservados'] ?? 0);
        $response['kpis']['numerosLibres'] = $libres;
        $response['kpis']['numerosReservados'] = $reservados;
        $response['kpis']['numerosDisponibles'] = $libres + $reservados;

        $response['kpis']['totalClientes'] = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();

        $sqlProgress = 'SELECT
            SUM(CASE WHEN status_ticket = 1 THEN 1 ELSE 0 END) AS vendidos,
            COUNT(*) AS total
            FROM tickets WHERE 1=1';
        $paramsProgress = [];
        if ($idRaffle !== null && $idRaffle > 0) {
            $sqlProgress .= ' AND id_raffle_ticket = :rid';
            $paramsProgress[':rid'] = $idRaffle;
        }
        $stP = $pdo->prepare($sqlProgress);
        foreach ($paramsProgress as $k => $val) {
            $stP->bindValue($k, $val, PDO::PARAM_INT);
        }
        $stP->execute();
        $prog = $stP->fetch(PDO::FETCH_ASSOC);
        $vendidosRifa = (int)($prog['vendidos'] ?? 0);
        $totalRifa = (int)($prog['total'] ?? 0);
        $response['kpis']['numerosVendidosRifa'] = $vendidosRifa;
        $response['kpis']['totalNumerosRifa'] = $totalRifa;
        $response['kpis']['porcentajeReal'] = $totalRifa > 0
            ? round(($vendidosRifa * 100) / $totalRifa, 2)
            : 0.0;

        if ($idRaffle !== null && $idRaffle > 0) {
            $stTitle = $pdo->prepare('SELECT title_raffle FROM raffles WHERE id_raffle = :id LIMIT 1');
            $stTitle->bindValue(':id', $idRaffle, PDO::PARAM_INT);
            $stTitle->execute();
            $response['kpis']['tituloRifaProgreso'] = (string)($stTitle->fetchColumn() ?: '');
        } else {
            $response['kpis']['tituloRifaProgreso'] = 'Todas las rifas';
        }

        $sqlPending = 'SELECT COUNT(*) FROM transfers WHERE status_transfer = 1';
        $paramsPending = [];
        if ($idRaffle !== null && $idRaffle > 0) {
            $sqlPending .= ' AND id_raffle_transfer = :rid';
            $paramsPending[':rid'] = $idRaffle;
        }
        $stPending = $pdo->prepare($sqlPending);
        foreach ($paramsPending as $k => $val) {
            $stPending->bindValue($k, $val, PDO::PARAM_INT);
        }
        $stPending->execute();
        $response['kpis']['transferenciasPendientes'] = (int)$stPending->fetchColumn();

        ksort($tendenciaMap);
        foreach ($tendenciaMap as $f => $monto) {
            $response['graficas']['tendencia'][] = ['fecha' => $f, 'total' => $monto];
        }

        foreach ($mediosDinero as $m => $dinero) {
            $response['graficas']['mediosPagoDinero'][] = $dinero;
            $response['graficas']['mediosPagoTickets'][] = $mediosTickets[$m] ?? 0;
            $response['graficas']['mediosPagoTransacciones'][] = $mediosTrans[$m] ?? 0;
            $response['graficas']['mediosPagoLabels'][] = $m;
        }

        uasort($clientesDetalle, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $i = 0;
        foreach ($clientesDetalle as $nombre => $datos) {
            if ($i++ >= 5) {
                break;
            }
            $response['graficas']['topClientes'][] = [
                'name' => $nombre,
                'total' => $datos['total'],
                'cantidad' => $datos['cantidad'],
                'telefono' => $datos['telefono'],
                'ciudad' => $datos['ciudad'],
            ];
        }

        arsort($ciudadesMap);
        $j = 0;
        foreach ($ciudadesMap as $ciu => $cant) {
            if ($j++ >= 5) {
                break;
            }
            $response['graficas']['topCiudades'][] = ['name' => $ciu, 'data' => $cant];
        }

        $diasLabels = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        foreach ($diasLabels as $num => $nombreDia) {
            $dataDia = [];
            for ($h = 0; $h <= 23; $h++) {
                $dataDia[] = ['x' => $h . ':00', 'y' => $heatmapRaw[$num][$h]];
            }
            $response['graficas']['heatmap'][] = ['name' => $nombreDia, 'data' => $dataDia];
        }

        arsort($paquetesMap);
        $k = 0;
        foreach ($paquetesMap as $label => $cant) {
            if ($k++ >= 10) {
                break;
            }
            $response['graficas']['paquetes'][] = ['name' => $label, 'data' => $cant];
        }

        return ['success' => true, 'data' => $response];
    }

    public function listarRifas(): array
    {
        $pdo = PdoFactory::get();
        $rows = $pdo->query('SELECT id_raffle, title_raffle FROM raffles ORDER BY id_raffle DESC')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = (object)['id_raffle' => (int)$r['id_raffle'], 'title_raffle' => $r['title_raffle']];
        }
        return ['success' => true, 'data' => $out];
    }
}
