<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/bootstrap/container.php';

$maint = AppContainer::get()->maintenance();
$isAdminSession = !empty($_SESSION['user_id']);

if ($maint->isPublicBlocked() && !$isAdminSession) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Mantenimiento</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-dark text-white d-flex align-items-center justify-content-center min-vh-100"><div class="text-center p-4">';
    echo '<h1 class="mb-3">🛠 Sitio en mantenimiento</h1>';
    echo '<p class="lead">' . htmlspecialchars($maint->getMaintenanceMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="dash.php" class="btn btn-outline-light mt-3">Acceso administradores</a></div></body></html>';
    exit;
}

require_once dirname(__DIR__) . '/bendecidos_public.php';
require_once dirname(__DIR__) . '/components/cr-numero-chip.php';
require_once dirname(__DIR__) . '/meta-pixel.php';

$bendecidosCards = edts_bendecidos_cards();
