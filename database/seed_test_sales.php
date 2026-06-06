<?php
declare(strict_types=1);

/**
 * Inserta ventas de prueba con números (tickets + sale_items).
 * Uso: php database/seed_test_sales.php [--count=1000] [--qty=3] [--clean]
 */

require_once __DIR__ . '/../config/config.php';

$opts = getopt('', ['count:', 'qty:', 'clean', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php database/seed_test_sales.php [--count=1000] [--qty=3] [--clean]\n";
    exit(0);
}

$count = max(1, min(10000, (int)($opts['count'] ?? 1000)));
$qtyPerSale = max(1, min(20, (int)($opts['qty'] ?? 3)));
$clean = isset($opts['clean']);
$batchSize = 50;

$raffle = Db::fetchOne('SELECT id_raffle, price_raffle FROM raffles ORDER BY id_raffle DESC LIMIT 1');
if (!$raffle) {
    fwrite(STDERR, "No hay rifas en la base de datos.\n");
    exit(1);
}

$idRaffle = (int)$raffle->id_raffle;
$unitPrice = (float)$raffle->price_raffle;
if ($unitPrice <= 0) {
    fwrite(STDERR, "La rifa #{$idRaffle} no tiene precio configurado.\n");
    exit(1);
}

$nombres = ['Ana', 'Carlos', 'María', 'Juan', 'Laura', 'Pedro', 'Sofía', 'Diego', 'Camila', 'Andrés'];
$apellidos = ['García', 'Rodríguez', 'López', 'Martínez', 'Hernández', 'Pérez', 'Gómez', 'Ruiz'];
$metodos = ['Efectivo', 'Transferencia', 'OpenPay', 'Nequi', 'Tarjeta'];
$origenes = ['W', 'A', 'T', 'M', null];

$pdo = Db::pdo();

if ($clean) {
    $pdo->beginTransaction();
    try {
        $reset = $pdo->exec(
            "UPDATE tickets t
             INNER JOIN sales s ON s.id_sale = t.id_sale_ticket
             SET t.status_ticket = 0,
                 t.id_customer_ticket = NULL,
                 t.id_sale_ticket = NULL
             WHERE s.code_sale LIKE 'SEED%'"
        );
        $deletedSales = $pdo->exec("DELETE FROM sales WHERE code_sale LIKE 'SEED%'");
        $deletedCustomers = $pdo->exec(
            "DELETE FROM customers
             WHERE phone_customer LIKE '3888%'
               AND email_customer LIKE 'test.sale.%'"
        );
        $pdo->commit();
        echo "Limpieza: {$deletedSales} ventas, tickets liberados ~{$reset}, {$deletedCustomers} clientes.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, 'Error en limpieza: ' . $e->getMessage() . "\n");
        exit(1);
    }

    if (!array_key_exists('count', $opts)) {
        exit(0);
    }
}

$ticketsNeeded = $count * $qtyPerSale;
$available = Db::fetchOne(
    'SELECT COUNT(*) AS n
     FROM tickets t
     WHERE t.id_raffle_ticket = :r
       AND t.status_ticket = 0
       AND NOT EXISTS (SELECT 1 FROM sale_items si WHERE si.id_ticket = t.id_ticket)',
    [':r' => $idRaffle]
);
$disponibles = (int)($available->n ?? 0);
if ($disponibles < $ticketsNeeded) {
    fwrite(STDERR, "Tickets insuficientes: necesitas {$ticketsNeeded}, hay {$disponibles} disponibles.\n");
    exit(1);
}

$ticketRows = Db::fetchAll(
    'SELECT t.id_ticket, t.number_ticket
     FROM tickets t
     WHERE t.id_raffle_ticket = :r
       AND t.status_ticket = 0
       AND NOT EXISTS (SELECT 1 FROM sale_items si WHERE si.id_ticket = t.id_ticket)
     ORDER BY t.id_ticket ASC
     LIMIT ' . (int)$ticketsNeeded,
    [':r' => $idRaffle]
);

if (count($ticketRows) < $ticketsNeeded) {
    fwrite(STDERR, "No se pudieron reservar {$ticketsNeeded} tickets.\n");
    exit(1);
}

$insertedSales = 0;
$insertedTickets = 0;
$baseTs = time();
$ticketIndex = 0;

echo "Insertando {$count} ventas ({$qtyPerSale} núm/venta) en rifa #{$idRaffle}…\n";

for ($batchStart = 1; $batchStart <= $count; $batchStart += $batchSize) {
    $batchEnd = min($count, $batchStart + $batchSize - 1);
    $pdo->beginTransaction();

    try {
        for ($i = $batchStart; $i <= $batchEnd; $i++) {
            $phone = '3888' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
            $email = "test.sale.{$i}@example.local";

            $existing = Db::fetchOne(
                'SELECT id_customer FROM customers WHERE phone_customer = :p LIMIT 1',
                [':p' => $phone]
            );

            if ($existing) {
                $idCustomer = (int)$existing->id_customer;
            } else {
                $idCustomer = Db::insert('customers', [
                    'name_customer' => $nombres[$i % count($nombres)],
                    'lastname_customer' => $apellidos[$i % count($apellidos)] . ' Seed',
                    'phone_customer' => $phone,
                    'email_customer' => $email,
                    'department_customer' => 'Antioquia',
                    'city_customer' => 'Medellín',
                    'status_customer' => 1,
                ]);
                if ($idCustomer <= 0) {
                    throw new RuntimeException("Error creando cliente #{$i}");
                }
            }

            $saleTickets = [];
            for ($q = 0; $q < $qtyPerSale; $q++) {
                if (!isset($ticketRows[$ticketIndex])) {
                    throw new RuntimeException('Se agotaron los tickets disponibles');
                }
                $saleTickets[] = $ticketRows[$ticketIndex];
                $ticketIndex++;
            }

            $totalSale = (float)((int)round($unitPrice * $qtyPerSale));
            $unitItem = round($totalSale / $qtyPerSale, 2);
            $codeSale = 'SEED' . str_pad((string)$i, 10, '0', STR_PAD_LEFT);
            $createdAt = date('Y-m-d H:i:s', $baseTs - ($count - $i) * 180);

            $idSale = Db::insert('sales', [
                'id_customer_sale' => $idCustomer,
                'id_raffle_sale' => $idRaffle,
                'code_sale' => $codeSale,
                'quantity_sale' => $qtyPerSale,
                'total_sale' => $totalSale,
                'payment_method_sale' => $metodos[$i % count($metodos)],
                'status_sale' => 1,
                'id_admin_sale' => null,
                'source_sale' => $origenes[$i % count($origenes)],
                'date_created_sale' => $createdAt,
            ]);

            if ($idSale <= 0) {
                throw new RuntimeException("Error creando venta #{$i}");
            }

            $stTicket = $pdo->prepare(
                'UPDATE tickets
                 SET status_ticket = 1, id_customer_ticket = :cid, id_sale_ticket = :sid
                 WHERE id_ticket = :tid AND status_ticket = 0'
            );
            $stItem = $pdo->prepare(
                'INSERT INTO sale_items (id_sale, id_ticket, number_ticket, unit_price, status_item)
                 VALUES (:sid, :tid, :num, :price, \'active\')'
            );

            foreach ($saleTickets as $tk) {
                $stTicket->execute([
                    ':cid' => $idCustomer,
                    ':sid' => $idSale,
                    ':tid' => (int)$tk->id_ticket,
                ]);
                if ($stTicket->rowCount() !== 1) {
                    throw new RuntimeException('Ticket #' . $tk->id_ticket . ' ya no está disponible');
                }
                $stItem->execute([
                    ':sid' => $idSale,
                    ':tid' => (int)$tk->id_ticket,
                    ':num' => (string)$tk->number_ticket,
                    ':price' => $unitItem,
                ]);
                $insertedTickets++;
            }

            $insertedSales++;
        }

        $pdo->commit();
        echo "  … {$batchEnd}/{$count} ventas\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Error en lote {$batchStart}-{$batchEnd}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$totalSales = Db::fetchOne('SELECT COUNT(*) AS n FROM sales');
$seedSales = Db::fetchOne("SELECT COUNT(*) AS n FROM sales WHERE code_sale LIKE 'SEED%'");
$soldTickets = Db::fetchOne(
    'SELECT COUNT(*) AS n FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 1',
    [':r' => $idRaffle]
);

echo "Listo: {$insertedSales} ventas, {$insertedTickets} números asignados.\n";
echo "Ventas totales en sistema: " . (int)($totalSales->n ?? 0) . " (seed: " . (int)($seedSales->n ?? 0) . ")\n";
echo "Tickets vendidos rifa #{$idRaffle}: " . (int)($soldTickets->n ?? 0) . "\n";
echo "Para borrar: php database/seed_test_sales.php --clean\n";
