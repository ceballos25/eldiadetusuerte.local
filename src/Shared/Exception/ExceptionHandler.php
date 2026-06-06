<?php
declare(strict_types=1);

namespace App\Shared\Exception;

final class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handle(\Throwable $e): void
    {
        self::log($e);

        if (self::isJsonRequest()) {
            $status = $e instanceof DomainException ? 422 : 500;
            $payload = [
                'success' => false,
                'message' => $e instanceof DomainException ? $e->getMessage() : 'Error interno del servidor',
            ];
            if (defined('DEBUG_MODE') && DEBUG_MODE && !($e instanceof DomainException)) {
                $payload['debug'] = $e->getMessage();
            }
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            throw $e;
        }

        http_response_code(500);
        echo 'Error interno del servidor';
        exit;
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    private static function log(\Throwable $e): void
    {
        if (function_exists('writeAppLog')) {
            writeAppLog('errors.log', sprintf(
                '%s in %s:%d — %s',
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }
    }

    private static function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest'
            || str_contains($uri, '.ajax.php');
    }
}
