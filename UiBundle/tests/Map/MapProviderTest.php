<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Map;

use c975L\UiBundle\Map\MapProvider;
use PHPUnit\Framework\TestCase;

class MapProviderTest extends TestCase
{
    // The two halves of the same decision: a provider writing cookies of its own is one a visitor has to be asked about, and OpenStreetMap's tile servers write none
    public function testOnlyTheProviderWritingCookiesIsGatedByConsent(): void
    {
        $this->assertTrue(MapProvider::Google->needsConsent());
        $this->assertFalse(MapProvider::Leaflet->needsConsent());
    }

    // A key is what the block's own settings ask for, and asking for one where none is needed would leave a working map behind an empty field (see MapExtension::settings)
    public function testOnlyGoogleNeedsAKey(): void
    {
        $this->assertTrue(MapProvider::Google->needsApiKey());
        $this->assertFalse(MapProvider::Leaflet->needsApiKey());
    }

    // The licence of the tiles requires the credit on the map itself, so a tile server without one is a provider drawn illegally
    public function testATileServerAlwaysComesWithItsAttribution(): void
    {
        foreach (MapProvider::cases() as $provider) {
            if (null === $provider->tileUrl()) {
                continue;
            }

            $this->assertNotNull($provider->attribution(), sprintf('"%s" draws from a tile server and credits nobody for it.', $provider->value));
        }
    }

    // The setting is typed in a back-office, and a map is a page's content: a value nobody recognizes draws OpenStreetMap rather than bringing down every page carrying a map
    public function testAnUnknownSettingFallsBackOnOpenStreetMap(): void
    {
        $this->assertSame(MapProvider::Leaflet, MapProvider::fromSetting('mapbox'));
        $this->assertSame(MapProvider::Leaflet, MapProvider::fromSetting(null));
        $this->assertSame(MapProvider::Google, MapProvider::fromSetting('google'));
    }

    // What the site names in its own policy - a missing origin is an empty box in production and a drawn map in development, the two policies never being the same
    public function testEveryProviderContributesItsOriginsToTheSharedLists(): void
    {
        foreach (MapProvider::cases() as $provider) {
            foreach ($provider->imgOrigins() as $origin) {
                $this->assertContains($origin, MapProvider::allImgOrigins());
            }
            foreach ($provider->scriptOrigins() as $origin) {
                $this->assertContains($origin, MapProvider::allScriptOrigins());
            }
            foreach ($provider->connectOrigins() as $origin) {
                $this->assertContains($origin, MapProvider::allConnectOrigins());
            }
        }

        $this->assertSame(array_unique(MapProvider::allImgOrigins()), MapProvider::allImgOrigins(), 'An origin two providers share is published twice.');
    }

    // The whole reason the origins are split per directive: one blob holding both would put a tile server in a "script-src" the day a site named it there
    public function testATileServerNeverReachesTheScriptOrTheConnectList(): void
    {
        $this->assertSame([], MapProvider::Leaflet->scriptOrigins(), 'Leaflet is served by this bundle, so an OpenStreetMap site has no script host to allow.');
        $this->assertSame([], MapProvider::Leaflet->connectOrigins());
        $this->assertNotContains('https://tile.openstreetmap.org', MapProvider::allScriptOrigins());
        $this->assertNotContains('https://tile.openstreetmap.org', MapProvider::allConnectOrigins());
    }

    // A provider that draws from a tile server and declares no image origin renders an empty box under any policy worth the name
    public function testEveryProviderDeclaresWhereItsImagesComeFrom(): void
    {
        foreach (MapProvider::cases() as $provider) {
            $this->assertNotSame([], $provider->imgOrigins(), sprintf('"%s" names no image origin at all.', $provider->value));
        }
    }

    // The choices "ui-map-provider" offers are read off this enum by nothing at all - configs.json is a static file, so the two are checked against each other here
    public function testTheDeclaredSettingOffersExactlyTheProvidersThisEnumKnows(): void
    {
        $configs = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/config/configs.json'), true, 512, \JSON_THROW_ON_ERROR);
        $entry = array_values(array_filter($configs, static fn (array $config): bool => 'ui-map-provider' === $config['slug']));

        $this->assertNotSame([], $entry, 'No config declares the slug "ui-map-provider".');
        $this->assertSame(MapProvider::values(), $entry[0]['choices']);
    }
}
