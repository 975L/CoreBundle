<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// The vendored Leaflet actually run, in a real browser, through the very sequence assets/js/map.js drives it with.
// Where VendorAssetsTest reads the shipped file for the names this bundle calls, this one proves those names still do what the map needs - the half a version bump breaks silently (see bin/vendor-assets.sh).
// No network: the tile url points nowhere on purpose, Leaflet building its panes and its tile elements all the same.
// What is asserted is the DOM it built, never a pixel - a test that needed tiles to load would fail on a train.
// The first test of this bundle to drive Chrome, hence the group: "--exclude-group browser" takes it back out.
#[Group('browser')]
class LeafletContractTest extends TestCase
{
    // Two places far enough apart that fitBounds has to move the view off either one of them
    private const array PLACES = [
        ['label' => 'Annecy', 'latitude' => 45.8992, 'longitude' => 6.1294],
        ['label' => 'Chamonix', 'latitude' => 45.9237, 'longitude' => 6.8694],
    ];

    protected function setUp(): void
    {
        if (!class_exists(BrowserFactory::class)) {
            $this->markTestSkipped('chrome-php/chrome is needed to run the vendored library.');
        }

        if (!is_executable('/usr/bin/google-chrome')) {
            $this->markTestSkipped('google-chrome is needed to run the vendored library.');
        }
    }

    public function testTheVendoredLeafletDrawsWhatTheControllerAsksItFor(): void
    {
        $built = $this->build();

        $this->assertTrue($built['loaded'], 'The vendored leaflet.js no longer exposes the "L" global a classic script sets.');
        $this->assertTrue($built['container'], 'The map container was never turned into a Leaflet one.');
        $this->assertGreaterThan(0, $built['panes'], 'Leaflet built no pane at all, so its stylesheet and its script no longer agree.');
        $this->assertGreaterThan(0, $built['tiles'], 'The tile layer created no tile element, so "tileLayer" no longer lays anything out.');

        // The pin is this bundle's own, drawn by sass/_map.scss - a divIcon that stopped carrying its class would leave every marker invisible
        $this->assertSame(2, $built['pins'], 'The two divIcon markers no longer carry the "ui-map__pin" class.');

        // fitBounds framed the pair rather than staying on the first place, which is what a map of several places is for
        $this->assertGreaterThan(6.1294, $built['center']['lng']);
        $this->assertLessThan(6.8694, $built['center']['lng']);

        // The popup is built from elements, so a name reaches it as text whatever it holds
        $this->assertSame('Chamonix & <script>', $built['popup']);
    }

    /**
     * Runs the same sequence as assets/js/map.js and reads back what Leaflet made of it.
     */
    private function build(): array
    {
        $browser = new BrowserFactory('/usr/bin/google-chrome')->createBrowser([
            'headless' => true,
            // Same reason as LayoutAuditor: the sandbox refuses to start for the user a CI image runs as
            'noSandbox' => true,
            'windowSize' => [900, 700],
        ]);

        $fixture = tempnam(sys_get_temp_dir(), 'leaflet') . '.html';
        file_put_contents($fixture, $this->page());

        try {
            $page = $browser->createPage();
            $page->navigate('file://' . $fixture)->waitForNavigation();

            $measured = json_decode((string) $page->evaluate('window.__built')->getReturnValue(), true);
            $page->close();
        } finally {
            $browser->close();
            unlink($fixture);
        }

        // Never read as "nothing built": a script that threw would otherwise report a clean run
        if (!is_array($measured)) {
            $this->fail('The vendored library threw before it could report anything - run the fixture by hand to see what.');
        }

        return $measured;
    }

    // The library and its stylesheet inlined rather than linked: a file:// page loading a sibling file is at Chrome's discretion, and nothing here is about path resolution
    private function page(): string
    {
        $root = \dirname(__DIR__, 2);
        $places = json_encode(self::PLACES, \JSON_THROW_ON_ERROR);

        return sprintf(
            <<<'HTML'
                <!doctype html><meta charset="utf-8">
                <style>%s
                #map { width: 600px; height: 400px; }
                .ui-map__pin { width: 24px; height: 24px; background: red; }</style>
                <div id="map"></div>
                <script>%s</script>
                <script>
                const places = %s;
                try {
                    // Deliberately unreachable: what is read back is the DOM Leaflet builds, never a tile that loaded
                    const map = L.map(document.getElementById('map')).setView([places[0].latitude, places[0].longitude], 13);
                    L.tileLayer('https://127.0.0.1:1/{z}/{x}/{y}.png', { attribution: 'test' }).addTo(map);

                    const icon = L.divIcon({ className: 'ui-map__pin', iconSize: [24, 24], iconAnchor: [12, 24], popupAnchor: [0, -24] });
                    const markers = places.map((place) => {
                        const heading = document.createElement('strong');
                        heading.textContent = place.label + ' & <script>';

                        return L.marker([place.latitude, place.longitude], { icon, title: place.label })
                            .bindPopup(heading)
                            .addTo(map);
                    });

                    map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [32, 32] });
                    markers[1].openPopup();

                    window.__built = JSON.stringify({
                        loaded: typeof L !== 'undefined',
                        container: document.getElementById('map').classList.contains('leaflet-container'),
                        panes: document.querySelectorAll('.leaflet-pane').length,
                        tiles: document.querySelectorAll('.leaflet-tile').length,
                        pins: document.querySelectorAll('.ui-map__pin').length,
                        center: { lat: map.getCenter().lat, lng: map.getCenter().lng },
                        popup: document.querySelector('.leaflet-popup-content strong')?.textContent ?? '',
                    });
                } catch (error) {
                    window.__built = JSON.stringify({ error: String(error) });
                }
                </script>
                HTML,
            file_get_contents($root . '/public/css/leaflet.css'),
            file_get_contents($root . '/public/js/leaflet.js'),
            $places
        );
    }
}
