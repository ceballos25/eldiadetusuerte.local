<?php
declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Shared\Http\Response;

final class RateLimitMiddleware
{
    private const DEFAULT_MAX = 60;
    private const DEFAULT_WINDOW = 60;

    public function check(string $key, int $maxAttempts = self::DEFAULT_MAX, int $windowSeconds = self::DEFAULT_WINDOW): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $bucket = preg_replace('/[^a-z0-9_-]/i', '_', $key . '_' . $ip);
        $file = appDataPath('cache/ratelimit_' . $bucket . '.json');

        $now = time();
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];

        if (is_file($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        if ($now > (int)($data['reset'] ?? 0)) {
            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        }

        $data['count'] = (int)($data['count'] ?? 0) + 1;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        if ($data['count'] > $maxAttempts) {
            Response::json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.',
            ], 429);
        }
    }
}
