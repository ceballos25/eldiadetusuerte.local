<?php
declare(strict_types=1);

/**
 * Helpers de marca El Día de Tu Suerte (CDN, textos, URLs).
 */
function edts_cdn(string $path = ''): string
{
    $base = defined('CDN_URL') ? CDN_URL : 'https://cdn.eldiadetusuerte.com';
    $base = rtrim($base, '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base : $base . '/' . $path;
}

/** Logo oficial del sitio (assets/logo.png). */
function edts_logo_url(): string
{
    $assets = defined('ASSETS_URL') ? rtrim(ASSETS_URL, '/') : '/assets';

    return $assets . '/logo.png';
}

function edts_site_display_name(): string
{
    return defined('SITE_DISPLAY_NAME') ? SITE_DISPLAY_NAME : 'El Día de Tu Suerte Oficial';
}

function edts_site_name(): string
{
    return defined('SITE_NAME') && SITE_NAME !== '' ? SITE_NAME : 'El Día de Tu Suerte';
}

function edts_site_url(): string
{
    return defined('BASE_URL') ? BASE_URL : 'https://eldiadetusuerte.com';
}

/** URL pública de producción (SEO, OG, enlaces compartidos). */
function edts_public_url(): string
{
    if (defined('PRODUCTION_SITE_URL') && PRODUCTION_SITE_URL !== '') {
        return PRODUCTION_SITE_URL;
    }

    return 'https://eldiadetusuerte.com';
}

function edts_whatsapp_default(): string
{
    return '573171684127';
}

function edts_contact_email(): string
{
    return defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : 'info@eldiadetusuerte.com';
}
