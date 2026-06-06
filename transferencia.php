<?php
/**
 * Estado de transferencia — confirmación y comprobante.
 * Consulta en segundo plano (?poll=1) sin recargar toda la página.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/transfersController.php';
require_once __DIR__ . '/controllers/ventas.controller.php';
require_once __DIR__ . '/includes/meta-pixel.php';

function transferenciaSuccessIconSvg(): string
{
    return '<svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
}

/** @return array{status: string, message: string, html_recibo: string, error_code: string} */
function transferenciaBuildStatusResponse(string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return [
            'status' => 'error',
            'message' => 'Enlace no válido.',
            'html_recibo' => '',
            'error_code' => 'invalid',
        ];
    }

    $transfer = TransfersController::obtenerPorCode($code);
    if (!$transfer) {
        return [
            'status' => 'error',
            'message' => 'Código no válido o expirado.',
            'html_recibo' => '',
            'error_code' => 'not_found',
        ];
    }

    $st = (int)($transfer['status_transfer'] ?? 0);

    if ($st === 3) {
        return [
            'status' => 'error',
            'message' => 'Comprobante no validado. Escríbenos con tu código.',
            'html_recibo' => '',
            'error_code' => 'rejected',
        ];
    }

    if ($st === 2) {
        $sale = Db::fetchOne(
            'SELECT id_sale, code_sale, total_sale, quantity_sale FROM sales WHERE code_sale = :c ORDER BY id_sale DESC LIMIT 1',
            [':c' => $code]
        );
        if ($sale) {
            $detalle = VentasController::obtenerDetalleVenta((int)$sale->id_sale);
            if (!empty($detalle['success']) && !empty($detalle['html_recibo'])) {
                return [
                    'status' => 'ok',
                    'message' => 'Pago confirmado.',
                    'html_recibo' => (string)$detalle['html_recibo'],
                    'error_code' => '',
                ];
            }
        }

        return [
            'status' => 'pending',
            'message' => 'Preparando comprobante…',
            'html_recibo' => '',
            'error_code' => '',
        ];
    }

    return [
        'status' => 'pending',
        'message' => '',
        'html_recibo' => '',
        'error_code' => '',
    ];
}

