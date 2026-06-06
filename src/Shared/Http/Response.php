<?php
declare(strict_types=1);

namespace App\Shared\Http;

final class Response
{
    public static function json(array $payload, int $statusCode = 200): never
    {
        $discarded = '';
        while (ob_get_level() > 0) {
            $discarded .= ob_get_clean();
        }
        $discarded = trim($discarded);
        if ($discarded !== '') {
            error_log('[Response::json] Salida previa descartada (' . strlen($discarded) . " bytes):\n" . $discarded);
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            error_log('[Response::json] json_encode falló: ' . json_last_error_msg());
            http_response_code(500);
            echo '{"success":false,"message":"Error interno al codificar respuesta"}';
            exit;
        }

        echo $encoded;
        exit;
    }
}
