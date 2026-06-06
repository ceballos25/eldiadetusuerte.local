<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/** Base para tests de integración que cargan config.php (BD, constantes). */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        bootstrapApp();
    }
}
