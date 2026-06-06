<?php
/**
 * Retorno PSE — accesorios.caballosrevelo.com/openpay/success.php
 * Consulta caballosrevelo.com/openpay/status.bridge.php y muestra las boletas.
 *
 * Siempre muestra primero el flujo animado (banco → respuesta → pedido); luego las boletas.
 * El JS consulta en segundo plano cada 4 s si el pago aún no está listo.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$orderId = trim((string)($_GET['order_id'] ?? ''));

function paymentStatusToken(): string
{
    return OPENPAY_STATUS_TOKEN !== '' ? OPENPAY_STATUS_TOKEN : OPENPAY_BRIDGE_SECRET;
}

function paymentMainSiteUrl(): string
{
    $api = OPENPAY_STATUS_API_URL;
    if ($api !== '' && preg_match('#^(https?://[^/]+)#', $api, $m)) {
        return $m[1];
    }
    return 'https://eldiadetusuerte.com';
}

/** @return array<string, string> */
function paymentErrorTitles(): array
{
    return [
        'rejected' => 'Pago no aprobado',
        'expired' => 'Tiempo agotado',
        'not_found' => 'Compra no encontrada',
        'processing_failed' => 'Problema al preparar tu pedido',
        'timeout' => 'Sigue en proceso',
        'invalid' => 'Enlace no válido',
        'unauthorized' => 'No pudimos verificar',
        'generic' => 'No pudimos confirmar tu pago',
    ];
}

function paymentErrorTitle(string $code): string
{
    $titles = paymentErrorTitles();
    return $titles[$code] ?? $titles['generic'];
}

function paymentSuccessIconSvg(): string
{
    return '<svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
}

/** @return array<string, mixed>|null */
function paymentFetchOrderStatus(string $orderId): ?array
{
    $api = OPENPAY_STATUS_API_URL;
    if ($api === '') {
        paymentServerLog('OPENPAY_STATUS_API_URL vacía');
        return null;
    }

    $url = $api . (str_contains($api, '?') ? '&' : '?') . 'order_id=' . rawurlencode($orderId);
    $token = paymentStatusToken();
    if ($token === '') {
        paymentServerLog('token status vacío');
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['X-Status-Token: ' . $token],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        paymentServerLog('STATUS API sin respuesta order=' . $orderId);
        return null;
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        paymentServerLog('STATUS API JSON inválido HTTP ' . $code . ' order=' . $orderId);
        return null;
    }

    if ($code >= 200 && $code < 300) {
        return $json;
    }

    if ($code === 404 || $code === 401 || $code === 422) {
        return $json;
    }

    paymentServerLog('STATUS API HTTP ' . $code . ' order=' . $orderId . ' body=' . substr((string)$body, 0, 300));
    return null;
}

/** @return array{status: string, message: string, html_recibo: string, error_code: string} */
function paymentBuildStatusResponse(string $orderId): array
{
    $empty = ['status' => 'pending', 'message' => '', 'html_recibo' => '', 'error_code' => ''];

    if ($orderId === '') {
        return [
            'status' => 'invalid',
            'message' => 'No encontramos tu pedido. Vuelve al sitio e intenta de nuevo.',
            'html_recibo' => '',
            'error_code' => 'invalid',
        ];
    }

    $payload = paymentFetchOrderStatus($orderId);
    if ($payload === null) {
        return [
            'status' => 'pending',
            'message' => 'Estamos confirmando tu pago con el banco. No cierres esta página.',
            'html_recibo' => '',
            'error_code' => '',
        ];
    }

    $status = (string)($payload['status'] ?? 'pending');
    $htmlRecibo = (string)($payload['html_recibo'] ?? '');
    $message = (string)($payload['message'] ?? 'Un momento, estamos preparando tu pedido…');
    $errorCode = (string)($payload['error_code'] ?? '');

    if ($status === 'ok' && $htmlRecibo !== '') {
        return [
            'status' => 'ok',
            'message' => 'Tu pago fue confirmado exitosamente.',
            'html_recibo' => $htmlRecibo,
            'error_code' => '',
        ];
    }

    if ($status === 'error') {
        if ($errorCode === '') {
            $errorCode = 'generic';
        }
        return [
            'status' => 'error',
            'message' => $message,
            'html_recibo' => '',
            'error_code' => $errorCode,
        ];
    }

    if ($message === 'Confirmacion en curso') {
        $message = 'Estamos confirmando tu pago. Esto puede tardar unos segundos.';
    }

    return [
        'status' => 'pending',
        'message' => $message,
        'html_recibo' => '',
        'error_code' => '',
    ];
}

