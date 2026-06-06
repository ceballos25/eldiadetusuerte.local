<?php
/**
 * WEBHOOK PUENTE (SERVIDOR PRINCIPAL) — v2
 * - Recibe JSON reenviado desde accesorios.caballosrevelo.com (service-payment-server)
 * - Valida firma HMAC
 * - Persiste webhook en BD (archivos JSON en accesorios)
 * - Procesa aprobación/rechazo con idempotencia
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/paymentBackupsController.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Application\Webhook\OpenPayBridgeSignatureException;
use App\Application\Webhook\OpenPayBridgeSignatureValidator;

function bridgeLog(string $message): void
{
    writeAppLog('openpay-bridge.log', $message);
}

function headersLower(): array
{
    $h = function_exists('getallheaders') ? getallheaders() : [];
    $out = [];
    foreach ($h as $k => $v) {
        $out[strtolower((string)$k)] = (string)$v;
    }
    return $out;
}

function unauthorized(string $reason): never
{
    bridgeLog('UNAUTHORIZED: ' . $reason);
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

function validateSignature(string $raw): void
{
    $h = headersLower();
    try {
        OpenPayBridgeSignatureValidator::validate(
            $raw,
            (string)env('OPENPAY_BRIDGE_SECRET', ''),
            $h['x-bridge-signature'] ?? '',
            $h['x-bridge-timestamp'] ?? ''
        );
    } catch (OpenPayBridgeSignatureException $e) {
        unauthorized($e->reasonCode);
    }
}

$raw = (string)file_get_contents('php://input');
bridgeLog('WEBHOOK BRIDGE RECIBIDO: ' . $raw);

$data = json_decode($raw, true);
if (!is_array($data)) {
    bridgeLog('JSON invalido');
    http_response_code(400);
    echo 'BAD_REQUEST';
    exit;
}

if (OPENPAY_REQUIRE_BRIDGE_SIGNATURE) {
    validateSignature($raw);
} else {
    bridgeLog('ADVERTENCIA: OPENPAY_REQUIRE_BRIDGE_SIGNATURE=false, firma HMAC no validada');
}

try {
    $processor = AppContainer::get()->webhooks();
    $result = $processor->process($data, 'openpay-bridge');
    bridgeLog('PROCESADO uuid=' . ($result['uuid'] ?? '?') . ' result=' . json_encode($result['result'] ?? []));
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'result' => $result['result'] ?? null]);
} catch (Throwable $e) {
    bridgeLog('ERROR PROCESANDO: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
