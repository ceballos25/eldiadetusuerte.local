<?php
declare(strict_types=1);

ob_start();

use App\Shared\Http\Request;
use App\Shared\Http\Response;

/** @var \App\Shared\Routing\Router $router */
$router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$result = $router->dispatch(Request::fromGlobals());

if ($result === null) {
    Response::json(['success' => false, 'message' => 'Ruta no encontrada'], 404);
}
