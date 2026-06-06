<?php
declare(strict_types=1);

namespace App\Shared\Audit;

final class AuditLogger
{
    public function log(string $event, array $context = []): void
    {
        $file = \appLogPath('audit.log');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            return;
        }
        clearstatcache(true, $file);
        if (file_exists($file)) {
            if (!is_writable($file)) {
                return;
            }
        } elseif (!is_writable($dir)) {
            return;
        }

        $line = json_encode([
            'timestamp' => date('c'),
            'event' => $event,
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_role' => $_SESSION['user_role'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'context' => $this->sanitize($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }

    private function sanitize(array $context): array
    {
        $sensitive = ['password', 'token', 'csrf_token', 'email_customer', 'phone_customer'];
        foreach ($sensitive as $field) {
            if (array_key_exists($field, $context)) {
                $context[$field] = '***';
            }
        }

        return $context;
    }
}
