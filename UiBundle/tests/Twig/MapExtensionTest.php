<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Twig\MapExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class MapExtensionTest extends TestCase
{
    public function testOpenStreetMapComesWithItsTilesAndNeedsNothingElse(): void
    {
        $settings = $this->settings('leaflet', '');

        $this->assertSame('leaflet', $settings['provider']);
        $this->assertStringContainsString('tile.openstreetmap.org', $settings['tileUrl']);
        $this->assertNotSame('', $settings['attribution']);
        $this->assertFalse($settings['needsConsent']);
        $this->assertTrue($settings['usable']);
    }

    // A key set while the site draws with OpenStreetMap is a key the pages would publish for nothing
    public function testTheKeyIsNotPublishedByAProviderThatDoesNotUseIt(): void
    {
        $this->assertSame('', $this->settings('leaflet', 'AIza-secret')['apiKey']);
    }

    public function testGoogleCarriesItsKeyAndItsConsent(): void
    {
        $settings = $this->settings('google', 'AIza-secret');

        $this->assertSame('AIza-secret', $settings['apiKey']);
        $this->assertTrue($settings['needsConsent']);
        $this->assertTrue($settings['usable']);
    }

    // A Google map with no key draws nothing at all: the component keeps the list of places on screen rather than an empty grey box
    public function testGoogleWithoutItsKeyIsNotUsable(): void
    {
        $this->assertFalse($this->settings('google', '   ')['usable']);
    }

    // The setting is typed in a back-office, and a page carrying a map must not 500 over a typo in it
    public function testAnUnknownProviderFallsBackOnOpenStreetMap(): void
    {
        $this->assertSame('leaflet', $this->settings('mapbox', '')['provider']);
    }

    // A visitor is shown the list of places and told nothing about a key or a security policy they cannot change
    public function testAVisitorIsNeverToldWhyAMapIsMissing(): void
    {
        $this->assertFalse($this->settings('google', '')['diagnostic']);
        $this->assertFalse($this->settings('google', '', null)['diagnostic'], 'A site running without security still says why the map is missing, on every page of it.');
    }

    // Whoever placed the block is looking at the page, not at a health check they have no reason to open
    public function testWhoeverMayActOnItIsToldWhyAMapIsMissing(): void
    {
        $this->assertTrue($this->settings('google', '', true)['diagnostic']);
    }

    private function settings(string $provider, string $apiKey, ?bool $granted = false): array
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['ui-map-provider', $provider],
            ['ui-map-google-api-key', $apiKey],
        ]);

        if (null === $granted) {
            return new MapExtension($configService)->settings();
        }

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => $granted && BackOfficeAccessVoter::ACCESS === $attribute
        );

        return new MapExtension($configService, $security)->settings();
    }
}
