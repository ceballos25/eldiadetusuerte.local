<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Validation\ImageUrlValidator;
use PHPUnit\Framework\TestCase;

final class ImageUrlValidatorTest extends TestCase
{
    public function testAcceptsHttpsUrl(): void
    {
        self::assertTrue(ImageUrlValidator::isValidFormat('https://cdn.example.com/img.png'));
    }

    public function testRejectsInvalidUrl(): void
    {
        self::assertFalse(ImageUrlValidator::isValidFormat('not-a-url'));
        self::assertFalse(ImageUrlValidator::isValidFormat('ftp://bad.com/x'));
    }
}
