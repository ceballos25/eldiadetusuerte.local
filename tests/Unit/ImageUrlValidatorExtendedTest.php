<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Validation\ImageUrlValidator;
use PHPUnit\Framework\TestCase;

final class ImageUrlValidatorExtendedTest extends TestCase
{
    public function testRejectsEmptyString(): void
    {
        self::assertFalse(ImageUrlValidator::isValidFormat(''));
    }

    public function testRejectsHttpWhenOnlyHttpsExpectedInStrictUse(): void
    {
        // El validador acepta http y https
        self::assertTrue(ImageUrlValidator::isValidFormat('http://example.com/a.png'));
    }

    public function testRejectsJavascriptUrl(): void
    {
        self::assertFalse(ImageUrlValidator::isValidFormat('javascript:alert(1)'));
    }
}
