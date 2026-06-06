<?php
declare(strict_types=1);

/**
 * Auditoría JSON en disco (accesorios): pending → processed | error.
 * Guarda todos los eventos OpenPay excepto verification (respaldo de seguridad).
 */
final class PaymentWebhookFileStorage
{
    private const SKIP_PERSIST_TYPES = ['verification'];

    /** @var list<string> */
    private const REPROCESS_DIRS = ['pending', 'error'];

    public static function shouldPersist(array $payload): bool
    {
        $type = strtolower(trim((string)($payload['type'] ?? $payload['event_type'] ?? '')));
        if ($type === '') {
            return true;
        }

        return !in_array($type, self::SKIP_PERSIST_TYPES, true);
    }

    public function store(array $payload, string $raw): string
    {
        if (!self::shouldPersist($payload)) {
            return '';
        }

        $filename = $this->filenameForPayload($payload);
        if ($filename === '') {
            return '';
        }

        $dir = $this->ensureDir('pending');
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $orderCode = $this->extractOrderCode($payload);
        $envelope = [
            'order_code' => $orderCode,
            'event_type' => (string)($payload['type'] ?? $payload['event_type'] ?? 'unknown'),
            'received_at' => date('c'),
            'forward_ok' => null,
            'payload' => $payload,
        ];

        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            paymentServerLog('WEBHOOK FILE write failed: ' . $path);
            throw new RuntimeException('No se pudo guardar webhook en disco');
        }

        paymentServerLog('WEBHOOK FILE pending: ' . $filename);

