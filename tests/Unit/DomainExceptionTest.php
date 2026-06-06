<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testCarriesErrorCode(): void
    {
        $e = new DomainException('Mensaje de prueba', 'TEST_CODE');

        self::assertSame('Mensaje de prueba', $e->getMessage());
        self::assertSame('TEST_CODE', $e->errorCode());
    }
}
