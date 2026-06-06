<?php
declare(strict_types=1);

namespace App\Application\Webhook;

use App\Infrastructure\OpenPay\OpenPayHttpClient;
use App\Shared\Exception\DomainException;

/**
 * Registro y consulta de webhooks en la API de OpenPay.
 */
final class OpenPayWebhookRegistrationService
{
    public function __construct(
        private readonly OpenPayHttpClient $client
    ) {
    }

    /**
     * @param list<string>|null $eventTypes
     * @return array<string, mixed>
     */
    public function create(
        ?string $url = null,
        ?string $user = null,
        ?string $password = null,
        ?array $eventTypes = null
    ): array {
        $url ??= $this->defaultWebhookUrl();
        $user ??= $this->webhookUser();
        $password ??= $this->webhookPassword();
        $eventTypes ??= OpenPayWebhookEventTypes::forRegistration();

        if ($url === '' || $user === '') {
            throw new DomainException(
                'Configura OPENPAY_WEBHOOK_URL y OPENPAY_WEBHOOK_USER en .env',
                'WEBHOOK_CONFIG'
            );
        }

        $response = $this->client->request('POST', 'webhooks', [
            'url' => $url,
            'user' => $user,
            'password' => $password,
            'event_types' => array_values($eventTypes),
        ]);

        if (!$response['ok']) {
            throw new DomainException(
                'OpenPay no pudo crear el webhook: ' . $this->formatApiError($response),
                'OPENPAY_WEBHOOK_CREATE'
            );
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $response = $this->client->request('GET', 'webhooks');
        if (!$response['ok']) {
            throw new DomainException(
                'No se pudo listar webhooks: ' . $this->formatApiError($response),
                'OPENPAY_WEBHOOK_LIST'
            );
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $webhookId): array
    {
        $response = $this->client->request('GET', 'webhooks/' . rawurlencode($webhookId));
        if (!$response['ok']) {
            throw new DomainException(
                'Webhook no encontrado en OpenPay',
                'OPENPAY_WEBHOOK_GET'
            );
        }

        return is_array($response['data']) ? $response['data'] : [];
    }

    public function delete(string $webhookId): void
    {
        $response = $this->client->request('DELETE', 'webhooks/' . rawurlencode($webhookId));
        if (!$response['ok'] && $response['http'] !== 404) {
            throw new DomainException(
                'No se pudo eliminar el webhook: ' . $this->formatApiError($response),
                'OPENPAY_WEBHOOK_DELETE'
            );
        }
    }

    public function defaultWebhookUrl(): string
    {
        if (defined('OPENPAY_WEBHOOK_URL') && OPENPAY_WEBHOOK_URL !== '') {
            return (string)OPENPAY_WEBHOOK_URL;
        }

        return defined('BASE_URL') ? BASE_URL . '/openpay/webhook.php' : '';
    }

    public function webhookUser(): string
    {
        return defined('OPENPAY_WEBHOOK_USER') ? (string)OPENPAY_WEBHOOK_USER : '';
    }

    public function webhookPassword(): string
    {
        return defined('OPENPAY_WEBHOOK_PASSWORD') ? (string)OPENPAY_WEBHOOK_PASSWORD : '';
    }

    /**
     * @param array{ok: bool, http: int, data: mixed, raw: string} $response
     */
    private function formatApiError(array $response): string
    {
        $http = $response['http'] ?? 0;
        if (is_array($response['data'])) {
            $desc = $response['data']['description'] ?? $response['data']['error_code'] ?? null;

            return trim("HTTP {$http} " . (string)($desc ?? json_encode($response['data'])));
        }

        return 'HTTP ' . $http . ' ' . substr((string)($response['raw'] ?? ''), 0, 200);
    }

    public static function fromConfig(): self
    {
        return new self(OpenPayHttpClient::fromConfig());
    }
}
