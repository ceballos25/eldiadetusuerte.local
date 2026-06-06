<?php
declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Valida firma HMAC del microservicio de pagos (accesorios → principal).
 */
final class OpenPayBridgeSignatureValidator
{
    public const MAX_TIMESTAMP_DRIFT_SECONDS = 600;

    /**
     * @throws OpenPayBridgeSignatureException
     */
    public static function validate(
        string $rawBody,
        string $secret,
        string $signatureHeader,
        string $timestampHeader,
        ?int $now = null
    ): void {
        if ($secret === '') {
            throw OpenPayBridgeSignatureException::emptySecret();
        }

        if ($signatureHeader === '' || $timestampHeader === '') {
            throw OpenPayBridgeSignatureException::missingHeaders();
        }

        if (!ctype_digit($timestampHeader)) {
            throw OpenPayBridgeSignatureException::invalidTimestamp();
        }

        $now ??= time();
        $tsInt = (int)$timestampHeader;
        if (abs($now - $tsInt) > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
            throw OpenPayBridgeSignatureException::timestampOutOfWindow();
        }

        $expected = self::computeSignature($rawBody, $secret, $timestampHeader);
        if (!hash_equals($expected, $signatureHeader)) {
            throw OpenPayBridgeSignatureException::invalidSignature();
        }
    }

    public static function computeSignature(string $rawBody, string $secret, string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }
}
