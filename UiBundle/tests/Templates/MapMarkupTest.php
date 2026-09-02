<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Twig\MapExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

// The component really rendered, not read as a string: what the controller gets is a JSON payload written through Twig's own escaping, and every way of getting that wrong renders a page that looks right and draws no map at all
class MapMarkupTest extends TestCase
{
    private const array PLACES = [
        ['label' => 'Annecy', 'latitude' => '45.8992', 'longitude' => '6.1294', 'text' => 'Au bord du lac.', 'url' => ''],
        ['label' => 'Chamonix', 'latitude' => '45.9237', 'longitude' => '6.8694', 'text' => '', 'url' => 'https://example.com'],
    ];

    // A JSON array, never an object: Stimulus reads an Array value, and an object has no length - the controller would leave every map on the site undrawn without a single error anywhere
    public function testThePayloadIsAJsonArrayEvenWhenAPointIsDropped(): void
    {
        $html = $this->render(['points' => array_merge([['label' => 'Nulle part', 'latitude' => '', 'longitude' => '']], self::PLACES)]);

        $payload = json_decode($this->payload($html), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertIsList($payload);
        $this->assertCount(2, $payload);
        $this->assertSame('Annecy', $payload[0]['label']);
    }

    // The places are the content: rendered server-side, with a link that works whether or not a map was ever drawn over them
    public function testEveryPlaceIsListedWithItsWayOutToAMap(): void
    {
        $html = $this->render(['points' => self::PLACES]);

        $this->assertStringContainsString('Annecy', $html);
        $this->assertStringContainsString('Chamonix', $html);
        $this->assertSame(2, substr_count($html, 'openstreetmap.org/?mlat='));
        // The one carrying a url is linked to it, the other is not
        $this->assertStringContainsString('<a href="https://example.com">Chamonix</a>', $html);
    }

    // A block holding nothing to draw renders nothing at all, rather than an empty framed box
    public function testABlockWithNoUsablePlaceRendersNothing(): void
    {
        $html = $this->render(['points' => [['label' => 'Nulle part', 'latitude' => '', 'longitude' => '']]]);

        $this->assertStringNotContainsString('ui-map', $html);
    }

    // A Google map with no key draws nothing at all: the list stays, and the controller is never even mounted
    public function testAGoogleMapWithNoKeyIsNeverMounted(): void
    {
        $html = $this->render(['points' => self::PLACES], 'google', '');

        $this->assertStringNotContainsString('data-controller="ui-map"', $html);
        $this->assertStringContainsString('Annecy', $html);
    }

    public function testGoogleCarriesItsKeyAndItsConsentPrompt(): void
    {
        $html = $this->render(['points' => self::PLACES], 'google', 'AIza-secret');

        $this->assertStringContainsString('data-ui-map-api-key-value="AIza-secret"', $html);
        $this->assertStringContainsString('data-ui-map-needs-consent-value="true"', $html);
    }

    // OpenStreetMap's tiles write no cookie, so nothing is held back on them - and the licence's credit travels with the tile server
    public function testOpenStreetMapIsDrawnWithoutConsentAndWithItsCredit(): void
    {
        $html = $this->render(['points' => self::PLACES]);

        $this->assertStringContainsString('data-ui-map-needs-consent-value="false"', $html);
        $this->assertStringContainsString('tile.openstreetmap.org', $html);
        $this->assertStringContainsString('OpenStreetMap', $html);
    }

    private function payload(string $html): string
    {
        preg_match('/data-ui-map-points-value="([^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches, 'The component wrote no points payload at all.');

        return html_entity_decode($matches[1], \ENT_QUOTES | \ENT_HTML5);
    }

    private function render(array $context, string $provider = 'leaflet', string $apiKey = ''): string
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['ui-map-provider', $provider],
            ['ui-map-google-api-key', $apiKey],
        ]);

        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        // Untranslated keys come back as-is, which is what the assertions above read
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // The component resolves the vendored Leaflet through asset(), so the function has to exist for the template to render at all
        $twig->addExtension(new AssetExtension(new Packages(new Package(new EmptyVersionStrategy()))));
        $twig->addExtension(new AttributeExtension(MapExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            MapExtension::class => static fn (): MapExtension => new MapExtension($configService),
        ]));

        return $twig->render('components/Map/Map.html.twig', $context);
    }
}
