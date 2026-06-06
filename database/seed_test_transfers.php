<?php
declare(strict_types=1);

/**
 * Inserta transferencias de prueba para validar paginación/filtros.
 * Uso: php database/seed_test_transfers.php [--count=50] [--clean]
 */

require_once __DIR__ . '/../config/config.php';

$opts = getopt('', ['count:', 'clean', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php database/seed_test_transfers.php [--count=50] [--clean]\n";
    exit(0);
}

$count = max(1, min(500, (int)($opts['count'] ?? 50)));
$clean = isset($opts['clean']);

$raffle = Db::fetchOne('SELECT id_raffle FROM raffles ORDER BY id_raffle DESC LIMIT 1');
if (!$raffle) {
    fwrite(STDERR, "No hay rifas en la base de datos.\n");
    exit(1);
}
$idRaffle = (int)$raffle->id_raffle;

$banks = ['BANCOLOMBIA', 'NEQUI', 'DAVIPLATA', 'BANCO DE BOGOTÁ', 'BBVA'];
$nombres = ['Ana', 'Carlos', 'María', 'Juan', 'Laura', 'Pedro', 'Sofía', 'Diego', 'Camila', 'Andrés'];
$apellidos = ['García', 'Rodríguez', 'López', 'Martínez', 'Hernández', 'Pérez', 'Gómez', 'Ruiz'];

if ($clean) {
    $deleted = Db::pdo()->exec(
        "DELETE t FROM transfers t
         INNER JOIN customers c ON c.id_customer = t.id_customer_transfer
         WHERE t.code_transfer LIKE 'TST%'"
    );
    Db::pdo()->exec(
        "DELETE FROM customers WHERE phone_customer LIKE '3999%' AND email_customer LIKE 'test.transfer.%'"
    );
    echo "Eliminadas transferencias de prueba previas: {$deleted}\n";
}

$inserted = 0;
$baseTs = time();

for ($i = 1; $i <= $count; $i++) {
    $phone = '3999' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
    $email = "test.transfer.{$i}@example.local";

    $existing = Db::fetchOne(
        'SELECT id_customer FROM customers WHERE phone_customer = :p LIMIT 1',
        [':p' => $phone]
    );

    if ($existing) {
        $idCustomer = (int)$existing->id_customer;
    } else {
        $idCustomer = Db::insert('customers', [
            'name_customer' => $nombres[$i % count($nombres)],
            'lastname_customer' => $apellidos[$i % count($apellidos)] . ' Test',
            'phone_customer' => $phone,
            'email_customer' => $email,
            'department_customer' => 'Antioquia',
            'city_customer' => 'Medellín',
            'status_customer' => 1,
        ]);
        if ($idCustomer <= 0) {
            fwrite(STDERR, "Error creando cliente #{$i}\n");
            continue;
        }
    }

    // ~80% pendientes, resto otros estados para probar filtros
    $status = match (true) {
        $i % 10 === 0 => 2,
        $i % 13 === 0 => 3,
        $i % 17 === 0 => 4,
        default => 1,
    };

    $qty = 3 + ($i % 8);
    $amount = $qty * 15000 + ($i * 500);
    $code = 'TST' . str_pad((string)$i, 7, '0', STR_PAD_LEFT);
    $createdAt = date('Y-m-d H:i:s', $baseTs - ($count - $i) * 3600);

    $id = Db::insert('transfers', [
        'code_transfer' => $code,
        'id_raffle_transfer' => $idRaffle,
        'id_customer_transfer' => $idCustomer,
        'quantity_transfer' => $qty,
        'amount_transfer' => $amount,
        'currency_transfer' => 'COP',
        'url_transfer' => null,
        'status_transfer' => $status,
        'source_transfer' => $banks[$i % count($banks)],
        'date_created_transfer' => $createdAt,
    ]);

    if ($id > 0) {
        $inserted++;
    }
}

$pending = Db::fetchOne('SELECT COUNT(*) AS n FROM transfers WHERE status_transfer = 1');
$testPending = Db::fetchOne(
    "SELECT COUNT(*) AS n FROM transfers WHERE code_transfer LIKE 'TST%' AND status_transfer = 1"
);

echo "Insertadas: {$inserted} transferencias de prueba (rifa #{$idRaffle}).\n";
echo "Pendientes totales en sistema: " . (int)($pending->n ?? 0) . "\n";
echo "Pendientes de prueba (TST*): " . (int)($testPending->n ?? 0) . "\n";
echo "Para borrarlas: php database/seed_test_transfers.php --clean\n";
