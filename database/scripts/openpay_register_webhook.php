#!/usr/bin/env php
<?php
/**
 * Registra (o lista) webhooks en OpenPay para este comercio.
 *
 * Uso:
 *   php database/scripts/openpay_register_webhook.php
 *   php database/scripts/openpay_register_webhook.php --list
 *   php database/scripts/openpay_register_webhook.php --delete=WEBHOOK_ID
 *   php database/scripts/openpay_register_webhook.php --url=https://caballosrevelo.com/openpay/webhook.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/bootstrap/container.php';

use App\Application\Webhook\OpenPayWebhookRegistrationService;

$args = $argv ?? [];
$list = in_array('--list', $args, true);
$deleteId = null;
$customUrl = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--delete=')) {
        $deleteId = substr($arg, 9);
    }
    if (str_starts_with($arg, '--url=')) {
        $customUrl = trim(substr($arg, 6));
    }
}

try {
    $service = AppContainer::get()->openPayWebhookRegistration();

    if ($deleteId !== null && $deleteId !== '') {
        $service->delete($deleteId);
        echo "Webhook eliminado: {$deleteId}\n";
        exit(0);
    }

    if ($list) {
        $hooks = $service->list();
        echo json_encode($hooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    $url = $customUrl ?? $service->defaultWebhookUrl();
    echo "Registrando webhook en OpenPay...\n";
    echo "URL:  {$url}\n";
    echo "User: " . $service->webhookUser() . "\n";

    $created = $service->create($url);
    echo "OK\n";
    echo json_encode($created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
