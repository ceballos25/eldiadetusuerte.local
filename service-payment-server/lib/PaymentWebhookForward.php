<?php
declare(strict_types=1);

/**
 * Reenvío firmado al webhook.bridge.php del servidor principal.
 *
 * @return array{ok: bool, http: int, body: string}
 */
function paymentForwardToPrincipal(string $raw): array
{
    $url = OPENPAY_WEBHOOK_FORWARD_URL;
    if ($url === '') {
        paymentServerLog('OPENPAY_WEBHOOK_FORWARD_URL no configurada');
        return ['ok' => false, 'http' => 0, 'body' => 'forward_url_empty'];
    }
    if (OPENPAY_BRIDGE_SECRET === '') {
        paymentServerLog('OPENPAY_BRIDGE_SECRET vacío');
        return ['ok' => false, 'http' => 0, 'body' => 'bridge_secret_empty'];
    }

    $ts = (string)time();
    $sig = hash_hmac('sha256', $ts . '.' . $raw, OPENPAY_BRIDGE_SECRET);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $raw,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Bridge-Signature: ' . $sig,
            'X-Bridge-Timestamp: ' . $ts,
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        paymentServerLog('FORWARD curl error: ' . $err . ' url=' . $url);
        return ['ok' => false, 'http' => 0, 'body' => $err];
    }

    paymentServerLog('FORWARD url=' . $url . ' HTTP ' . $code . ' body=' . substr((string)$body, 0, 800));
    return ['ok' => $code >= 200 && $code < 300, 'http' => $code, 'body' => (string)$body];
}
