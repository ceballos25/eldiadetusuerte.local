<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MetaConversionsApi
{
    private const DEFAULT_API_VERSION = 'v20.0';
    private const FBC_MAX_AGE_SECONDS = 90 * 24 * 60 * 60;

    /** Eventos permitidos en navegador (Pixel / meta.ajax.php). */
    public const BROWSER_TRACK_EVENTS = ['PageView'];

    /** Eventos enviados por CAPI en servidor. */
    public const CAPI_TRACK_EVENTS = ['PageView', 'Purchase'];

    /** @var list<string> */
    public const STANDARD_EVENTS = [
        'PageView',
        'Purchase',
    ];

    public static function isAllowedTrackEvent(string $eventName): bool
    {
        return in_array(trim($eventName), self::BROWSER_TRACK_EVENTS, true);
    }

    public static function isStandardEvent(string $eventName): bool
    {
        return in_array(trim($eventName), self::CAPI_TRACK_EVENTS, true);
    }

    public static function eventId(string $eventName, ?string $reference = null): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $eventName) ?: 'event');
        $suffix = $reference !== null && trim($reference) !== ''
            ? preg_replace('/[^A-Za-z0-9\-]+/', '-', trim($reference))
            : bin2hex(random_bytes(8));

        return trim($base . '-' . $suffix, '-');
    }

    /**
     * @param array<string, mixed> $customData
     * @param array<string, mixed> $userData
     * @return array{success: bool, event_id: string, sent: bool}
     */
    public static function trackStandardEvent(
        string $eventName,
        array $customData = [],
        ?string $eventRef = null,
        array $userData = [],
        bool $includeRequestUserData = true
    ): array {
        $eventName = trim($eventName);
        if (!self::isStandardEvent($eventName)) {
            return ['success' => false, 'event_id' => '', 'sent' => false];
        }

        $customData = self::sanitizeCustomData($customData);
        $eventRef = self::sanitizeEventReference($eventRef);

        $eventId = self::eventId($eventName, $eventRef);
        $sent = self::sendEvent($eventName, $customData, $userData, $eventId, null, $includeRequestUserData);

        return ['success' => true, 'event_id' => $eventId, 'sent' => $sent];
    }

    public static function sendPageView(?string $eventId = null): bool
    {
        return self::sendEvent('PageView', [], [], $eventId);
    }

    /**
     * @param array<string, mixed>|object $sale
     * @param array<string, mixed> $extraUserData
     */
    public static function sendPurchase(array|object $sale, ?string $eventId = null, array $extraUserData = []): bool
    {
        $sale = is_object($sale) ? get_object_vars($sale) : $sale;
        $idSale = (int)($sale['id_sale'] ?? 0);
        $codeSale = (string)($sale['code_sale'] ?? '');
        $customData = self::buildPurchaseCustomDataFromSale($sale);

        $userData = array_merge(
            self::userDataFromCustomer($sale),
            self::userDataFromStoredMeta($extraUserData)
        );

        return self::sendEvent(
            'Purchase',
            $customData,
            array_filter($userData),
            $eventId ?? self::purchaseEventId($idSale, $codeSale),
            null,
            false
        );
    }

    public static function purchaseEventId(int $idSale, string $codeSale = ''): string
    {
        $reference = $idSale > 0
            ? (string)$idSale
            : (trim($codeSale) !== '' ? trim($codeSale) : null);

        return self::eventId('Purchase', $reference);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPurchaseCustomData(string $orderId, float $value, int $quantity, int $idSale = 0): array
    {
        return self::buildPurchaseCustomDataFromSale([
            'code_sale' => $orderId,
            'total_sale' => $value,
            'quantity_sale' => $quantity,
            'id_sale' => $idSale,
        ]);
    }

    /**
     * Formato Meta estándar: solo currency + value.
     *
     * @param array<string, mixed> $sale
     * @return array{currency: string, value: float}
     */
    public static function buildPurchaseCustomDataFromSale(array $sale): array
    {
        $value = (float)($sale['total_sale'] ?? $sale['value'] ?? 0);

        return self::sanitizePurchaseCustomData([
            'currency' => 'COP',
            'value' => $value,
        ]);
    }

    /**
     * @return array{currency: string, value: float}
     */
    public static function sanitizePurchaseCustomData(array $customData): array
    {
        $currency = strtoupper(trim((string)($customData['currency'] ?? 'COP')));

        return [
            'currency' => $currency !== '' ? $currency : 'COP',
            'value' => round((float)($customData['value'] ?? 0), 2),
        ];
    }

    /**
     * Meta del checkout (IP, user-agent, cookies Meta) para CAPI Purchase posterior.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function buildCheckoutMetaContext(array $input = []): array
    {
        $ip = self::normalizeClientIp($input['client_ip_address'] ?? null) ?? self::clientIp();
        $ua = trim((string)($input['client_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));

        return array_filter([
            'fbp' => trim((string)($input['meta_fbp'] ?? $input['fbp'] ?? '')),
            'fbc' => trim((string)($input['meta_fbc'] ?? $input['fbc'] ?? '')),
            'client_ip_address' => $ip,
            'client_user_agent' => $ua !== '' ? $ua : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    public static function extractStoredMeta(array $row): array
    {
        foreach (['openpay_response_payment_backup', 'meta_transfer'] as $field) {
            $raw = (string)($row[$field] ?? '');
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (isset($decoded['meta']) && is_array($decoded['meta'])) {
                return $decoded['meta'];
            }

            if (isset($decoded['client_ip_address']) || isset($decoded['fbp'])) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    public static function userDataFromPaymentBackup(array $backup): array
    {
        return self::userDataFromStoredMeta(self::extractStoredMeta($backup));
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function userDataFromStoredMeta(array $meta): array
    {
        return array_filter([
            'fbp' => trim((string)($meta['fbp'] ?? '')) ?: null,
            'fbc' => trim((string)($meta['fbc'] ?? '')) ?: null,
            'client_ip_address' => self::normalizeClientIp($meta['client_ip_address'] ?? null),
            'client_user_agent' => trim((string)($meta['client_user_agent'] ?? '')) ?: null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $sale
     */
    public static function logPurchaseNotSent(array $sale, string $detail): void
    {
        self::logDebugResult('NO_ENVIADO', [
            'event_name' => 'Purchase',
            'event_id' => self::eventId('Purchase', (string)($sale['id_sale'] ?? $sale['code_sale'] ?? '')),
            'custom_data' => self::sanitizePurchaseCustomData([
                'currency' => 'COP',
                'value' => (float)($sale['total_sale'] ?? $sale['value'] ?? 0),
            ]),
        ], $detail);
    }

    /**
     * @param array<string, mixed> $customData
     * @return array<string, mixed>
     */
    public static function sanitizeCustomData(array $customData): array
    {
        unset(
            $customData['content_name'],
            $customData['content_names'],
            $customData['search_string']
        );

        if (isset($customData['contents']) && is_array($customData['contents'])) {
            $contents = [];
            foreach ($customData['contents'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = preg_replace('/[^0-9]/', '', (string)($item['id'] ?? '')) ?? '';
                if ($id === '') {
                    continue;
                }
                $row = [
                    'id' => $id,
                    'quantity' => max(1, (int)($item['quantity'] ?? 1)),
                ];
                if (isset($item['item_price'])) {
                    $row['item_price'] = round((float)$item['item_price'], 2);
                }
                $contents[] = $row;
            }
            if ($contents === []) {
                unset($customData['contents']);
            } else {
                $customData['contents'] = $contents;
            }
        }

        foreach (['content_category'] as $key) {
            if (!isset($customData[$key])) {
                continue;
            }
            $value = strtolower(trim((string)$customData[$key]));
            if ($value === '' || self::containsBlockedMarketingTerms($value)) {
                unset($customData[$key]);
            }
        }

        if (isset($customData['content_ids']) && is_array($customData['content_ids'])) {
            $ids = [];
            foreach ($customData['content_ids'] as $id) {
                $clean = preg_replace('/[^0-9\-]/', '', (string)$id) ?? '';
                if ($clean !== '') {
                    $ids[] = $clean;
                }
            }
            if ($ids === []) {
                unset($customData['content_ids']);
            } else {
                $customData['content_ids'] = array_values(array_unique($ids));
            }
        }

        return $customData;
    }

    public static function sanitizeEventReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $reference = strtolower(trim($reference));
        if ($reference === '') {
            return null;
        }

        if (self::containsBlockedMarketingTerms($reference)) {
            $reference = (string)preg_replace(self::blockedTermsPattern(), '', $reference);
        }

        $reference = preg_replace('/[^a-z0-9\-]+/', '-', $reference) ?? '';
        $reference = trim($reference, '-');

        return $reference !== '' ? $reference : null;
    }

    private static function blockedTermsPattern(): string
    {
        return '/sticker|stickers|rifa|rifas|raffle|raffles|sorteo|sorteos|loteria|loter[ií]a|lottery|'
            . 'ticket|tickets|suerte|boleta|boletas|paquete|paquetes|premio|premios|bingo|apuesta|'
            . 'transferencia|compra[\s\-]?web/iu';
    }

    private static function containsBlockedMarketingTerms(string $value): bool
    {
        return (bool)preg_match(self::blockedTermsPattern(), $value);
    }

    /**
     * @param array<string, mixed> $customData
     * @param array<string, mixed> $userData
     */
    public static function sendEvent(
        string $eventName,
        array $customData = [],
        array $userData = [],
        ?string $eventId = null,
        ?int $eventTime = null,
        bool $includeRequestUserData = true
    ): bool {
        $customData = $eventName === 'Purchase'
            ? self::sanitizePurchaseCustomData($customData)
            : self::sanitizeCustomData($customData);
        if (!self::isEnabled()) {
            self::logDebugResult('NO_ENVIADO', [
                'event_name' => $eventName,
                'event_id' => $eventId ?? '',
                'custom_data' => $customData,
            ], 'META_CAPI_ENABLED=false');
            return false;
        }

        $pixelId = self::pixelId();
        $accessToken = self::accessToken();
        if ($pixelId === '' || $accessToken === '') {
            $missingCreds = [
                'event_name' => $eventName,
                'event_id' => $eventId ?? '',
                'custom_data' => $customData,
            ];
            if ($eventName === 'Purchase') {
                self::logResult('NO_ENVIADO', $missingCreds, 'Falta META_PIXEL_ID o META_ACCESS_TOKEN');
            } else {
                self::logDebugResult('NO_ENVIADO', $missingCreds, 'Falta META_PIXEL_ID o META_ACCESS_TOKEN');
            }
            return false;
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => $eventTime ?? time(),
            'action_source' => 'website',
            'user_data' => self::sanitizeUserDataIdentifiers(array_filter(array_merge(
                $includeRequestUserData ? self::requestUserData() : [],
                $userData
            ))),
        ];

        if ($eventName !== 'Purchase') {
            $event['event_source_url'] = self::eventSourceUrl();
        }

        if ($eventId !== null && trim($eventId) !== '') {
            $event['event_id'] = trim($eventId);
        }

        if ($customData !== []) {
            $event['custom_data'] = $customData;
        }

        $payload = ['data' => [$event]];
        $testEventCode = self::testEventCode();
        if ($testEventCode !== '') {
            $payload['test_event_code'] = $testEventCode;
        }

        return self::post($pixelId, $accessToken, $payload, $event);
    }

    public static function pixelId(): string
    {
        return defined('META_PIXEL_ID') ? trim((string)\META_PIXEL_ID) : '';
    }

    public static function isPixelConfigured(): bool
    {
        return self::pixelId() !== '';
    }

    public static function isCapiConfigured(): bool
    {
        return self::isPixelConfigured() && self::accessToken() !== '';
    }

    public static function isConfigured(): bool
    {
        return self::isCapiConfigured();
    }

    private static function isEnabled(): bool
    {
        return !defined('META_CAPI_ENABLED') || (bool)\META_CAPI_ENABLED;
    }

    private static function accessToken(): string
    {
        return defined('META_ACCESS_TOKEN') ? trim((string)\META_ACCESS_TOKEN) : '';
    }

    private static function apiVersion(): string
    {
        $version = defined('META_API_VERSION') ? trim((string)\META_API_VERSION) : self::DEFAULT_API_VERSION;

        return $version !== '' ? $version : self::DEFAULT_API_VERSION;
    }

    private static function testEventCode(): string
    {
        return defined('META_TEST_EVENT_CODE') ? trim((string)\META_TEST_EVENT_CODE) : '';
    }

    private static function isDebugEnabled(): bool
    {
        return defined('META_CAPI_DEBUG') && (bool)\META_CAPI_DEBUG;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $event
     */
    private static function post(string $pixelId, string $accessToken, array $payload, array $event): bool
    {
        if (!function_exists('curl_init')) {
            self::logResult('NO_ENVIADO', $event, 'cURL no está disponible para enviar eventos a Meta.');
            return false;
        }

        $url = 'https://graph.facebook.com/' . rawurlencode(self::apiVersion())
            . '/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($accessToken);

        $ch = curl_init($url);
        if ($ch === false) {
            self::logResult('NO_ENVIADO', $event, 'No se pudo inicializar cURL.');
            return false;
        }

        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            \CURLOPT_CONNECTTIMEOUT => self::connectTimeout(),
            \CURLOPT_TIMEOUT => self::requestTimeout(),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            self::logResult('NO_ENVIADO', $event, 'HTTP ' . $httpCode . ': ' . ($error !== '' ? $error : (string)$response));
            return false;
        }

        if (in_array($event['event_name'] ?? '', ['Purchase', 'PageView'], true)) {
            self::logResult('ENVIADO', $event, self::metaResponseDetail($httpCode, (string)$response));
        } else {
            self::logDebugResult('ENVIADO', $event, self::metaResponseDetail($httpCode, (string)$response));
        }

        return true;
    }

    private static function metaResponseDetail(int $httpCode, string $response): string
    {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return 'HTTP ' . $httpCode . ' response=' . mb_substr($response, 0, 500);
        }

        $messages = $decoded['messages'] ?? [];
        if (is_array($messages)) {
            $messages = json_encode($messages, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        return sprintf(
            'HTTP %d events_received=%s fbtrace_id=%s messages=%s',
            $httpCode,
            isset($decoded['events_received']) ? (string)$decoded['events_received'] : '',
            (string)($decoded['fbtrace_id'] ?? ''),
            is_string($messages) ? $messages : ''
        );
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function logDebugResult(string $status, array $event, string $detail): void
    {
        if (self::isDebugEnabled()) {
            self::logResult($status, $event, $detail);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function logResult(string $status, array $event, string $detail): void
    {
        $customData = is_array($event['custom_data'] ?? null) ? $event['custom_data'] : [];

        self::log(sprintf(
            'Meta CAPI %s: %s event_id=%s value=%s detail=%s',
            $status,
            (string)($event['event_name'] ?? ''),
            (string)($event['event_id'] ?? ''),
            isset($customData['value']) ? (string)$customData['value'] : '',
            $detail
        ));
    }

    /**
     * @param array<string, mixed> $sale
     * @return array<string, mixed>
     */
    public static function userDataFromCustomer(array $sale): array
    {
        $firstName = self::firstName((string)($sale['name_customer'] ?? ''));
        $lastName = (string)($sale['lastname_customer'] ?? '');

        return array_filter([
            'em' => self::hash((string)($sale['email_customer'] ?? '')),
            'ph' => self::hashPhone((string)($sale['phone_customer'] ?? '')),
            'fn' => self::hash($firstName),
            'ln' => self::hash($lastName),
            'ct' => self::hash((string)($sale['city_customer'] ?? '')),
            'st' => self::hash((string)($sale['department_customer'] ?? '')),
            'country' => self::hash('co'),
            'external_id' => self::hash((string)($sale['id_customer_sale'] ?? $sale['id_customer'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function userDataFromInput(array $input): array
    {
        return array_filter([
            'em' => self::hash((string)($input['email'] ?? $input['email_customer'] ?? '')),
            'ph' => self::hashPhone((string)($input['phone'] ?? $input['phone_customer'] ?? '')),
            'fn' => self::hash(self::firstName((string)($input['name'] ?? $input['name_customer'] ?? ''))),
            'ln' => self::hash((string)($input['lastname'] ?? $input['lastname_customer'] ?? '')),
            'ct' => self::hash((string)($input['city'] ?? $input['city_customer'] ?? '')),
            'st' => self::hash((string)($input['department'] ?? $input['department_customer'] ?? '')),
            'country' => self::hash('co'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestUserData(): array
    {
        return array_filter([
            'client_ip_address' => self::clientIp(),
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'fbp' => $_COOKIE['_fbp'] ?? null,
            'fbc' => $_COOKIE['_fbc'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $userData
     * @return array<string, mixed>
     */
    private static function sanitizeUserDataIdentifiers(array $userData): array
    {
        if (isset($userData['client_ip_address'])) {
            $ip = self::normalizeClientIp($userData['client_ip_address']);
            if ($ip === null) {
                unset($userData['client_ip_address']);
            } else {
                $userData['client_ip_address'] = $ip;
            }
        }

        if (isset($userData['fbc'])) {
            $fbc = trim((string)$userData['fbc']);
            if (!self::isValidFbc($fbc)) {
                unset($userData['fbc']);
            } else {
                $userData['fbc'] = $fbc;
            }
        }

        return $userData;
    }

    private static function isValidFbc(string $fbc): bool
    {
        if ($fbc === '') {
            return false;
        }

        if (!preg_match('/^fb\.1\.(\d{10,13})\.[A-Za-z0-9_-]+$/', $fbc, $matches)) {
            return false;
        }

        $rawTimestamp = (int)($matches[1] ?? 0);
        if ($rawTimestamp <= 0) {
            return false;
        }

        // Accept both seconds and milliseconds unix timestamps.
        $timestamp = strlen((string)$rawTimestamp) === 13
            ? (int)floor($rawTimestamp / 1000)
            : $rawTimestamp;

        $now = time();
        if ($timestamp > $now + 86400) {
            return false;
        }

        return ($now - $timestamp) <= self::FBC_MAX_AGE_SECONDS;
    }

    private static function clientIp(): ?string
    {
        return self::normalizeClientIp(null);
    }

    private static function normalizeClientIp(mixed $value): ?string
    {
        $candidates = [];
        if ($value !== null && trim((string)$value) !== '') {
            $candidates[] = (string)$value;
        }
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        $candidates[] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

        foreach ($candidates as $candidate) {
            $ip = trim(explode(',', (string)$candidate)[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    private static function connectTimeout(): int
    {
        return defined('META_CAPI_CONNECT_TIMEOUT') ? (int)\META_CAPI_CONNECT_TIMEOUT : 5;
    }

    private static function requestTimeout(): int
    {
        return defined('META_CAPI_TIMEOUT') ? (int)\META_CAPI_TIMEOUT : 8;
    }

    private static function eventSourceUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? parse_url((string)(defined('BASE_URL') ? \BASE_URL : ''), \PHP_URL_HOST));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

        if ($host === '') {
            return defined('BASE_URL') ? (string)\BASE_URL : '';
        }

        return $scheme . '://' . $host . $uri;
    }

    private static function firstName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value) ?: [];

        return (string)($parts[0] ?? $value);
    }

    private static function hash(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    private static function hashPhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10) {
            $digits = '57' . $digits;
        }

        return $digits !== '' ? hash('sha256', $digits) : null;
    }

    private static function log(string $message): void
    {
        writeAppLog('meta-capi.log', $message);
    }
}
