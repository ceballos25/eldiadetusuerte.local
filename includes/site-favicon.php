<?php
declare(strict_types=1);

/**
 * URL del favicon del sitio (site_images.favicon o fallback CDN).
 */
function cr_site_favicon_url(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $default = edts_cdn('images/logos/logo.ico');

    try {
        if (class_exists(\App\Infrastructure\Repository\PdoSiteImageRepository::class)) {
            $row = (new \App\Infrastructure\Repository\PdoSiteImageRepository())->findByKey('favicon');
            if ($row) {
                $candidate = trim((string)($row['url_image'] ?? ''));
                if ($candidate === '') {
                    $candidate = trim((string)($row['fallback_url'] ?? ''));
                }
                if ($candidate !== '') {
                    $cached = $candidate;
                    return $cached;
                }
            }
        }
    } catch (Throwable) {
        // Fallback si la BD no está disponible.
    }

    $cached = $default;
    return $cached;
}

function cr_site_favicon_href(): string
{
    return htmlspecialchars(cr_site_favicon_url(), ENT_QUOTES, 'UTF-8');
}
