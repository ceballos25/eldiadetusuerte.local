<?php
declare(strict_types=1);

namespace App\Application\Webhook;

final class OpenPayBridgeSignatureException extends \RuntimeException
{
    private function __construct(string $message, public readonly string $reasonCode)
    {
        parent::__construct($message);
    }

    public static function emptySecret(): self
    {
        return new self('Bridge secret vacío', 'empty_secret');
    }

    public static function missingHeaders(): self
    {
        return new self('Faltan headers de firma', 'missing_headers');
    }

    public static function invalidTimestamp(): self
    {
        return new self('Timestamp inválido', 'invalid_timestamp');
    }

    public static function timestampOutOfWindow(): self
    {
        return new self('Timestamp fuera de ventana', 'timestamp_out_of_window');
    }

    public static function invalidSignature(): self
    {
        return new self('Firma inválida', 'invalid_signature');
    }
}
