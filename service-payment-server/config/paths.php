<?php

/**
 * Rutas para accesorios.caballosrevelo.com (docroot = public_html).
 *
 * $VH_ROOT/
 * ├── .env
 * ├── logs/
 * ├── data/
 * └── public_html/   ← contenido de service-payment-server/
 */

function resolveAppRoot(string $publicRoot): string
{
    return dirname(rtrim($publicRoot, '/\\'));
}

function resolveEnvPath(string $publicRoot): string
{
    $publicRoot = rtrim($publicRoot, '/\\');
    $candidates = [
        resolveAppRoot($publicRoot) . DIRECTORY_SEPARATOR . '.env',
        resolveAppRoot($publicRoot) . DIRECTORY_SEPARATOR . '.env-cr',
        $publicRoot . DIRECTORY_SEPARATOR . '.env',
    ];

    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && is_readable($real)) {
            return $real;
        }
    }

    throw new Exception(
        'No se encontró .env legible. Probado: ' . implode(', ', $candidates)
    );
}

function bootstrapPrivateStorage(string $publicRoot): void
{
    $publicRoot = rtrim($publicRoot, '/\\');
    $rootReal = realpath($publicRoot);

    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', $rootReal !== false ? $rootReal : $publicRoot);
    }
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', resolveAppRoot((string)ROOT_PATH));
    }
    if (!defined('LOG_PATH')) {
        define('LOG_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'logs');
    }
    if (!defined('DATA_PATH')) {
        define('DATA_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'data');
    }
    if (!defined('OPENPAY_WEBHOOKS_PATH')) {
        define(
            'OPENPAY_WEBHOOKS_PATH',
            (string)ROOT_PATH . DIRECTORY_SEPARATOR . 'openpay' . DIRECTORY_SEPARATOR . 'webhooks'
        );
    }

    foreach ([LOG_PATH] as $dir) {
        ensureWritableDirectory($dir, 0755);
    }

    foreach (['pending', 'processed', 'error'] as $subdir) {
        $dir = OPENPAY_WEBHOOKS_PATH . DIRECTORY_SEPARATOR . $subdir;
        ensureWritableDirectory($dir, 0777);
        @chmod($dir, 0777);
    }
}

function ensureWritableDirectory(string $path, int $mode = 0777): bool
{
    if (!is_dir($path)) {
        if (!@mkdir($path, $mode, true) && !is_dir($path)) {
            error_log('[paths] No se pudo crear: ' . $path);
            return false;
        }
    } elseif ($mode !== 0777) {
        @chmod($path, $mode);
    }

    return is_writable($path) || @chmod($path, $mode);
}

function appLogPath(string $filename): string
{
    ensureWritableDirectory(LOG_PATH);
    return LOG_PATH . DIRECTORY_SEPARATOR . ltrim($filename, '/\\');
}

function writeAppLog(string $filename, string $message, bool $withTimestamp = true): bool
{
    $file = appLogPath($filename);
    $line = ($withTimestamp ? '[' . date('Y-m-d H:i:s') . '] ' : '') . $message . PHP_EOL;
    return @file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
}
