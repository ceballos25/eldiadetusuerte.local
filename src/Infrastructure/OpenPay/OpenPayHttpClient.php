<?php
declare(strict_types=1);

namespace App\Infrastructure\OpenPay;

/**
 * Cliente HTTP mínimo para la API REST de OpenPay Colombia.
 */
final class OpenPayHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $privateKey
    ) {
    }

    /**
     * @return array{ok: bool, http: int, data: mixed, raw: string}
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->privateKey . ':'),
            ],
            CURLOPT_TIMEOUT => 30,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);
        $raw = (string)curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === '' && $err !== '') {
            return ['ok' => false, 'http' => 0, 'data' => ['error' => $err], 'raw' => ''];
        }

        $decoded = json_decode($raw, true);

        return [
            'ok' => $http >= 200 && $http < 300,
            'http' => $http,
            'data' => $decoded ?? $raw,
            'raw' => $raw,
        ];
    }

    public static function fromConfig(): self
    {
        if (!defined('OPENPAY_URL') || !defined('OPENPAY_PRIVATE_KEY')) {
            throw new \RuntimeException('OpenPay no está configurado');
        }

        return new self((string)OPENPAY_URL, (string)OPENPAY_PRIVATE_KEY);
    }
}
