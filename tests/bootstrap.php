<?php
declare(strict_types=1);

define('APP_RUNNING_TESTS', true);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Carga config completa (BD, constantes). Tests unitarios puros no lo necesitan;
 * los de integración llaman bootstrapApp().
 */
function bootstrapApp(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    require_once __DIR__ . '/../config/config.php';
    $loaded = true;
}
