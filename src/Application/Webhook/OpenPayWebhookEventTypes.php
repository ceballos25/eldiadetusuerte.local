<?php
declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Eventos OpenPay estandarizados para El Día de Tu Suerte.
 * @see https://documents.openpay.co/api/index.html#webhooks
 */
final class OpenPayWebhookEventTypes
{
    /** Verificación de URL al registrar el webhook (obligatorio en OpenPay). */
    public const VERIFICATION = 'verification';

    /** Cargo aplicado / pago exitoso. */
    public const CHARGE_SUCCEEDED = 'charge.succeeded';

    /** Cargo programado (no confirma pago aún). */
    public const CHARGE_CREATED = 'charge.created';

    /** Cargo fallido. */
    public const CHARGE_FAILED = 'charge.failed';

    /** Cargo cancelado. */
    public const CHARGE_CANCELLED = 'charge.cancelled';

    /** Cargo reembolsado. */
    public const CHARGE_REFUNDED = 'charge.refunded';

    /** Cargo declinado tras rescoring. */
    public const CHARGE_RESCORED_DECLINE = 'charge.rescored.to.decline';

    /**
     * Eventos que se registran al crear el webhook en OpenPay.
     *
     * @return list<string>
     */
    public static function forRegistration(): array
    {
        return [
            self::VERIFICATION,
            self::CHARGE_SUCCEEDED,
            self::CHARGE_FAILED,
            self::CHARGE_CANCELLED,
            self::CHARGE_REFUNDED,
            self::CHARGE_RESCORED_DECLINE,
            // Opcional: cargo creado (solo informativo, no aprueba venta)
            self::CHARGE_CREATED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function approvedEvents(): array
    {
        return [
            self::CHARGE_SUCCEEDED,
            'order.completed',
            'order.payment.received',
        ];
    }

    /**
     * @return list<string>
     */
    public static function rejectedEvents(): array
    {
        return [
            self::CHARGE_FAILED,
            self::CHARGE_CANCELLED,
            self::CHARGE_REFUNDED,
            self::CHARGE_RESCORED_DECLINE,
            'order.expired',
            'order.cancelled',
            'order.payment.cancelled',
        ];
    }

    /**
     * @return list<string>
     */
    public static function ignoredEvents(): array
    {
        return [
            self::CHARGE_CREATED,
        ];
    }
}