if (isset($_GET['poll']) && $_GET['poll'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        transferenciaBuildStatusResponse((string)($_GET['code'] ?? '')),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$settings = TransfersController::obtenerSettings();
$whatsappUrl = $settings['whatsapp_chat_url'] ?? 'https://api.whatsapp.com/send/?phone=57';
$code = trim((string)($_GET['code'] ?? ''));
$response = transferenciaBuildStatusResponse($code);
$status = $response['status'];
$htmlRecibo = $response['html_recibo'];
$statusMessage = $response['message'];
$errorCode = $response['error_code'];
$showRecibo = $status === 'ok' && $htmlRecibo !== '';
$isError = $status === 'error';
$isPending = $status === 'pending';

$errorTitles = [
    'rejected' => 'Pago no validado',
    'not_found' => 'Código no encontrado',
    'invalid' => 'Enlace no válido',
    'generic' => 'No pudimos consultar',
];
$errorTitle = $errorTitles[$errorCode] ?? $errorTitles['generic'];

$siteName = defined('SITE_NAME') && SITE_NAME ? (string)SITE_NAME : 'El Día de Tu Suerte';
$homeUrl = defined('BASE_URL') && BASE_URL ? rtrim((string)BASE_URL, '/') : '/';

$pageTitle = $showRecibo
    ? 'Pago confirmado — ' . $siteName
    : ($isError ? $errorTitle . ' — ' . $siteName : 'Validando tu pago — ' . $siteName);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f4f4">
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
            --cr-dark: #000000;
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
        @media (min-width: 480px) { .card-body { padding: 2rem 1.75rem 1.75rem; } }
        .icon-wrap {
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            background: var(--cr-gold-muted);
            border: 2px solid var(--cr-gold);
        }
        .icon-wrap.success { background: var(--cr-accent-green-soft); border-color: var(--cr-accent-green); }
        .icon-wrap.error { background: var(--cr-accent-red-soft); border-color: var(--cr-accent-red); }
        .icon-wrap svg { width: 28px; height: 28px; }
        .icon-wrap .accent { stroke: var(--cr-gold-dark); fill: none; }
        .icon-wrap.success .accent { stroke: var(--cr-accent-green); }
        .icon-wrap.error .accent { stroke: var(--cr-accent-red); }
        h1 { font-size: 1.15rem; font-weight: 700; text-align: center; line-height: 1.4; margin-bottom: 0.65rem; color: var(--cr-dark); }
        .subtitle { text-align: center; color: var(--cr-text-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.25rem; }
        .progress-block { margin-bottom: 1rem; }
        .txn-flow { position: relative; padding: 0.25rem 0.15rem 0; }
        .txn-rail {
            position: absolute; top: 19px;
            left: calc(16.66% + 14px); right: calc(16.66% + 14px);
            height: 5px;
            background: linear-gradient(90deg, #ececec, #e0e0e0, #ececec);
            border-radius: 999px; overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
        }
        .txn-rail__fill {
            position: relative; height: 100%; width: 12%;
            border-radius: 999px;
            background: linear-gradient(90deg, #2aad62, var(--cr-accent-green), #6ee09a, #b8f5d4, var(--cr-accent-green));
            background-size: 220% 100%;
            animation: greenStream 2.2s linear infinite;
            transition: width 1.85s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 0 10px rgba(57, 203, 127, 0.55), 0 0 3px rgba(57, 203, 127, 0.35);
        }
        .txn-rail__fill.is-active::after {
            content: ''; position: absolute; top: 0; right: 0; width: 28px; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7));
            animation: headGlow 1.4s ease-in-out infinite;
        }
        @keyframes greenStream { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
        @keyframes headGlow { 0%, 100% { opacity: 0.4; } 50% { opacity: 1; } }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem; position: relative; z-index: 1; }
        .step { text-align: center; padding: 0 0.1rem; }
        .step-dot {
            width: 38px; height: 38px; margin: 0 auto 0.55rem; border-radius: 50%;
            background: var(--cr-surface); border: 2px solid var(--cr-border);
            display: flex; align-items: center; justify-content: center;
            transition: border-color 0.45s ease, background 0.45s ease, box-shadow 0.45s ease, transform 0.45s ease;
        }
        .step-dot svg { width: 17px; height: 17px; stroke: #bbb; fill: none; }
        .step-text { font-size: 0.7rem; color: #aaa; font-weight: 500; line-height: 1.4; }
        .step.active .step-dot {
            border-color: var(--cr-gold);
            background: linear-gradient(145deg, #fff8e8, var(--cr-gold-muted));
            box-shadow: 0 0 0 4px rgba(255, 188, 66, 0.22);
            transform: scale(1.06);
            animation: stepPulse 2s ease-in-out infinite;
        }
        .step.active .step-dot svg { stroke: var(--cr-gold-dark); }
        .step.active .step-text { color: var(--cr-gold-dark); font-weight: 700; }
        .step.done .step-dot { border-color: var(--cr-accent-green); background: var(--cr-accent-green); }
        .step.done .step-dot svg { stroke: #fff; }
        .step.done .step-text { color: var(--cr-accent-green); font-weight: 600; }
        @keyframes stepPulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(255, 188, 66, 0.18); }
            50% { box-shadow: 0 0 0 7px rgba(255, 188, 66, 0.12); }
        }
        .order-ref {
            text-align: center; font-size: 0.85rem; color: var(--cr-text-muted);
            margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--cr-border);
        }
        .order-ref code {
            display: inline-block; margin-top: 0.35rem; font-size: 0.8rem; font-weight: 600;
            background: var(--cr-gold-muted); padding: 0.35rem 0.65rem; border-radius: 8px;
            color: var(--cr-dark); border: 1px solid rgba(var(--cr-gold-rgb), 0.35);
            word-break: break-all;
        }
        .btn-row { display: flex; flex-direction: column; gap: 0.65rem; margin-top: 1.15rem; }
        .btn-gold {
            display: block; width: 100%; padding: 0.85rem 1rem; text-align: center;
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
            color: var(--cr-dark); background: var(--cr-gold); border: none;
            border-radius: var(--cr-radius-md); cursor: pointer; transition: background 0.2s;
        }
        .btn-gold:hover { background: var(--cr-gold-dark); color: var(--cr-dark); }
        .btn-outline {
            display: block; width: 100%; padding: 0.85rem 1rem; text-align: center;
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
            color: var(--cr-dark); background: transparent;
            border: 2px solid var(--cr-border); border-radius: var(--cr-radius-md);
            cursor: pointer; transition: border-color 0.2s, background 0.2s;
        }
        .btn-outline:hover { border-color: var(--cr-gold-dark); background: var(--cr-gold-muted); }
        .btn-whatsapp {
            display: block; width: 100%; padding: 0.85rem 1rem; text-align: center;
            text-decoration: none; font-weight: 600; font-size: 0.9rem; color: #fff !important;
            background: linear-gradient(45deg, #25D366, #128C7E);
            border: none; border-radius: var(--cr-radius-md); cursor: pointer;
        }
        .btn-whatsapp:hover { filter: brightness(1.05); color: #fff !important; }
        .recibo-wrap { margin-top: 1rem; animation: fadeUp 0.5s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .recibo-box {
            background: var(--cr-surface); border: 1px solid var(--cr-border);
            border-radius: var(--cr-radius-md); padding: 0.75rem; overflow-x: auto;
        }
        .recibo-box img { max-width: 100%; height: auto; display: block; }
        .footer-note { text-align: center; font-size: 0.85rem; color: var(--cr-text-muted); margin-top: 1rem; line-height: 1.55; }
        .wait-hint { text-align: center; font-size: 0.82rem; color: var(--cr-text-muted); margin-top: 0.85rem; line-height: 1.5; }
        .secure-badge {
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
            margin-top: auto; padding-top: 1.25rem; font-size: 0.75rem; color: var(--cr-text-muted);
        }
        .hidden { display: none !important; }
    </style>
    <?php edts_meta_pixel_head(); ?>
</head>
<body>
    <header class="site-header">
        <div class="site-header__name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></div>
    </header>

    <div class="shell <?= $showRecibo ? 'shell--wide' : '' ?>" id="appShell">
        <?php if ($isError): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap error">
                    <svg class="accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="subtitle"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($code !== ''): ?>
                <div class="order-ref">Tu referencia<br><code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code></div>
                <?php endif; ?>
                <div class="btn-row">
        
                    <a class="btn-gold" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
                </div>
            </div>
        </div>

        <?php elseif ($showRecibo): ?>
        <div class="card">
            <div class="card-body">
                <div class="icon-wrap success"><?= transferenciaSuccessIconSvg() ?></div>
                <h1>¡Pago confirmado!</h1>
                <p class="subtitle">Aquí está tu comprobante.</p>
            </div>
        </div>
        <div class="recibo-wrap">
            <div class="recibo-box"><?= $htmlRecibo ?></div>
            <p class="footer-note">Guarda captura. También llegó a tu correo.</p>
            <div class="btn-row">
                <a class="btn-gold" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            </div>
        </div>

        <?php else: ?>
        <div class="card" id="stateCard">
            <div class="card-body">
                <div class="icon-wrap hidden" id="stateIconWrap"></div>
                <h1 id="stateTitle">Validando tu pago</h1>

                <div class="progress-block" id="progressBlock">
                    <div class="txn-flow">
                        <div class="txn-rail" aria-hidden="true">
                            <div class="txn-rail__fill is-active" id="progressFill"></div>
                        </div>
                        <div class="steps">
                            <div class="step active" id="step1">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                                </div>
                                <p class="step-text">Comprobante<br>recibido</p>
                            </div>
                            <div class="step" id="step2">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                </div>
                                <p class="step-text">Revisando<br>tu pago</p>
                            </div>
                            <div class="step" id="step3">
                                <div class="step-dot">
                                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                </div>
                                <p class="step-text">Preparando<br>pedido</p>
                            </div>
                        </div>
                    </div>
                    <p class="wait-hint">Pulsa <strong>Actualizar</strong> para ver si ya fue aprobado.</p>
                </div>

                <?php if ($code !== ''): ?>
                <div class="order-ref">
                    Referencia<br>
                    <code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <?php endif; ?>

                <div class="btn-row" id="actionButtons">
                    <button type="button" class="btn-outline" id="btnRefresh" onclick="actualizarEstado()">Actualizar</button>
                    <button type="button" class="btn-whatsapp" id="btnWhatsapp" onclick="enviarValidacion()">WhatsApp</button>
                </div>
            </div>
        </div>
        <div id="reciboContainer" class="recibo-wrap hidden"></div>
        <?php endif; ?>
    </div>

    <div class="secure-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Conexión segura
    </div>

    <script>
    const DATA = {
        code: <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>,
        whatsappUrl: <?= json_encode($whatsappUrl, JSON_UNESCAPED_UNICODE) ?>,
        homeUrl: <?= json_encode($homeUrl, JSON_UNESCAPED_UNICODE) ?>,
        siteName: <?= json_encode($siteName, JSON_UNESCAPED_UNICODE) ?>,
        successIconHtml: <?= json_encode(transferenciaSuccessIconSvg(), JSON_UNESCAPED_UNICODE) ?>
    };

    function enviarValidacion() {
        const texto = '¡Hola! 👋 Acabo de realizar una compra por transferencia.\n\n📋 Código: ' + DATA.code + '\n\nPor favor, confirmen y aprueben mi compra. 🙂';
        abrirWA(texto);
    }
    function enviarSoporte() {
        const texto = 'Hola 👋\n\nMi código ' + DATA.code + ' fue rechazado. Necesito ayuda con mi pago 🙏';
        abrirWA(texto);
    }
    function abrirWA(texto) {
        window.open(DATA.whatsappUrl + '&text=' + encodeURIComponent(texto), '_blank');
    }

    <?php if ($isPending && $code !== ''): ?>
    (function () {
        const pollUrl = 'transferencia.php?poll=1&code=' + encodeURIComponent(DATA.code);
        const stepDurationMs = 4200;
        let stepPhase = 0;
        let stageDone = false;
        let revealing = false;

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const progressFill = document.getElementById('progressFill');
        const stateTitle = document.getElementById('stateTitle');
        const progressBlock = document.getElementById('progressBlock');
        const stateIconWrap = document.getElementById('stateIconWrap');
        const reciboContainer = document.getElementById('reciboContainer');
        const appShell = document.getElementById('appShell');
        const btnRefresh = document.getElementById('btnRefresh');
        const actionButtons = document.getElementById('actionButtons');

        const stepBarPct = [12, 50, 100];
        const stepDoneIcon = '<svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';

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
            if (progressFill) progressFill.style.width = stepBarPct[phase] + '%';
        }

        function startStageFlow() {
            setStepPhase(0);
            setTimeout(() => {
                if (stageDone) return;
                setStepPhase(1);
            }, stepDurationMs);
        }

        function showSuccess(html) {
            stageDone = true;
            progressBlock.classList.add('hidden');
            if (progressFill) progressFill.classList.remove('is-active');
            if (stateIconWrap) {
                stateIconWrap.classList.remove('hidden');
                stateIconWrap.className = 'icon-wrap success';
                stateIconWrap.innerHTML = DATA.successIconHtml;
            }
            stateTitle.textContent = '¡Pago confirmado!';
            if (actionButtons) actionButtons.classList.add('hidden');
            appShell.classList.add('shell--wide');
            reciboContainer.innerHTML = '<div class="recibo-box">' + html + '</div><p class="footer-note">Guarda captura. También llegó a tu correo.</p><div class="btn-row"><a class="btn-gold" href="' + DATA.homeUrl + '">Volver al sitio</a></div>';
            reciboContainer.classList.remove('hidden');
            document.title = 'Pago confirmado — ' + DATA.siteName;
        }

        function showRejected() {
            stageDone = true;
            window.location.href = 'transferencia.php?code=' + encodeURIComponent(DATA.code) + '&_=' + Date.now();
        }

        async function poll() {
            if (stageDone || revealing) return;

            if (btnRefresh) {
                btnRefresh.disabled = true;
                btnRefresh.textContent = 'Consultando…';
            }

            try {
                const res = await fetch(pollUrl, { cache: 'no-store' });
                const data = await res.json();
                if (data.status === 'ok' && data.html_recibo) {
                    revealing = true;
                    setStepPhase(2);
                    if (btnRefresh) {
                        btnRefresh.disabled = true;
                        btnRefresh.textContent = 'Preparando…';
                    }
                    setTimeout(() => showSuccess(data.html_recibo), 1800);
                    return;
                }
                if (data.status === 'error') {
                    showRejected();
                    return;
                }
            } catch (e) {
                if (btnRefresh) {
                    btnRefresh.disabled = false;
                    btnRefresh.textContent = 'Reintentar';
                }
            }

            if (btnRefresh) {
                btnRefresh.disabled = false;
                btnRefresh.textContent = 'Actualizar';
            }
        }

        window.actualizarEstado = poll;

        startStageFlow();
    })();
    <?php endif; ?>
    </script>
</body>
</html>
