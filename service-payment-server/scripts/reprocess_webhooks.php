<?php
declare(strict_types=1);

/**
 * Reprocesa JSON en openpay/webhooks/pending/ o error/ (accesorios).
 *
 * Uso (SSH en el hosting accesorios, desde public_html):
 *   php scripts/reprocess_webhooks.php
 *   php scripts/reprocess_webhooks.php --dir=error
 *   php scripts/reprocess_webhooks.php --file=PB-20260531135119265.json
 *   php scripts/reprocess_webhooks.php --dry-run
 *
 * Nota: copiar un archivo a pending/ NO lo reprocesa solo; hay que ejecutar este script
 * o esperar un nuevo POST de OpenPay a webhook.php.
 */
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/lib/PaymentWebhookForward.php';
require_once $root . '/lib/WebhookFileStorage.php';

$opts = getopt('', ['dir::', 'file::', 'dry-run']);
$dir = isset($opts['dir']) ? (string)$opts['dir'] : 'pending';
$file = isset($opts['file']) ? basename((string)$opts['file']) : '';
$dryRun = array_key_exists('dry-run', $opts);

$allowedDirs = ['pending', 'error', 'all'];
if (!in_array($dir, $allowedDirs, true)) {
    fwrite(STDERR, "dir inválido. Use: pending, error o all\n");
    exit(1);
}

$storage = new PaymentWebhookFileStorage();
$dirs = $dir === 'all' ? ['pending', 'error'] : [$dir];

if ($dryRun) {
    foreach ($dirs as $sub) {
        $files = $storage->listJsonFiles($sub);
        echo strtoupper($sub) . ': ' . count($files) . " archivo(s)\n";
        foreach ($files as $name) {
            echo '  - ' . $name . "\n";
        }
    }
    exit(0);
}

if ($file !== '') {
    $targetDir = $dirs[0];
    $result = $storage->reprocessOne($targetDir, $file, 'paymentForwardToPrincipal');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit($result['ok'] ? 0 : 1);
}

$results = $storage->reprocessAll($dirs, 'paymentForwardToPrincipal');
if ($results === []) {
    echo "No hay archivos .json en " . implode(', ', $dirs) . "\n";
    exit(0);
}

$ok = 0;
$fail = 0;
foreach ($results as $row) {
    $status = $row['ok'] ? 'OK' : 'FAIL';
    if ($row['ok']) {
        $ok++;
    } else {
        $fail++;
    }
    echo sprintf(
        "[%s] %s/%s HTTP %d — %s\n",
        $status,
        $row['from'],
        $row['filename'],
        $row['http'],
        $row['message']
    );
}

echo "\nResumen: {$ok} ok, {$fail} fallidos\n";
exit($fail > 0 ? 1 : 0);
