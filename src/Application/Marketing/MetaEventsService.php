<?php
declare(strict_types=1);

namespace App\Application\Marketing;

final class MetaEventsService
{
    /** @var list<string> */
    public const ALLOWED_ACTIONS = ['track_event', 'list_events'];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(string $action, array $payload): array
    {
        return match ($action) {
            'track_event' => $this->trackEvent($payload),
            'list_events' => [
                'success' => true,
                'events' => MetaConversionsApi::BROWSER_TRACK_EVENTS,
                'pixel_id' => MetaConversionsApi::pixelId(),
                'capi_enabled' => defined('META_CAPI_ENABLED') ? (bool)\META_CAPI_ENABLED : true,
            ],
            default => ['success' => false, 'message' => 'Acción no válida'],
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function trackEvent(array $payload): array
    {
        if (!MetaConversionsApi::isPixelConfigured()) {
            return ['success' => false, 'message' => 'Meta Pixel no configurado'];
        }

        if (!MetaConversionsApi::isCapiConfigured()) {
            return ['success' => false, 'message' => 'Meta Conversions API no configurada (falta META_ACCESS_TOKEN)'];
        }

        $eventName = trim((string)($payload['event_name'] ?? ''));
        if (!MetaConversionsApi::isAllowedTrackEvent($eventName)) {
            return ['success' => false, 'message' => 'Evento Meta no permitido: ' . $eventName];
        }

        $guard = new MetaPixelGuard();

        if ($eventName === 'PageView') {
            if (!$guard->shouldSendPageView()) {
                return ['success' => true, 'event_name' => 'PageView', 'event_id' => '', 'capi_sent' => false, 'skipped' => true];
            }
        }

        $customData = $this->decodeJsonField($payload['custom_data'] ?? '{}');
        $userInput = $this->decodeJsonField($payload['user_data'] ?? '{}');
        if ($eventName === 'Purchase') {
            $customData = MetaConversionsApi::sanitizePurchaseCustomData($customData);
        } else {
            $customData = MetaConversionsApi::sanitizeCustomData($customData);
        }

        $eventRef = trim((string)($payload['event_ref'] ?? ''));
        $eventRef = MetaConversionsApi::sanitizeEventReference($eventRef !== '' ? $eventRef : null);

        if ($eventName === 'Purchase') {
            $saleCode = trim((string)($eventRef ?? ''));
            if ($saleCode !== '' && !$guard->shouldSendPurchase($saleCode)) {
                return ['success' => true, 'event_name' => 'Purchase', 'event_id' => '', 'capi_sent' => false, 'skipped' => true];
            }
        }

        $userData = MetaConversionsApi::userDataFromInput($userInput);

        if (!empty($payload['fbp'])) {
            $userData['fbp'] = trim((string)$payload['fbp']);
        }
        if (!empty($payload['fbc'])) {
            $userData['fbc'] = trim((string)$payload['fbc']);
        }

        $result = MetaConversionsApi::trackStandardEvent(
            $eventName,
            $customData,
            $eventRef,
            $userData,
            true
        );

        if ($eventName === 'PageView' && ($result['sent'] ?? false)) {
            $guard->markPageViewSent();
        }
        if ($eventName === 'Purchase' && ($result['sent'] ?? false)) {
            $saleCode = trim((string)($eventRef ?? ''));
            if ($saleCode !== '') {
                $guard->markPurchaseSent($saleCode);
            }
        }

        return [
            'success' => true,
            'event_name' => $eventName,
            'event_id' => $result['event_id'],
            'capi_sent' => $result['sent'],
            'custom_data' => $customData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
