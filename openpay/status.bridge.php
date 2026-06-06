<?php
/**
 * STATUS API (SERVIDOR PRINCIPAL)
 * Consulta por order_id para success.php del microservicio de pagos (accesorios).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/ventas.controller.php';

header('Content-Type: application/json; charset=utf-8');

function statusOut(array $payload, int $http = 200): never
{
    http_response_code($http);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function statusUnauthorized(string $reason): never
{
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'unauthorized',
        'message' => 'No pudimos verificar tu compra en este momento. Intenta recargar la página.',
    ], 401);
}

function statusHeadersLower(): array
{
    $h = function_exists('getallheaders') ? getallheaders() : [];
    $out = [];
    foreach ($h as $k => $v) {
        $out[strtolower((string)$k)] = (string)$v;
    }
    return $out;
}

function requireStatusToken(): void
{
    $token = (string)env('OPENPAY_STATUS_TOKEN', '');
    if ($token === '') {
        $token = (string)env('OPENPAY_BRIDGE_SECRET', '');
    }
    if ($token === '') {
        statusUnauthorized('token no configurado');
    }

    $h = statusHeadersLower();
    $got = $h['x-status-token'] ?? '';
    if ($got === '' || !hash_equals($token, $got)) {
        statusUnauthorized('token invalido');
    }
}

requireStatusToken();

$orderId = trim((string)($_GET['order_id'] ?? ''));
if ($orderId === '') {
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'invalid',
        'message' => 'Falta la referencia de tu compra.',
    ], 422);
}

$sale = Db::fetchOne(
    'SELECT id_sale, code_sale, total_sale, quantity_sale FROM sales WHERE code_sale = :c LIMIT 1',
    [':c' => $orderId]
);
if ($sale) {
    $detalle = VentasController::obtenerDetalleVenta((int)$sale->id_sale);
    if (!empty($detalle['success'])) {
        $payload = [
            'success' => true,
            'status' => 'ok',
            'id_sale' => (int)$sale->id_sale,
            'html_recibo' => (string)($detalle['html_recibo'] ?? ''),
        ];
        statusOut($payload);
    }
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'processing_failed',
        'message' => 'Tu compra fue registrada, pero no pudimos mostrar el comprobante. Guarda tu referencia y contáctanos.',
    ]);
}

$backup = Db::fetchOne(
    'SELECT status_payment_backup FROM payment_backups WHERE code_payment_backup = :c LIMIT 1',
    [':c' => $orderId]
);

if (!$backup) {
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'not_found',
        'message' => 'No encontramos esta compra. Si acabas de pagar, espera un momento. Si ya pasó tiempo, vuelve al sitio e intenta de nuevo.',
    ], 404);
}

$st = (int)($backup->status_payment_backup ?? 0);

if ($st === 1) {
    statusOut([
        'success' => true,
        'status' => 'pending',
        'message' => 'Estamos confirmando tu pago con el banco. No cierres esta página.',
    ]);
}

if ($st === 4) {
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'processing_failed',
        'message' => 'Recibimos tu pago, pero hubo un problema al preparar tu pedido. Guarda tu referencia y escríbenos para ayudarte.',
    ]);
}

if ($st === 2) {
    statusOut([
        'success' => true,
        'status' => 'pending',
        'message' => 'Tu pago fue aprobado. Estamos preparando tu pedido…',
    ]);
}

if ($st === 3) {
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'rejected',
        'message' => 'El banco no aprobó este pago. Tus nros quedaron liberados. Puedes intentar comprar de nuevo.',
    ]);
}

if ($st === 5) {
    statusOut([
        'success' => false,
        'status' => 'error',
        'error_code' => 'expired',
        'message' => 'Se agotó el tiempo para completar el pago. Vuelve al sitio e intenta de nuevo.',
    ]);
}

statusOut([
    'success' => true,
    'status' => 'pending',
    'message' => 'Estamos verificando el estado de tu compra…',
]);
