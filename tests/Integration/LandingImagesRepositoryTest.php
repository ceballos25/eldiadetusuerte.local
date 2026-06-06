<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Repository\PdoSiteImageRepository;
use Tests\Support\IntegrationTestCase;

final class LandingImagesRepositoryTest extends IntegrationTestCase
{
    public function testSiteImagesSeeded(): void
    {
        $map = (new PdoSiteImageRepository())->asKeyMap();
        self::assertArrayHasKey('logo', $map);
        self::assertArrayHasKey('favicon', $map);
        self::assertStringContainsString('https://', $map['logo']['url']);
    }
}
