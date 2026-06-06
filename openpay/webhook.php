<?php
/**
 * Endpoint público para notificaciones webhook de OpenPay.
 *
 * OpenPay envía POST JSON con autenticación HTTP Basic (user/password del webhook).
 * Al registrar el webhook, OpenPay dispara un evento "verification".
 *
 * Configurar en .env:
 *   OPENPAY_WEBHOOK_URL=https://tudominio.com/openpay/webhook.php
 *   OPENPAY_WEBHOOK_USER=...
 *   OPENPAY_WEBHOOK_PASSWORD=...
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/paymentBackupsController.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Application\Webhook\OpenPayWebhookEventTypes;
use App\Application\Webhook\OpenPayWebhookPayloadNormalizer;

header('Content-Type: application/json; charset=utf-8');

function openpayWebhookLog(string $message): void
{
    writeAppLog('openpay-webhook.log', $message);
}

function openpayWebhookUnauthorized(string $reason): never
{
    openpayWebhookLog('UNAUTHORIZED: ' . $reason);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

function openpayWebhookValidateBasicAuth(): void
{
    if (!defined('OPENPAY_WEBHOOK_USER') || OPENPAY_WEBHOOK_USER === '') {
        openpayWebhookLog('ADVERTENCIA: OPENPAY_WEBHOOK_USER vacío — webhook sin autenticación');
        return;
    }

    $expectedUser = (string)OPENPAY_WEBHOOK_USER;
    $expectedPass = defined('OPENPAY_WEBHOOK_PASSWORD') ? (string)OPENPAY_WEBHOOK_PASSWORD : '';

    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && isset($_SERVER['PHP_AUTH_USER'])) {
        $user = (string)$_SERVER['PHP_AUTH_USER'];
        $pass = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    } elseif (str_starts_with(strtolower($auth), 'basic ')) {
        $decoded = base64_decode(substr($auth, 6), true);
        if ($decoded === false) {
            openpayWebhookUnauthorized('basic malformado');
        }
        $parts = explode(':', $decoded, 2);
        $user = $parts[0] ?? '';
        $pass = $parts[1] ?? '';
    } else {
        openpayWebhookUnauthorized('sin Authorization Basic');
    }

    if (!hash_equals($expectedUser, $user) || !hash_equals($expectedPass, $pass)) {
        openpayWebhookUnauthorized('credenciales incorrectas');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method_not_allowed']);
    exit;
}

$raw = (string)file_get_contents('php://input');
openpayWebhookLog('RECIBIDO: ' . substr($raw, 0, 4000));

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'invalid_json']);
    exit;
}

openpayWebhookValidateBasicAuth();

$normalized = OpenPayWebhookPayloadNormalizer::normalize($data);
$eventType = $normalized['type'] ?? '';

if ($eventType === OpenPayWebhookEventTypes::VERIFICATION) {
    openpayWebhookLog('VERIFICATION OK');
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'verified']);
    exit;
}

try {
    $processor = AppContainer::get()->webhooks();
    $result = $processor->process($data, 'openpay');
    openpayWebhookLog('OK uuid=' . ($result['uuid'] ?? '?') . ' ' . json_encode($result['result'] ?? []));
    http_response_code(200);
    echo json_encode(['success' => true, 'uuid' => $result['uuid'] ?? null]);
} catch (Throwable $e) {
    openpayWebhookLog('ERROR: ' . $e->getMessage());
    // OpenPay reintenta si no recibe 200; respondemos 200 tras persistir para no perder el evento
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'stored_with_error']);
}