        return $filename;
    }

    public function markForwardResult(string $filename, bool $forwardOk, int $httpCode, string $body, string $fromDir = 'pending'): void
    {
        $filename = basename($filename);
        if ($filename === '') {
            return;
        }

        $fromDir = $this->normalizeDir($fromDir);
        $sourcePath = $this->path($fromDir, $filename);
        if (!file_exists($sourcePath)) {
            paymentServerLog('WEBHOOK FILE markForward: no existe ' . $fromDir . '/' . $filename);
            return;
        }

        $action = $this->extractBridgeAction($body);
        $saleApproved = $forwardOk && in_array($action, ['approved', 'sale_exists'], true);

        $data = $this->readJson($sourcePath);
        if ($data !== null) {
            $data['forward_ok'] = $forwardOk;
            $data['forward_http'] = $httpCode;
            $data['forward_at'] = date('c');
            $data['bridge_action'] = $action;
            $data['sale_approved'] = $saleApproved;
            if (!$forwardOk) {
                $data['forward_error'] = mb_substr($body, 0, 500);
            }
            file_put_contents($sourcePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        }

        if ($forwardOk) {
            $this->moveFile($filename, 'processed', $fromDir);
            return;
        }

        if ($fromDir !== 'error') {
            $this->moveFile($filename, 'error', $fromDir);
        }
    }

    /**
     * Reenvía un JSON en pending/ o error/ al principal y mueve la carpeta según el resultado.
     *
     * @return array{ok: bool, http: int, body: string, message: string, filename: string, from: string}
     */
    public function reprocessOne(string $subdir, string $filename, callable $forwardFn): array
    {
        $subdir = $this->normalizeDir($subdir);
        $filename = basename($filename);
        $path = $this->path($subdir, $filename);

        if (!file_exists($path)) {
            return [
                'ok' => false,
                'http' => 0,
                'body' => '',
                'message' => 'archivo no encontrado',
                'filename' => $filename,
                'from' => $subdir,
            ];
        }

        $data = $this->readJson($path);
        $raw = self::extractRawPayloadFromFile($data);
        if ($raw === null) {
            return [
                'ok' => false,
                'http' => 0,
                'body' => '',
                'message' => 'JSON inválido o sin payload OpenPay',
                'filename' => $filename,
                'from' => $subdir,
            ];
        }

        $workDir = $subdir;
        if ($subdir === 'error') {
            $this->moveFile($filename, 'pending', 'error');
            $workDir = 'pending';
        }

        paymentServerLog('WEBHOOK REPROCESS ' . $filename . ' from=' . $subdir);
        $forward = $forwardFn($raw);
        $this->markForwardResult(
            $filename,
            (bool)$forward['ok'],
            (int)$forward['http'],
            (string)$forward['body'],
            $workDir
        );

        return [
            'ok' => (bool)$forward['ok'],
            'http' => (int)$forward['http'],
            'body' => (string)$forward['body'],
            'message' => 'reenviado',
            'filename' => $filename,
            'from' => $subdir,
        ];
    }

    /**
     * @return list<string> nombres de archivo .json
     */
    public function listJsonFiles(string $subdir): array
    {
        $subdir = $this->normalizeDir($subdir);
        $dir = $this->ensureDir($subdir);
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $names = array_map(static fn(string $path): string => basename($path), $files);
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $subdirs
     * @return list<array{ok: bool, http: int, body: string, message: string, filename: string, from: string}>
     */
    public function reprocessAll(array $subdirs, callable $forwardFn): array
    {
        $results = [];
        foreach ($subdirs as $subdir) {
            foreach ($this->listJsonFiles($subdir) as $filename) {
                $results[] = $this->reprocessOne($subdir, $filename, $forwardFn);
            }
        }

        return $results;
    }

    /**
     * Acepta sobre (envelope con clave payload) o evento OpenPay en la raíz.
     */
    public static function extractRawPayloadFromFile(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }

        if (isset($data['payload']) && is_array($data['payload'])) {
            $encoded = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
            return $encoded === false ? null : $encoded;
        }

        if (isset($data['type']) || isset($data['event_type'])) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
            return $encoded === false ? null : $encoded;
        }

        return null;
    }

    private function extractBridgeAction(string $body): ?string
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }
        $result = $json['result'] ?? null;
        if (!is_array($result)) {
            return null;
        }

        return isset($result['action']) ? (string)$result['action'] : null;
    }

    private function moveFile(string $filename, string $toDir, string $fromDir = 'pending'): void
    {
        $fromDir = $this->normalizeDir($fromDir);
        $toDir = $this->normalizeDir($toDir);
        $this->ensureDir($toDir);
        $src = $this->path($fromDir, $filename);
        $dst = $this->path($toDir, $filename);

        if (!file_exists($src)) {
            return;
        }
        if (file_exists($dst)) {
            @unlink($dst);
        }
        if (@rename($src, $dst)) {
            paymentServerLog('WEBHOOK FILE moved to ' . $toDir . ': ' . $filename);
            return;
        }
        if (@copy($src, $dst) && @unlink($src)) {
            paymentServerLog('WEBHOOK FILE copied to ' . $toDir . ': ' . $filename);
            return;
        }
        paymentServerLog('WEBHOOK FILE move failed: ' . $src . ' -> ' . $dst);
    }

    private function filenameForPayload(array $payload): string
    {
        $orderCode = $this->extractOrderCode($payload);
        $eventType = (string)($payload['type'] ?? $payload['event_type'] ?? 'event');
        $safeType = preg_replace('/[^A-Za-z0-9._-]+/', '_', $eventType) ?: 'event';

        if ($orderCode !== '') {
            $safeOrder = preg_replace('/[^A-Za-z0-9._-]+/', '_', $orderCode) ?: 'order';

            return $safeOrder . '_' . $safeType . '.json';
        }

        $hash = substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: ''), 0, 10);

        return $safeType . '_' . date('YmdHis') . '_' . $hash . '.json';
    }

    private function extractOrderCode(array $payload): string
    {
        if (isset($payload['transaction']['order_id'])) {
            return trim((string)$payload['transaction']['order_id']);
        }
        if (isset($payload['data']['order_id'])) {
            return trim((string)$payload['data']['order_id']);
        }
        return '';
    }

    private function normalizeDir(string $dir): string
    {
        $dir = trim($dir, '/\\');
        if (!in_array($dir, ['pending', 'processed', 'error'], true)) {
            throw new InvalidArgumentException('Directorio webhook inválido: ' . $dir);
        }

        return $dir;
    }

    private function baseDir(): string
    {
        if (!defined('ROOT_PATH')) {
            throw new RuntimeException('ROOT_PATH no definido');
        }

        return rtrim((string)ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'openpay' . DIRECTORY_SEPARATOR . 'webhooks';
    }

    private function ensureDir(string $subdir): string
    {
        $subdir = $this->normalizeDir($subdir);
        $path = $this->baseDir() . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        @chmod($path, 0777);

        return $path;
    }

    private function path(string $subdir, string $filename): string
    {
        return $this->ensureDir($subdir) . DIRECTORY_SEPARATOR . basename($filename);
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
