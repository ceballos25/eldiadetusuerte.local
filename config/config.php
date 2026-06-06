<?php
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/envLoader.php';
require_once __DIR__ . '/paths.php';

$publicRoot = dirname(__DIR__);
$envLoader = new EnvLoader(resolveEnvPath($publicRoot));
$envLoader->load();
bootstrapPrivateStorage($publicRoot);

/**
 * ===============================
 * RUTAS ABSOLUTAS (FIX WINDOWS/LINUX)
 * ===============================
 */
define('DS', DIRECTORY_SEPARATOR);
define('BASE_URL', rtrim(env('SITE_URL'), '/'));
define('ASSETS_URL', BASE_URL . '/assets');
define('CDN_URL', rtrim((string)env('CDN_URL', 'https://cdn.eldiadetusuerte.com'), '/'));
define('PRODUCTION_SITE_URL', rtrim((string)env('PRODUCTION_SITE_URL', 'https://eldiadetusuerte.com'), '/'));
define('SITE_DISPLAY_NAME', (string)env('SITE_DISPLAY_NAME', 'El Día de Tu Suerte Oficial'));

/**
 * ===============================
 * CONFIG DB
 * ===============================
 */
define('DB_HOST', env('DB_HOST'));
define('DB_PORT', env('DB_PORT'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_NAME', env('DB_NAME'));
define('DB_CHARSET', env('DB_CHARSET'));

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../controllers/db.php';
require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/site-favicon.php';

if (!defined('APP_RUNNING_TESTS')) {
    \App\Shared\Exception\ExceptionHandler::register();
}

/**
 * ===============================
 * SMTP / MAIL
 * ===============================
 */
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', env('SMTP_PORT'));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION'));

