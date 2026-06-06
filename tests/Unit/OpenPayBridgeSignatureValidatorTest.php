<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\OpenPayBridgeSignatureException;
use App\Application\Webhook\OpenPayBridgeSignatureValidator;
use PHPUnit\Framework\TestCase;

final class OpenPayBridgeSignatureValidatorTest extends TestCase
{
    private const SECRET = 'test-bridge-secret-32chars!!';

    public function testValidSignaturePasses(): void
    {
        $raw = '{"type":"charge.succeeded","transaction":{"order_id":"PB-1"}}';
        $ts = '1700000000';
        $sig = OpenPayBridgeSignatureValidator::computeSignature($raw, self::SECRET, $ts);

        OpenPayBridgeSignatureValidator::validate($raw, self::SECRET, $sig, $ts, 1700000000);
        self::assertTrue(true);
    }

    public function testEmptySecretThrows(): void
    {
        $this->expectException(OpenPayBridgeSignatureException::class);
        $this->expectExceptionMessage('Bridge secret vacío');

        OpenPayBridgeSignatureValidator::validate('{}', '', 'sig', '1700000000', 1700000000);
    }

    public function testMissingHeadersThrow(): void
    {
        $this->expectException(OpenPayBridgeSignatureException::class);

        OpenPayBridgeSignatureValidator::validate('{}', self::SECRET, '', '1700000000', 1700000000);
    }

    public function testInvalidTimestampThrows(): void
    {
        $this->expectException(OpenPayBridgeSignatureException::class);

        OpenPayBridgeSignatureValidator::validate('{}', self::SECRET, 'abc', 'not-a-number', 1700000000);
    }

    public function testTimestampOutsideWindowThrows(): void
    {
        $raw = '{}';
        $ts = '1700000000';
        $sig = OpenPayBridgeSignatureValidator::computeSignature($raw, self::SECRET, $ts);

        $this->expectException(OpenPayBridgeSignatureException::class);

        OpenPayBridgeSignatureValidator::validate($raw, self::SECRET, $sig, $ts, 1700003601);
    }

    public function testWrongSignatureThrows(): void
    {
        $this->expectException(OpenPayBridgeSignatureException::class);

        OpenPayBridgeSignatureValidator::validate('{}', self::SECRET, str_repeat('a', 64), '1700000000', 1700000000);
    }

    public function testComputeSignatureIsDeterministic(): void
    {
        $raw = '{"order_id":"PB-1"}';
        $a = OpenPayBridgeSignatureValidator::computeSignature($raw, self::SECRET, '100');
        $b = OpenPayBridgeSignatureValidator::computeSignature($raw, self::SECRET, '100');

        self::assertSame($a, $b);
        self::assertSame(64, strlen($a));
    }
}
