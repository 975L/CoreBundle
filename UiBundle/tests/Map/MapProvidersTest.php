<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Map;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Map\GoogleMapsProvider;
use c975L\UiBundle\Map\LeafletProvider;
use c975L\UiBundle\Service\MapProviderResolver;
use PHPUnit\Framework\TestCase;

// What each shipped provider answers, and which one the setting picks
class MapProvidersTest extends TestCase
{
    private function resolver(?string $provider, string $apiKey = ''): MapProviderResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['ui-map-provider', $provider], ['ui-map-google-api-key', $apiKey]]);

        return MapResolverFactory::create($configService);
    }

    // A map is a page's content: a setting naming a provider gone keeps a drawn map rather than none
    public function testAnUnknownSettingFallsBackOnLeaflet(): void
    {
        $this->assertSame('leaflet', $this->resolver('nope')->getProvider()?->getSlug());
        $this->assertSame('leaflet', $this->resolver(null)->getProvider()?->getSlug());
        $this->assertSame('google', $this->resolver('google', 'key')->getProvider()?->getSlug());
    }

    // Google writes cookies of its own and needs a key; OpenStreetMap's tiles do neither
    public function testOnlyGoogleNeedsConsentAndAKey(): void
    {
        $leaflet = $this->resolver('leaflet')->getProvider();
        $this->assertFalse($leaflet?->needsConsent());
        $this->assertTrue($leaflet?->isUsable());

        $this->assertTrue($this->resolver('google', 'key')->getProvider()?->needsConsent());
        $this->assertTrue($this->resolver('google', 'key')->getProvider()?->isUsable());
        $this->assertFalse($this->resolver('google', ' ')->getProvider()?->isUsable());
    }

    // The tile server's licence requires its credit on the map, and the library comes from this bundle
    public function testLeafletCarriesItsTilesItsCreditAndItsLibrary(): void
    {
        $settings = $this->resolver('leaflet')->getProvider()?->getSettings();

        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $settings['tileUrl']);
        $this->assertStringContainsString('OpenStreetMap', (string) $settings['attribution']);
        $this->assertStringEndsWith('bundles/c975lui/js/leaflet.js', (string) $settings['script']);
        $this->assertSame(['apiKey' => 'key'], $this->resolver('google', 'key')->getProvider()?->getSettings());
    }

    public function testEachProviderLinksToItsOwnMap(): void
    {
        $this->assertSame('https://www.openstreetmap.org/?mlat=45.9&mlon=6.1#map=16/45.9/6.1', $this->resolver('leaflet')->getProvider()?->getPlaceUrl('45.9', '6.1'));
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=45.9%2C6.1', $this->resolver('google', 'key')->getProvider()?->getPlaceUrl('45.9', '6.1'));
        $this->assertSame('https://www.openstreetmap.org/search?query=1%20rue%20X', $this->resolver('leaflet')->getProvider()?->getSearchUrl('1 rue X'));
    }

    // A tile server never reaches the script or the connect list: this bundle serves Leaflet itself
    public function testTheShippedOriginsKeepEachDirectiveApart(): void
    {
        $origins = MapProviderResolver::shippedOrigins();

        $this->assertSame([...LeafletProvider::getOrigins()['img'], ...GoogleMapsProvider::getOrigins()['img']], $origins['img']);
        $this->assertSame(['https://maps.googleapis.com'], $origins['script']);
        $this->assertSame(['https://maps.googleapis.com'], $origins['connect']);
    }

    // The setting offers exactly the providers shipped
    public function testTheDeclaredSettingOffersTheShippedProviders(): void
    {
        foreach (json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/config/configs.json'), true, 512, \JSON_THROW_ON_ERROR) as $entry) {
            if ('ui-map-provider' === $entry['slug']) {
                $this->assertSame(['leaflet', 'google'], $entry['choices']);

                return;
            }
        }

        $this->fail('"ui-map-provider" is no longer declared.');
    }
}