define('MAIL_FROM', env('MAIL_FROM'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME'));
define('MAIL_BCC', env('MAIL_BCC'));

/**
 * ===============================
 * OPENPAY
 * ===============================
 */
define('OPENPAY_MERCHANT_ID', env('OPENPAY_MERCHANT_ID'));
define('OPENPAY_PRIVATE_KEY', env('OPENPAY_PRIVATE_KEY'));
define('OPENPAY_PUBLIC_KEY', env('OPENPAY_PUBLIC_KEY'));

define(
    'OPENPAY_URL',
    env('OPENPAY_ENV') === 'production'
        ? 'https://api.openpay.co/v1/' . env('OPENPAY_MERCHANT_ID')
        : 'https://sandbox-api.openpay.co/v1/'. env('OPENPAY_MERCHANT_ID')
);

define('OPENPAY_RETURN_URL', env('OPENPAY_RETURN_URL'));
// URL pública donde OpenPay envía notificaciones (POST + Basic Auth).
define(
    'OPENPAY_WEBHOOK_URL',
    trim((string)env('OPENPAY_WEBHOOK_URL', '')) !== ''
        ? rtrim(trim((string)env('OPENPAY_WEBHOOK_URL')), '/')
        : BASE_URL . '/openpay/webhook.php'
);
define('OPENPAY_WEBHOOK_USER', trim((string)env('OPENPAY_WEBHOOK_USER', '')));
define('OPENPAY_WEBHOOK_PASSWORD', (string)env('OPENPAY_WEBHOOK_PASSWORD', ''));
// Solo el microservicio de pagos (pagos.eldiadetusuerte.com) reenvía; en principal suele ir vacío.
define('OPENPAY_WEBHOOK_FORWARD_URL', trim((string)env('OPENPAY_WEBHOOK_FORWARD_URL', '')));
define('OPENPAY_BRIDGE_SECRET', (string)env('OPENPAY_BRIDGE_SECRET', ''));
// Mismo valor en accesorios (header X-Status-Token); si vacío, status.bridge usa OPENPAY_BRIDGE_SECRET.
define('OPENPAY_STATUS_TOKEN', (string)env('OPENPAY_STATUS_TOKEN', ''));
define('OPENPAY_STATUS_API_URL', trim((string)env('OPENPAY_STATUS_API_URL', '')));
// En producción: accesorios firma con HMAC al reenviar; el principal valida en webhook.bridge.php.
define('OPENPAY_REQUIRE_BRIDGE_SIGNATURE', filter_var(env('OPENPAY_REQUIRE_BRIDGE_SIGNATURE', true), FILTER_VALIDATE_BOOLEAN));

/**
 * ===============================
 * CONFIG SITIO
 * ===============================
 */
define('SITE_NAME', env('SITE_NAME'));

/**
 * ===============================
 * META PIXEL + CONVERSIONS API
 * Producción (.env-cr):
 *   META_PIXEL_ID=2058534878012983
 *   META_ACCESS_TOKEN=token_desde_events_manager
 *   META_CAPI_ENABLED=true
 * Accesorios (.env): META_PIXEL_ID= mismo ID
 * ===============================
 */
define('META_PIXEL_ID', (string)env('META_PIXEL_ID', ''));
define('META_ACCESS_TOKEN', (string)env('META_ACCESS_TOKEN', ''));
define('META_API_VERSION', (string)env('META_API_VERSION', 'v20.0'));
define('META_TEST_EVENT_CODE', (string)env('META_TEST_EVENT_CODE', ''));
define('META_CAPI_ENABLED', filter_var(env('META_CAPI_ENABLED', true), FILTER_VALIDATE_BOOLEAN));
define('META_CAPI_DEBUG', filter_var(env('META_CAPI_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('META_CAPI_CONNECT_TIMEOUT', max(1, (int)env('META_CAPI_CONNECT_TIMEOUT', 5)));
define('META_CAPI_TIMEOUT', max(META_CAPI_CONNECT_TIMEOUT, (int)env('META_CAPI_TIMEOUT', 8)));

/**
 * ===============================
 * TIMEZONE
 * ===============================
 */
date_default_timezone_set(env('TIMEZONE') ?: 'America/Bogota');

/**
 * ===============================
 * MODO / ERRORES
 * ===============================
 */
define('APP_ENV', env('APP_ENV'));
// filter_var evita (bool)'none' === true (valor inválido en .env).
define('DEBUG_MODE', filter_var(env('DEBUG_MODE', false), FILTER_VALIDATE_BOOLEAN));

if (filter_var(env('DISPLAY_ERRORS', false), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    if (is_dir(LOG_PATH) && is_writable(LOG_PATH)) {
        ini_set('error_log', LOG_PATH . '/php_errors.log');
    }
}

/**
 * ===============================
 * CONFIGURACIÓN DE POS
 * ===============================
 */
define('SALE_PREFIX', env('SALE_PREFIX') ?? 'EDTS');
define('SALE_PAD', (int)(env('SALE_PAD') ?? 6));

/**
 * ===============================
 * CONFIGURACIÓN Y MANEJO DE SESIÓN
 * ===============================
 */
$cookieHttpOnly = filter_var(env('SESSION_COOKIE_HTTPONLY', true), FILTER_VALIDATE_BOOLEAN);
$cookieSecure = filter_var(env('SESSION_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN);
ini_set('session.cookie_httponly', $cookieHttpOnly ? '1' : '0');
ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');
$cookieSameSite = strtoupper((string)env('SESSION_COOKIE_SAMESITE', 'Lax'));
if (!in_array($cookieSameSite, ['LAX', 'STRICT', 'NONE'], true)) {
    $cookieSameSite = 'Lax';
}
ini_set('session.cookie_samesite', $cookieSameSite === 'NONE' ? 'None' : ($cookieSameSite === 'STRICT' ? 'Strict' : 'Lax'));
if ($cookieSameSite === 'NONE' && !$cookieSecure) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_lifetime', (string)(int)env('SESSION_LIFETIME', 28800));
ini_set('session.gc_maxlifetime', (string)(int)env('SESSION_LIFETIME', 28800));

// Sesiones fuera de public_html ($VH_ROOT/logs/sessions).
$sessionSavePath = trim((string)env('SESSION_SAVE_PATH', ''));
if ($sessionSavePath === '') {
    $sessionSavePath = LOG_PATH . DIRECTORY_SEPARATOR . 'sessions';
}
ensureWritableDirectory($sessionSavePath);
if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
    ini_set('session.save_path', $sessionSavePath);
}

if (
    PHP_SAPI !== 'cli'
    && filter_var(env('SESSION_AUTO_START', true), FILTER_VALIDATE_BOOLEAN)
    && session_status() === PHP_SESSION_NONE
) {
    session_name((string)env('SESSION_NAME', 'PHPSESSID'));
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * ===============================
 * PROTECCIÓN DE RUTAS
 * ===============================
 * Solo protege si NO estamos en páginas públicas
 */
$publicPages = [
    'index.php',
    'favicon.php',
    'pruebas.php',
    'dash.php',
    'transferencia.php',
    'webhook.php',
    'webhook.bridge.php',
    'status.bridge.php',
    'ventas.ajax.php',
    'web.ajax.php',
    'meta.ajax.php',
    'settings.ajax.php',
    'login.php',
    'success.php',
];
$scriptNames = array_unique(array_filter([
    basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')),
    basename((string)(parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '')),
]));
$isPublicPage = false;
foreach ($scriptNames as $base) {
    if ($base !== '' && in_array($base, $publicPages, true)) {
        $isPublicPage = true;
        break;
    }
}

// Solo validar autenticación si NO es una página pública (en web; CLI no aplica)
if (PHP_SAPI !== 'cli' && !$isPublicPage) {
    if (!isset($_SESSION["user_id"]) || empty($_SESSION["user_id"])) {
        $isAjaxRequest = false;
        foreach ($scriptNames as $base) {
            if (str_ends_with($base, '.ajax.php')) {
                $isAjaxRequest = true;
                break;
            }
        }
        if (!$isAjaxRequest && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $isAjaxRequest = true;
        }
        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header("Location: " . BASE_URL . "/dash.php");
        exit;
    }

    // Sesión controlada solo por SESSION_LIFETIME/cookies PHP.
}