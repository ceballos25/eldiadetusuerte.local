<?php
/**
 * Microservicio de pagos — accesorios.caballosrevelo.com
 *
 * Despliegue: sube TODO el contenido de esta carpeta a public_html del hosting.
 * .env en $VH_ROOT/.env (un nivel arriba de public_html).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/envLoader.php';
require_once __DIR__ . '/config/paths.php';

$publicRoot = __DIR__;
$envLoader = new EnvLoader(resolveEnvPath($publicRoot));
$envLoader->load();
bootstrapPrivateStorage($publicRoot);

define('BASE_URL', rtrim((string)env('SITE_URL', ''), '/'));

define('OPENPAY_WEBHOOK_USER', trim((string)env('OPENPAY_WEBHOOK_USER', '')));
define('OPENPAY_WEBHOOK_PASSWORD', (string)env('OPENPAY_WEBHOOK_PASSWORD', ''));
define('OPENPAY_RETURN_URL', trim((string)env('OPENPAY_RETURN_URL', '')));
define('OPENPAY_WEBHOOK_FORWARD_URL', trim((string)env('OPENPAY_WEBHOOK_FORWARD_URL', '')));
define('OPENPAY_BRIDGE_SECRET', (string)env('OPENPAY_BRIDGE_SECRET', ''));
define('OPENPAY_STATUS_API_URL', trim((string)env('OPENPAY_STATUS_API_URL', '')));
define('OPENPAY_STATUS_TOKEN', (string)env('OPENPAY_STATUS_TOKEN', ''));

define('META_PIXEL_ID', trim((string)env('META_PIXEL_ID', '')));

define('SITE_NAME', env('SITE_NAME', 'El Día de Tu Suerte'));
define('DEBUG_MODE', filter_var(env('DEBUG_MODE', false), FILTER_VALIDATE_BOOLEAN));

date_default_timezone_set(env('TIMEZONE') ?: 'America/Bogota');

if (filter_var(env('DISPLAY_ERRORS', false), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (defined('LOG_PATH') && is_dir(LOG_PATH) && is_writable(LOG_PATH)) {
        ini_set('error_log', LOG_PATH . '/php_errors.log');
    }
}

function paymentServerLog(string $message): void
{
    writeAppLog('payment-server.log', $message);
}
