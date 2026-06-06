<?php
declare(strict_types=1);

ob_start();

use App\Shared\Http\Request;
use App\Shared\Http\Response;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

// Lectura pública de settings para landing/index (sin pasar por RBAC).
if ($method !== 'POST' || $action === 'obtener') {
    require_once dirname(__DIR__, 2) . '/config/config.php';
    require_once dirname(__DIR__, 2) . '/controllers/settings.controller.php';
    Response::json(SettingsController::obtenerSettings());
}

/** @var \App\Shared\Routing\Router $router */
$router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$result = $router->dispatch(Request::fromGlobals());

if ($result === null) {
    Response::json(['success' => false, 'message' => 'Ruta no encontrada'], 404);
}