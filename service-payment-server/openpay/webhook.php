<?php
/**
 * Webhook OpenPay en el microservicio de pagos (accesorios.caballosrevelo.com).
 *
 * 1. Valida Basic Auth (usuario/clave del webhook en OpenPay).
 * 2. Responde "verification" aquí mismo.
 * 3. Reenvía al principal (webhook.bridge.php) con firma HMAC.
 * 4. Guarda JSON de auditoría (todos los eventos salvo verification) en openpay/webhooks/.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/PaymentWebhookForward.php';
require_once dirname(__DIR__) . '/lib/WebhookFileStorage.php';

header('Content-Type: application/json; charset=utf-8');

function paymentWebhookUnauthorized(string $reason): never
{
    paymentServerLog('WEBHOOK UNAUTHORIZED: ' . $reason);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

function paymentWebhookValidateBasicAuth(): void
{
    if (OPENPAY_WEBHOOK_USER === '') {
        paymentServerLog('ADVERTENCIA: OPENPAY_WEBHOOK_USER vacío');
        return;
    }

    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && isset($_SERVER['PHP_AUTH_USER'])) {
        $user = (string)$_SERVER['PHP_AUTH_USER'];
        $pass = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    } elseif (str_starts_with(strtolower($auth), 'basic ')) {
        $decoded = base64_decode(substr($auth, 6), true);
        if ($decoded === false) {
            paymentWebhookUnauthorized('basic malformado');
        }
        $parts = explode(':', $decoded, 2);
        $user = $parts[0] ?? '';
        $pass = $parts[1] ?? '';
    } else {
        paymentWebhookUnauthorized('sin Authorization Basic');
    }

    if (!hash_equals(OPENPAY_WEBHOOK_USER, $user) || !hash_equals(OPENPAY_WEBHOOK_PASSWORD, $pass)) {
        paymentWebhookUnauthorized('credenciales incorrectas');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method_not_allowed']);
    exit;
}

$raw = (string)file_get_contents('php://input');
paymentServerLog('WEBHOOK IN: ' . substr($raw, 0, 4000));

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'invalid_json']);
    exit;
}

paymentWebhookValidateBasicAuth();

$type = (string)($data['type'] ?? $data['event_type'] ?? '');
if ($type === 'verification') {
    paymentServerLog('VERIFICATION OK (payment-server)');
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'verified']);
    exit;
}

$filename = '';
$fileStorage = null;
try {
    $fileStorage = new PaymentWebhookFileStorage();
    if (PaymentWebhookFileStorage::shouldPersist($data)) {
        $filename = $fileStorage->store($data, $raw);
    }
} catch (Throwable $e) {
    paymentServerLog('WEBHOOK FILE store error: ' . $e->getMessage());
}

$forward = paymentForwardToPrincipal($raw);

if ($filename !== '' && $fileStorage instanceof PaymentWebhookFileStorage) {
    try {
        $fileStorage->markForwardResult($filename, $forward['ok'], $forward['http'], $forward['body']);
    } catch (Throwable $e) {
        paymentServerLog('WEBHOOK FILE move error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => $forward['ok'] ? 'forwarded' : 'accepted_queued',
    'forward_http' => $forward['http'],
    'forward_body' => DEBUG_MODE ? substr($forward['body'], 0, 500) : null,
]);
