<?php
declare(strict_types=1);

namespace App\Shared\Validation;

final class ImageUrlValidator
{
    public static function isValidFormat(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array(strtolower((string)$scheme), ['http', 'https'], true);
    }

    public static function isReachable(string $url, int $timeoutSeconds = 5): bool
    {
        if (!self::isValidFormat($url)) {
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'EDTS-ImageValidator/1.0',
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }
}
