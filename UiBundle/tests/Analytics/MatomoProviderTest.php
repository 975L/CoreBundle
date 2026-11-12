<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Analytics;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Analytics\MatomoProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MatomoProviderTest extends TestCase
{
    private function createProvider(?string $url, ?string $id): MatomoProvider
    {
        $values = ['analytics-matomo-url' => $url, 'analytics-matomo-id' => $id];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null);

        return new MatomoProvider($configService);
    }

    // No separate switch: both values a tracker url is built from filled is what turns tracking on
    #[DataProvider('provideConfigs')]
    public function testItIsConfiguredByBothValuesAlone(?string $url, ?string $id, bool $expected): void
    {
        $this->assertSame($expected, $this->createProvider($url, $id)->isConfigured());
    }

    public static function provideConfigs(): iterable
    {
        yield 'both filled' => ['https://stats.example.com', '3', true];
        yield 'no url' => [null, '3', false];
        yield 'blank url' => ['  ', '3', false];
        yield 'no id' => ['https://stats.example.com', null, false];
        yield 'blank id' => ['https://stats.example.com', ' ', false];
    }

    // Trimmed and stripped of its trailing slashes, "//matomo.js" defeating proxy cache de-duplication
    public function testTheSettingsCarryTheCleanedUrlAndId(): void
    {
        $this->assertSame(['url' => 'https://stats.example.com/stats', 'id' => '3'], $this->createProvider(' https://stats.example.com/stats// ', ' 3 ')->getSettings());
    }

    // Origin only, a preconnect carrying no path
    public function testTheOriginDropsThePath(): void
    {
        $this->assertSame('https://stats.example.com', $this->createProvider('https://stats.example.com/stats/', '3')->getOrigin());
        $this->assertSame('https://stats.example.com:8443', $this->createProvider('https://stats.example.com:8443/', '3')->getOrigin());
        $this->assertNull($this->createProvider('stats', '3')->getOrigin());
    }
}