if (isset($_GET['poll']) && $_GET['poll'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(paymentBuildStatusResponse($orderId), JSON_UNESCAPED_UNICODE);
    exit;
}

$response = paymentBuildStatusResponse($orderId);
$status = $response['status'];
$htmlRecibo = $response['html_recibo'];
$statusMessage = $response['message'];
$errorCode = $response['error_code'];
$receiptReady = $status === 'ok' && $htmlRecibo !== '';
$isError = $status === 'error';
$isInvalid = $status === 'invalid';
$runIntroFlow = $orderId !== '' && !$isInvalid && !$isError;
$errorTitle = ($isError || $isInvalid)
    ? paymentErrorTitle($errorCode !== '' ? $errorCode : ($isInvalid ? 'invalid' : 'generic'))
    : '';
$pageTitle = $runIntroFlow
    ? 'Confirmando pago — ' . SITE_NAME
    : (($isError || $isInvalid) ? $errorTitle . ' — ' . SITE_NAME : 'Confirmando pago — ' . SITE_NAME);
$siteName = SITE_NAME;
$mainSiteUrl = paymentMainSiteUrl();
$errorTitlesJson = json_encode(paymentErrorTitles(), JSON_UNESCAPED_UNICODE);
$initialStatusJson = json_encode([
    'status' => $status,
    'html_recibo' => $receiptReady ? $htmlRecibo : '',
    'message' => $statusMessage,
    'error_code' => $errorCode,
], JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f4f4">
    <meta name="color-scheme" content="light">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cr-gold: #ffbc42;
            --cr-gold-dark: #e5a82a;
            --cr-gold-muted: #fff4e0;
            --cr-gold-rgb: 255, 188, 66;
            --cr-dark: #1a1a1a;
            --cr-black: #000000;
            --cr-text: #333333;
            --cr-text-muted: #666666;
            --cr-body-bg: #f4f4f4;
            --cr-surface: #ffffff;
            --cr-border: #e6e6e6;
            --cr-accent-green: #39cb7f;
            --cr-accent-green-soft: #e8f9f0;
            --cr-accent-red: #dc3545;
            --cr-accent-red-soft: #fdecee;
            --cr-radius-md: 14px;
            --cr-radius-lg: 20px;
            --cr-font: 'Montserrat', system-ui, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html { background: var(--cr-body-bg); }

        body {
            font-family: var(--cr-font);
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--cr-body-bg);
            color: var(--cr-text);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 1rem calc(1.5rem + env(safe-area-inset-bottom, 0));
        }

        .site-header {
            width: 100%;
            max-width: 100vw;
            background: var(--cr-black);
            border-bottom: 3px solid var(--cr-gold);
            padding: calc(0.85rem + env(safe-area-inset-top, 0)) 1rem 0.85rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .site-header__name {
            color: var(--cr-gold);
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .shell { width: 100%; max-width: 440px; flex: 1; }
        .shell--wide { max-width: min(680px, 100%); }

        .card {
            background: var(--cr-surface);
            border-radius: var(--cr-radius-lg);
            border: 1px solid var(--cr-border);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .card-body { padding: 1.75rem 1.25rem 1.5rem; }

        @media (min-width: 480px) {
            .card-body { padding: 2rem 1.75rem 1.75rem; }
        }

        .icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            background: var(--cr-gold-muted);
            border: 2px solid var(--cr-gold);
        }

        .icon-wrap.success {
            background: var(--cr-accent-green-soft);
            border-color: var(--cr-accent-green);
        }

        .icon-wrap.error {
            background: var(--cr-accent-red-soft);
            border-color: var(--cr-accent-red);
        }

        .icon-wrap svg { width: 28px; height: 28px; }
        .icon-wrap .accent { stroke: var(--cr-gold-dark); fill: none; }
        .icon-wrap.success .accent { stroke: var(--cr-accent-green); fill: none; }
        .icon-wrap.error .accent { stroke: var(--cr-accent-red); fill: none; }

        h1 {
            font-size: 1.15rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.4;
            margin-bottom: 0.65rem;
            color: var(--cr-dark);
        }

        @media (min-width: 480px) { h1 { font-size: 1.2rem; } }

        .subtitle {
            text-align: center;
            color: var(--cr-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.35rem;
            padding: 0 0.25rem;
        }

        .progress-block { margin-bottom: 1.25rem; }

        .txn-flow {
            position: relative;
            padding: 0.25rem 0.15rem 0;
        }

        .txn-rail {
            position: absolute;
            top: 19px;
            left: calc(16.66% + 14px);
            right: calc(16.66% + 14px);
            height: 5px;
            background: linear-gradient(90deg, #ececec, #e0e0e0, #ececec);
            border-radius: 999px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .txn-rail__fill {
            position: relative;
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: linear-gradient(90deg, #c9921f, var(--cr-gold-dark), var(--cr-gold), #ffd98a, var(--cr-gold));
            background-size: 220% 100%;
            animation: goldStream 2.2s linear infinite;
            transition: width 1.85s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 0 10px rgba(255, 188, 66, 0.55), 0 0 2px rgba(255, 188, 66, 0.8);
        }

        .txn-rail__fill.is-active::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 28px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65));
            animation: headGlow 1.4s ease-in-out infinite;
        }

        @keyframes goldStream {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }

        @keyframes headGlow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.25rem;
            position: relative;
            z-index: 1;
        }

        .step {
            text-align: center;
            padding: 0 0.1rem;
        }

        .step-dot {
            width: 38px;
            height: 38px;
            margin: 0 auto 0.55rem;
            border-radius: 50%;
            background: var(--cr-surface);
            border: 2px solid var(--cr-border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.45s ease, background 0.45s ease, box-shadow 0.45s ease, transform 0.45s ease;
        }

        .step-dot svg {
            width: 17px;
            height: 17px;
            stroke: #bbb;
            fill: none;
            transition: stroke 0.45s ease;
        }

        .step-text {
            font-size: 0.7rem;
            color: #aaa;
            font-weight: 500;
            line-height: 1.4;
            transition: color 0.45s ease, font-weight 0.45s ease;
        }

        @media (min-width: 480px) { .step-text { font-size: 0.74rem; } }

        .step.active .step-dot {
            border-color: var(--cr-gold);
            background: linear-gradient(145deg, #fff8e8, var(--cr-gold-muted));
            box-shadow: 0 0 0 4px rgba(255, 188, 66, 0.22), 0 4px 14px rgba(255, 188, 66, 0.28);
            transform: scale(1.06);
            animation: stepPulse 2s ease-in-out infinite;
        }

        .step.active .step-dot svg { stroke: var(--cr-gold-dark); }

        .step.active .step-text {
            color: var(--cr-gold-dark);
            font-weight: 700;
        }

        .step.done .step-dot {
            border-color: var(--cr-accent-green);
            background: var(--cr-accent-green);
            box-shadow: 0 0 0 3px rgba(57, 203, 127, 0.2);
            transform: scale(1);
            animation: none;
        }

        .step.done .step-dot svg { stroke: #fff; }

        .step.done .step-text {
            color: var(--cr-accent-green);
            font-weight: 600;
        }

        @keyframes stepPulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(255, 188, 66, 0.18), 0 4px 12px rgba(255, 188, 66, 0.2); }
            50% { box-shadow: 0 0 0 7px rgba(255, 188, 66, 0.12), 0 4px 18px rgba(255, 188, 66, 0.35); }
        }

        .txn-secure {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            margin-top: 1rem;
            font-size: 0.72rem;
            color: var(--cr-text-muted);
            letter-spacing: 0.02em;
        }

        .txn-secure svg {
            width: 13px;
            height: 13px;
            stroke: var(--cr-gold-dark);
            opacity: 0.85;
        }

        .order-ref {
            text-align: center;
            font-size: 0.85rem;
            color: var(--cr-text-muted);
            margin-top: 1.15rem;
            padding-top: 1.15rem;
            border-top: 1px solid var(--cr-border);
            line-height: 1.5;
        }

        .order-ref code {
            display: inline-block;
            margin-top: 0.35rem;
            font-size: 0.8rem;
            font-weight: 600;
            background: var(--cr-gold-muted);
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            color: var(--cr-dark);
            border: 1px solid rgba(var(--cr-gold-rgb), 0.35);
            word-break: break-all;
        }

        .btn-back {
            display: block;
            width: 100%;
            margin-top: 1.25rem;
            padding: 0.85rem 1rem;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--cr-dark);
            background: var(--cr-gold);
            border: none;
            border-radius: var(--cr-radius-md);
            transition: background 0.2s;
        }

        .btn-back:hover { background: var(--cr-gold-dark); color: var(--cr-dark); }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            margin-top: auto;
            padding-top: 1.25rem;
            font-size: 0.75rem;
            color: var(--cr-text-muted);
        }

        .secure-badge svg { width: 14px; height: 14px; opacity: 0.7; }

        .boletas-wrap { margin-top: 1rem; animation: fadeUp 0.5s ease; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .boletas-box {
            background: var(--cr-surface);
            border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius-md);
            padding: 0.75rem;
            overflow-x: auto;
        }

        .boletas-box img { max-width: 100%; height: auto; display: block; }

        .footer-note {
            text-align: center;
            font-size: 0.85rem;
            color: var(--cr-text-muted);
            margin-top: 1rem;
            line-height: 1.55;
            padding: 0 0.5rem;
        }

        .wait-hint {
            text-align: center;
            font-size: 0.82rem;
            color: var(--cr-text-muted);
            margin-top: 1rem;
            line-height: 1.5;
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header__name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></div>
    </header>

    <div class="shell" id="appShell">

        <?php if ($isInvalid): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap error">
                    <svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="subtitle"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <a class="btn-back" href="<?= htmlspecialchars($mainSiteUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            </div>
        </div>

        <?php elseif ($isError): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap error">
                    <svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="subtitle"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <a class="btn-back" href="<?= htmlspecialchars($mainSiteUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            </div>
        </div>

        <?php elseif ($runIntroFlow): ?>
        <div class="card" id="stateCard">
            <div class="card-body">
                <div class="icon-wrap hidden" id="stateIconWrap"></div>
                <h1 id="stateTitle">Confirmando con tu banco</h1>
                <p class="subtitle" id="stateMessage">Verificando tu transacción con la entidad bancaria…</p>

                <div class="progress-block" id="progressBlock">
                    <div class="txn-flow">
                        <div class="txn-rail" aria-hidden="true">
                            <div class="txn-rail__fill is-active" id="progressFill"></div>
                        </div>
                        <div class="steps">
                            <div class="step active" id="step1">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M12 3L3 10h18L12 3z"/><path d="M7 10v11"/><path d="M12 10v11"/><path d="M17 10v11"/></svg>
                                </div>
                                <p class="step-text">Confirmando<br>con tu banco</p>
                            </div>
                            <div class="step" id="step2">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.93 19.07l1.41-1.41"/><path d="M17.66 6.34l1.41-1.41"/></svg>
                                </div>
                                <p class="step-text">Obteniendo<br>respuesta</p>
                            </div>
                            <div class="step" id="step3">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                                </div>
                                <p class="step-text">Preparando<br>tu pedido</p>
                            </div>
                        </div>
                    </div>
                    <p class="wait-hint" id="waitHint">Espera en esta pantalla. Se actualiza sola — no recargues.</p>
                    <div class="txn-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Transacción protegida
                    </div>
                </div>

                <a class="btn-back hidden" id="btnBack" href="<?= htmlspecialchars($mainSiteUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>

                <?php if ($orderId !== ''): ?>
                <div class="order-ref">
                    Tu referencia de compra<br>
                    <code><?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div id="boletasContainer" class="boletas-wrap hidden"></div>
        <?php endif; ?>
    </div>

    <div class="secure-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Conexión segura
    </div>

    <?php if ($runIntroFlow): ?>
    <script>
    (function () {
        const orderId = <?= json_encode($orderId, JSON_UNESCAPED_UNICODE) ?>;
        const pollUrl = window.location.pathname + '?poll=1&order_id=' + encodeURIComponent(orderId);
        const errorTitles = <?= $errorTitlesJson ?>;
        const mainSiteUrl = <?= json_encode($mainSiteUrl, JSON_UNESCAPED_UNICODE) ?>;
        const successIconHtml = <?= json_encode(paymentSuccessIconSvg(), JSON_UNESCAPED_UNICODE) ?>;
        const initialStatus = <?= $initialStatusJson ?>;
        let attempts = 0;
        const maxAttempts = 60;
        const stepDurationMs = 4200;
        let stepPhase = 0;
        let polling = true;
        let flowComplete = false;
        let pendingReceiptHtml = null;
        let receiptFetched = false;
        const stageTimers = [];

        if (initialStatus.status === 'ok' && initialStatus.html_recibo) {
            pendingReceiptHtml = initialStatus.html_recibo;
            receiptFetched = true;
        }

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const progressBlock = document.getElementById('progressBlock');
        const progressFill = document.getElementById('progressFill');
        const stateTitle = document.getElementById('stateTitle');
        const stateMessage = document.getElementById('stateMessage');
        const stateIconWrap = document.getElementById('stateIconWrap');
        const boletasContainer = document.getElementById('boletasContainer');
        const appShell = document.getElementById('appShell');
        const btnBack = document.getElementById('btnBack');

        const stepTitles = [
            'Confirmando con tu banco',
            'Obteniendo respuesta',
            'Preparando tu pedido'
        ];
        const stepMessages = [
            'Verificando tu transacción con la entidad bancaria…',
            'Esperando la respuesta del sistema de pagos…',
            'Generando tu comprobante de pago…'
        ];
        const stepBarPct = [12, 50, 100];
        const stepDoneIcon = '<svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';

        function clearStageTimers() {
            while (stageTimers.length) clearTimeout(stageTimers.pop());
        }

        function setStepPhase(phase) {
            stepPhase = phase;
            [step1, step2, step3].forEach((el, i) => {
                if (!el) return;
                el.classList.remove('active', 'done');
                const dot = el.querySelector('.step-dot');
                if (i < phase) {
                    el.classList.add('done');
                    if (dot) dot.innerHTML = stepDoneIcon;
                } else if (i === phase) {
                    el.classList.add('active');
                }
            });
            if (progressFill) {
                progressFill.style.width = stepBarPct[phase] + '%';
            }
            if (stateTitle && polling) {
                stateTitle.textContent = stepTitles[phase];
            }
            if (stateMessage && polling) {
                stateMessage.textContent = stepMessages[phase];
            }
        }

        function maybeRevealReceipt() {
            if (flowComplete && pendingReceiptHtml) {
                showSuccess(pendingReceiptHtml);
                pendingReceiptHtml = null;
            }
        }

        function startStageFlow() {
            clearStageTimers();
            flowComplete = false;
            setStepPhase(0);
            stageTimers.push(setTimeout(() => {
                if (!polling) return;
                setStepPhase(1);
                stageTimers.push(setTimeout(() => {
                    if (!polling) return;
                    setStepPhase(2);
                    stageTimers.push(setTimeout(() => {
                        flowComplete = true;
                        maybeRevealReceipt();
                    }, stepDurationMs));
                }, stepDurationMs));
            }, stepDurationMs));
        }

        function titleForCode(code) {
            return errorTitles[code] || errorTitles.generic;
        }

        function showSuccess(html) {
            polling = false;
            clearStageTimers();
            progressBlock.classList.add('hidden');
            if (progressFill) progressFill.classList.remove('is-active');
            if (stateIconWrap) {
                stateIconWrap.classList.remove('hidden', 'error');
                stateIconWrap.className = 'icon-wrap success';
                stateIconWrap.innerHTML = successIconHtml;
            }
            stateTitle.textContent = '¡Pago confirmado!';
            stateMessage.textContent = 'Tu pago fue confirmado exitosamente. Aquí está tu comprobante.';
            if (btnBack) btnBack.classList.add('hidden');
            appShell.classList.add('shell--wide');
            boletasContainer.innerHTML = '<div class="boletas-box">' + html + '</div><p class="footer-note">Guarda una captura de pantalla de este comprobante. También te enviamos una copia a tu correo.</p>';
            boletasContainer.classList.remove('hidden');
            document.title = 'Pago confirmado — <?= addslashes($siteName) ?>';
        }

        function showError(msg, code) {
            polling = false;
            clearStageTimers();
            pendingReceiptHtml = null;
            progressBlock.classList.add('hidden');
            if (progressFill) progressFill.classList.remove('is-active');
            if (stateIconWrap) {
                stateIconWrap.classList.remove('hidden');
                stateIconWrap.className = 'icon-wrap error';
                stateIconWrap.innerHTML = '<svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            }
            stateTitle.textContent = titleForCode(code || 'generic');
            stateMessage.textContent = msg || 'Si ya pagaste, guarda tu referencia y escríbenos para ayudarte.';
            if (btnBack) btnBack.classList.remove('hidden');
            document.title = titleForCode(code || 'generic') + ' — <?= addslashes($siteName) ?>';
        }

        function showTimeout() {
            showError(
                'Tu pago puede tardar un poco más de lo normal. Guarda tu referencia y revisa tu correo en unos minutos. Si no recibes confirmación, contáctanos.',
                'timeout'
            );
        }

        async function poll() {
            if (!polling) return;
            if (receiptFetched && flowComplete) return;
            if (receiptFetched && !flowComplete) return;
            if (attempts >= maxAttempts) {
                showTimeout();
                return;
            }
            attempts++;
            try {
                const res = await fetch(pollUrl, { cache: 'no-store' });
                const data = await res.json();
                if (data.status === 'ok' && data.html_recibo) {
                    pendingReceiptHtml = data.html_recibo;
                    receiptFetched = true;
                    maybeRevealReceipt();
                    return;
                }
                if (data.status === 'error') {
                    showError(data.message, data.error_code || 'generic');
                    return;
                }
            } catch (e) { /* reintentar */ }
            setTimeout(poll, 4000);
        }

        if (polling) {
            startStageFlow();
            setTimeout(poll, 2000);
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
