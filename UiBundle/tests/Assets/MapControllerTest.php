<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A drawn map cannot be proven from a unit test - it is tiles fetched by a browser - so what is locked here is what makes it safe to put on a public page: nothing third-party is loaded before consent, nothing is required for the block to hold, and a place reaches a popup escaped
class MapControllerTest extends TestCase
{
    private const string MODULE = 'assets/js/map.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Map/Map.html.twig';

    // Registered lazily, under the kebab-case identifier every "data-ui-map-*-value" binding is derived from - a camelCase one silently breaks all of them
    public function testTheControllerIsRegisteredUnderTheIdentifierTheComponentWrites(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringContainsString("'ui-map': () => import('./js/map.js')", $barrel);
        $this->assertStringContainsString('data-controller="ui-map"', $this->read(self::COMPONENT));
    }

    // The same contract as video-iframe.js, matched on both spellings: a missed match would conclude the page has no banner and load Google before any consent was given
    public function testTheConsentBannerIsMatchedOnBothSpellings(): void
    {
        $module = $this->read(self::MODULE);

        $this->assertStringContainsString('[data-controller~="cookie-consent"]', $module);
        $this->assertStringContainsString('[data-controller~="cookieConsent"]', $module);
        $this->assertStringContainsString('acceptedCategory("content")', $module);
    }

    // Served by this bundle and appended on demand, never imported: a consuming app has no package to require and no CDN host to open in its policy, and a page carrying no map downloads none of it (same reasoning as cookie-consent.js, which vendors its own banner)
    public function testLeafletIsServedByThisBundleAndNeverImported(): void
    {
        $module = $this->read(self::MODULE);

        $this->assertDoesNotMatchRegularExpression('/import\s*\(\s*"leaflet/', $module, 'Leaflet is imported as a package again, which puts an "importmap:require" back on every consuming app.');
        $this->assertDoesNotMatchRegularExpression('/^import .*"leaflet/m', $module);
        $this->assertStringContainsString('/bundles/c975lui/js/leaflet.js', $module);
        $this->assertStringContainsString('/bundles/c975lui/css/leaflet.css', $module);
    }

    // The two files really shipped, and declared in the manifest that says which version they are (see VendorAssetsTest)
    public function testTheFilesItLoadsAreTheOnesThisBundleShips(): void
    {
        $manifest = json_decode($this->read('config/vendor-assets.json'), true, 512, \JSON_THROW_ON_ERROR);
        $leaflet = array_values(array_filter($manifest, static fn (array $library): bool => 'leaflet' === $library['name']));

        $this->assertNotSame([], $leaflet, 'Leaflet is no longer declared in the vendored assets manifest.');
        foreach (['public/js/leaflet.js', 'public/css/leaflet.css'] as $path) {
            $this->assertArrayHasKey($path, $leaflet[0]['files']);
        }
    }

    // Leaflet reads the sizes its own rules give the panes as it builds, so a map built before the stylesheet applies lays its tiles out against an unstyled box
    public function testTheStylesheetIsWaitedForBeforeTheMapIsBuilt(): void
    {
        $this->assertMatchesRegularExpression('/Promise\.all\(\[\s*this\.load\("link"/', $this->read(self::MODULE));
    }

    // Drawn rather than loaded, so it takes the site's own palette instead of the library's fixed blue
    public function testTheMarkerIsDrawnRatherThanLoaded(): void
    {
        // That the markers really carry that class is MapBehaviourTest's; what stays here is the other end, the stylesheet actually drawing something for it
        $this->assertStringContainsString('.ui-map__pin', $this->read('sass/_map.scss'));
    }

    // AssetMapper resolves a stylesheet's url() strictly: one image a vendored sheet names and the bundle does not ship fails the whole compilation, which answers 500 on every bundle stylesheet at once - not just on this one
    public function testEveryImageTheVendoredStylesheetNamesIsShipped(): void
    {
        preg_match_all('/url\((images\/[^)]+)\)/', $this->read('public/css/leaflet.css'), $matches);
        $this->assertNotEmpty($matches[1], 'The vendored stylesheet names no image at all, which this guard would then pass blindly.');

        $declared = json_decode($this->read('config/vendor-assets.json'), true, 512, \JSON_THROW_ON_ERROR);
        $leaflet = array_values(array_filter($declared, static fn (array $library): bool => 'leaflet' === $library['name']))[0];

        foreach (array_unique($matches[1]) as $image) {
            $path = 'public/css/' . $image;
            $this->assertFileExists(\dirname(__DIR__, 2) . '/' . $path, sprintf('"leaflet.css" names "%s", which the bundle does not ship - every bundle stylesheet answers 500.', $image));
            $this->assertArrayHasKey($path, $leaflet['files'], sprintf('"%s" is shipped but undeclared, so the next update would silently drop it.', $path));
        }
    }

    // A place's name and text are what an editor typed: built as elements, they reach the popup escaped whatever they hold
    public function testAPopupIsBuiltFromElementsAndNeverFromAnHtmlString(): void
    {
        $module = $this->read(self::MODULE);

        // The escaping itself is MapBehaviourTest's; what no scenario can show is the absence of the other way of building it
        $this->assertStringNotContainsString('innerHTML', $module);
    }

    // The list of places is the content: a visitor with no JavaScript, an app that never required leaflet, a key Google turned down - all keep the addresses, their links, and the only version of the block a keyboard can work through
    public function testTheListOfPlacesIsRenderedServerSideAndTheCanvasIsNot(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('data-ui-map-target="list"', $component);
        $this->assertStringContainsString('<div class="ui-map__canvas" data-ui-map-target="canvas" hidden>', $component);
        $this->assertStringContainsString('openstreetmap.org/?mlat=', $component, 'A place no longer carries the link that works whether or not a map was ever drawn.');
    }

    // The half a scenario cannot see: it mounts markup of its own, so the element the diagnostic is written into, and the fact that it is rendered for nobody but whoever may act on it, are the template's own to guarantee
    public function testTheDiagnosticIsRenderedForWhoeverMayActOnItAndForNobodyElse(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('{% if settings.diagnostic %}', $component, 'The reason a map is missing is rendered whoever is reading the page.');
        $this->assertStringContainsString('data-ui-map-target="diagnostic"', $component);
        $this->assertStringContainsString('data-ui-map-diagnostic-csp-value=', $component);
        $this->assertStringContainsString("'label.map_diagnostic_no_key'|trans", $component, 'A Google map with no key says nothing at all, and no controller is mounted to say it.');

        // Painted by this bundle's own stylesheet: a class nothing draws is a message nobody sees
        $this->assertStringContainsString('.ui-map__diagnostic {', $this->read('sass/_map.scss'));
        $this->assertStringContainsString('.ui-map__diagnostic', $this->read('public/css/styles.css'), 'The compiled stylesheet was not rebuilt, so the notice is drawn as plain text in the flow.');
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
