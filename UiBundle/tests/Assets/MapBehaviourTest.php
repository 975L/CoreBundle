<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/map.js drawing over the Leaflet this bundle vendors, served from the path a site serves it from, and driven under the identifier templates/components/Map/Map.html.twig actually writes
// LeafletContractTest proves the library still draws when driven correctly; this proves this controller drives it correctly, which is the other half. Between the two sits everything only a browser can answer: a canvas whose size is read as it is built, a map framed on its markers, and a Google map that must not appear before a visitor has been asked
#[Group('browser')]
class MapBehaviourTest extends JsCase
{
    private const string BANNER = '<div data-controller="cookie-consent"></div>';

    private const string CONSENT = 'window.CookieConsent = {
        granted: %s,
        acceptedCategory(category) { return "content" === category && this.granted; },
        acceptCategory(category) { this.granted = "content" === category; window.dispatchEvent(new CustomEvent("cc:onChange")); },
    };';

    // What the map was framed on, and where each marker sits, are the map's own to answer - no class on an element says either. The library is caught as it is defined, once for the page, the controller keeping a single loader for the whole document
    private const string HOOK = 'window.__maps = []; window.__markers = [];
        if (!window.__hooked) {
            window.__hooked = true;
            let held = window.L;
            Object.defineProperty(window, "L", {
                configurable: true,
                get: () => held,
                set(value) {
                    held = value;
                    if (value && !value.__wrapped) {
                        value.__wrapped = true;
                        const map = value.map;
                        const marker = value.marker;
                        value.map = (...args) => { const made = map(...args); window.__maps.push(made); return made; };
                        value.marker = (...args) => { const made = marker(...args); window.__markers.push(made); return made; };
                    }
                },
            });
        }';

    // Two places far enough apart that framing on them has to leave both ends
    private const string POINTS = '[{"label":"Annecy","latitude":45.8992,"longitude":6.1294,"text":"Le lac","url":"/annecy"},{"label":"Chamonix <script>","latitude":45.9237,"longitude":6.8694}]';

    // OpenStreetMap writes no cookie, so nothing is ever held back for it
    public function testAMapOverOpenStreetMapIsDrawnWithoutAskingAnything(): void
    {
        $drawn = $this->map(
            'return {
                 hidden: root.querySelector("[data-ui-map-target=canvas]").hidden,
                 container: root.querySelector(".leaflet-container") !== null,
                 pins: root.querySelectorAll(".ui-map__pin").length,
                 tiles: root.querySelectorAll(".leaflet-tile").length,
                 list: root.querySelectorAll("[data-ui-map-target=list] li").length,
             };'
        );

        $this->assertFalse($drawn['hidden'], 'The canvas is still hidden, so the map was never revealed.');
        $this->assertTrue($drawn['container'], 'Leaflet was never given the canvas.');
        $this->assertSame(2, $drawn['pins'], 'The places are not on the map, or not with this bundle\'s own pin.');
        $this->assertGreaterThan(0, $drawn['tiles'], 'The tile layer laid nothing out.');
        $this->assertSame(2, $drawn['list'], 'The server-rendered list of places is gone, which is the only version of the block a screen reader and a keyboard can work through.');
    }

    // Both providers read the container's size as they build, and a display:none box is 0x0 - the map then paints a grey grid that only puts itself right on the next window resize
    public function testTheCanvasIsRevealedBeforeTheLibraryIsHandedIt(): void
    {
        $this->assertGreaterThan(
            0,
            $this->map('return root.querySelector(".leaflet-container").getBoundingClientRect().height;'),
            'The map was built against a box of no size, which paints a grey grid until the window is resized.'
        );
    }

    // Several places: the map frames itself on them rather than staying on the first
    public function testAMapOfSeveralPlacesFramesItselfOnAllOfThem(): void
    {
        $centre = $this->map('return window.__maps[0].getCenter().lng;');

        $this->assertGreaterThan(6.1294, $centre, 'The map stayed on the first place instead of framing the pair.');
        $this->assertLessThan(6.8694, $centre, 'The map framed itself past the last place.');
    }

    // A single place keeps the zoom the editor chose: framing on one marker's bounds zooms as far in as the map goes, on a street corner nobody can place
    public function testASinglePlaceKeepsTheZoomTheEditorChose(): void
    {
        $this->assertSame(
            11,
            $this->map('return window.__maps[0].getZoom();', ['points' => '[{"label":"Annecy","latitude":45.8992,"longitude":6.1294}]']),
            'A map of one place did not keep the zoom it was given, so it framed itself onto a single point.'
        );
    }

    // The label and the text are what an editor typed, and the address a marker sits at is no reason for them to reach a popup unescaped
    public function testWhatAnEditorTypedReachesThePopupAsText(): void
    {
        $popup = $this->map(
            'window.__markers[1].openPopup();
             await new Promise((r) => setTimeout(r, 60));

             return { text: document.querySelector(".ui-map__popup strong")?.textContent ?? null, scripts: document.querySelectorAll(".ui-map__popup script").length };'
        );

        $this->assertSame('Chamonix <script>', $popup['text'], 'The label does not reach the popup as the editor typed it.');
        $this->assertSame(0, $popup['scripts'], 'A label was interpreted as markup on its way into the popup.');
    }

    // A place carrying an address is a link, one without is not - and neither is ever built from a string
    public function testAPlaceWithAnAddressIsALinkAndOneWithoutIsNot(): void
    {
        $shapes = $this->map(
            'window.__markers[0].openPopup();
             await new Promise((r) => setTimeout(r, 60));
             const linked = document.querySelector(".ui-map__popup a")?.getAttribute("href") ?? null;
             const text = document.querySelector(".ui-map__popup p")?.textContent ?? null;

             return { linked, text };'
        );

        $this->assertSame('/annecy', $shapes['linked'], 'A place with an address is not a link in its popup.');
        $this->assertSame('Le lac', $shapes['text'], 'The text an editor wrote for a place never reaches its popup.');
    }

    // The failure this whole arrangement is built around: a Google map must not appear before the visitor has been asked
    public function testAGoogleMapIsNeverDrawnBeforeConsentIsGiven(): void
    {
        $state = $this->map(
            'return {
                 drawn: !!root.querySelector(".leaflet-container") || window.__maps.length > 0,
                 prompt: !root.querySelector("[data-ui-map-target=consent]").hidden,
                 canvas: root.querySelector("[data-ui-map-target=canvas]").hidden,
                 asked: [...document.querySelectorAll("script[src]")].filter((s) => s.src.includes("googleapis")).length,
                 list: root.querySelectorAll("[data-ui-map-target=list] li").length,
             };',
            ['provider' => 'google', 'needsConsent' => 'true', 'banner' => true, 'consent' => 'false']
        );

        $this->assertFalse($state['drawn'], 'A Google map was drawn with consent still pending.');
        $this->assertSame(0, $state['asked'], 'Google\'s script was fetched before the visitor was asked, which is the request that hands it their address.');
        $this->assertTrue($state['prompt'], 'Nothing on screen asks the visitor for the consent the map is waiting on.');
        $this->assertTrue($state['canvas'], 'The empty canvas is on screen over the list of places.');
        $this->assertSame(2, $state['list'], 'The places are not readable at all while consent is pending.');
    }

    // A provider writing no cookie of its own must never be held back, banner or no banner
    public function testAMapNeedingNoConsentIsDrawnEvenOnAPageThatHasABanner(): void
    {
        $this->assertTrue(
            (bool) $this->map(
                'return !!root.querySelector(".leaflet-container");',
                ['banner' => true, 'consent' => 'false']
            ),
            'A map over a provider that writes no cookie was held back behind a banner it has no business waiting for.'
        );
    }

    // "cc:onConsent" fires on every load once the choice is known, so a returning visitor gets the map without clicking again
    public function testConsentGivenAfterwardsDrawsTheMapAndTakesThePromptAway(): void
    {
        $state = $this->map(
            'window.CookieConsent.granted = true;
             window.dispatchEvent(new CustomEvent("cc:onConsent"));
             await new Promise((r) => setTimeout(r, 200));

             return { drawn: !!root.querySelector(".leaflet-container"), prompt: !root.querySelector("[data-ui-map-target=consent]").hidden };',
            ['needsConsent' => 'true', 'banner' => true, 'consent' => 'false']
        );

        $this->assertTrue($state['drawn'], 'Consent was given and no map was drawn, so a returning visitor is asked again for nothing.');
        $this->assertFalse($state['prompt'], 'The prompt still asks for an answer that has been given.');
    }

    // A tile server refusing the request, a key Google turned down, a stylesheet that never arrived: the list of places is what stays
    public function testALibraryThatCannotBeLoadedLeavesTheListOnScreen(): void
    {
        $state = $this->map(
            'return { canvas: root.querySelector("[data-ui-map-target=canvas]").hidden, list: root.querySelectorAll("[data-ui-map-target=list] li").length };',
            // Its own copy of the controller: the loader is kept for the life of the document, so a map drawn by an earlier scenario would hand this one a library already in place
            ['script' => 'http://127.0.0.1:1/leaflet.js', 'fresh' => true]
        );

        $this->assertTrue($state['canvas'], 'A map that could not be drawn left an empty box on screen.');
        $this->assertSame(2, $state['list'], 'A failure to draw took the places away with it.');
    }

    // A block holding no place draws nothing at all rather than a map centred on nowhere
    public function testABlockWithNoPlaceDrawsNothing(): void
    {
        $this->assertFalse(
            (bool) $this->map('return !!root.querySelector(".leaflet-container");', ['points' => '[]']),
            'A block holding no place still built a map, centred on a point that does not exist.'
        );
    }

    // Turbo caches the page as it stands, and a drawn map holds listeners on the window it would otherwise keep
    public function testDisconnectingTakesTheDrawnMapDown(): void
    {
        $this->assertTrue(
            (bool) $this->map(
                'const block = root.querySelector("[data-controller~=ui-map]");
                 document.createElement("div").appendChild(block);
                 await new Promise((r) => setTimeout(r, 100));

                 return !block.querySelector(".leaflet-container .leaflet-pane");'
            ),
            'The drawn map outlived the block it belongs to, so a cached page comes back holding a map nothing owns.'
        );
    }

    // A health check is read by somebody who went looking; the person who has just placed the block is looking at the page. Said in place, and only to whoever may act on it
    public function testAMapThatCouldNotBeLoadedSaysWhyToWhoeverMayActOnIt(): void
    {
        $said = $this->map(
            'return { text: root.querySelector("[data-ui-map-target=diagnostic]").textContent, hidden: root.querySelector("[data-ui-map-target=diagnostic]").hidden };',
            ['script' => 'http://127.0.0.1:1/leaflet.js', 'fresh' => true, 'diagnostic' => true]
        );

        $this->assertSame('La carte n\'a pas pu etre chargee.', $said['text'], 'A map that could not be loaded says nothing where it should have been drawn.');
        $this->assertFalse($said['hidden'], 'The reason was written where nobody can read it.');
    }

    // A visitor is shown the list of places and told nothing about a key or a policy that is not theirs to change - the element is not even rendered for them
    public function testAVisitorIsToldNothingAtAll(): void
    {
        $this->assertSame(
            0,
            $this->map(
                'return root.querySelectorAll(".ui-map__diagnostic").length;',
                ['script' => 'http://127.0.0.1:1/leaflet.js', 'fresh' => true]
            ),
            'A visitor is shown why a map is missing, on a site whose content is the list of places under it.'
        );
    }

    // A policy refusing Google's host blocks the fetch and leaves nothing a script can read afterwards: this event is the only account of it, and naming the directive is what makes the sentence actionable
    public function testAMapRefusedByThePolicyNamesTheDirectiveThatRefusedIt(): void
    {
        $said = $this->map(
            'document.dispatchEvent(new SecurityPolicyViolationEvent("securitypolicyviolation", {
                 bubbles: true,
                 documentURI: window.location.href,
                 referrer: "",
                 blockedURI: "https://maps.googleapis.com/maps/api/js",
                 violatedDirective: "script-src-elem",
                 effectiveDirective: "script-src-elem",
                 originalPolicy: "script-src \'self\'",
                 disposition: "enforce",
                 sourceFile: "",
                 sample: "",
                 statusCode: 200,
                 lineNumber: 0,
                 columnNumber: 0,
             }));
             window.scrollTo(0, 3000);
             await new Promise((r) => setTimeout(r, 400));

             return root.querySelector("[data-ui-map-target=diagnostic]").textContent;',
            ['script' => 'http://127.0.0.1:1/leaflet.js', 'fresh' => true, 'diagnostic' => true, 'below' => true]
        );

        $this->assertSame(
            'La politique du site (script-src-elem) refuse les hotes de Google.',
            $said,
            'A map the site\'s own policy refused is reported as a provider that did not answer, which sends whoever reads it looking at the wrong thing.'
        );
    }

    // A font, another embed, anything else the policy refuses on the same page is not this block's to report
    public function testAViolationRaisedBySomethingElseIsNotAttributedToTheMap(): void
    {
        $said = $this->map(
            'document.dispatchEvent(new SecurityPolicyViolationEvent("securitypolicyviolation", {
                 bubbles: true,
                 documentURI: window.location.href,
                 referrer: "",
                 blockedURI: "https://fonts.googleapis.com/css2",
                 violatedDirective: "style-src-elem",
                 effectiveDirective: "style-src-elem",
                 originalPolicy: "style-src \'self\'",
                 disposition: "enforce",
                 sourceFile: "",
                 sample: "",
                 statusCode: 200,
                 lineNumber: 0,
                 columnNumber: 0,
             }));
             window.scrollTo(0, 3000);
             await new Promise((r) => setTimeout(r, 400));

             return root.querySelector("[data-ui-map-target=diagnostic]").textContent;',
            ['script' => 'http://127.0.0.1:1/leaflet.js', 'fresh' => true, 'diagnostic' => true, 'below' => true]
        );

        $this->assertSame('La carte n\'a pas pu etre chargee.', $said, 'A stylesheet somebody else\'s policy refused was reported as the reason this map is missing.');
    }

    // Google copies a nonce it finds on the page onto the <style> and <script> elements its API injects afterwards (see its Content-Security-Policy guide), which is the whole reason a site can keep "style-src: \'self\'" with a nonce and still draw a Google map - the alternative being to open every page of that site to inline styles for the sake of one block
    // Nothing here reaches Google: the loader element is caught as it is appended, before the browser has been given anything to fetch
    public function testTheGoogleLoaderCarriesThePagesNonceAndAStyleToReadItFrom(): void
    {
        $appended = $this->loader('jeton-de-la-page');

        $this->assertCount(2, $appended, 'The loader was appended alone, so Google has no style element to read a nonce from and its own are dropped by a nonced policy.');
        $this->assertSame('STYLE', $appended[0]['tag'], 'The style carrier does not come first, and Google reads the nonce of the first element of each kind it finds.');
        $this->assertSame('jeton-de-la-page', $appended[0]['nonce'], 'The style carrier does not carry the page\'s own nonce.');
        $this->assertSame('SCRIPT', $appended[1]['tag']);
        $this->assertSame('jeton-de-la-page', $appended[1]['nonce'], 'The loader itself carries no nonce, so a nonced script-src refuses it outright.');
        $this->assertStringStartsWith('https://maps.googleapis.com/', (string) $appended[1]['src']);
    }

    // A site serving no policy at all has no nonce to give, and an empty one written onto an element is worse than none
    public function testAPageServingNoPolicyAppendsNothingButTheLoader(): void
    {
        $appended = $this->loader(null);

        $this->assertCount(1, $appended, 'A page with no policy was given a style element it has no use for.');
        $this->assertSame('SCRIPT', $appended[0]['tag']);
        $this->assertSame('', $appended[0]['nonce'], 'An empty nonce was written onto the loader.');
    }

    /**
     * The Google loader caught as it is appended, the head standing in for itself so nothing is ever fetched.
     */
    private function loader(?string $nonce): mixed
    {
        return $this->map(
            'try {
                 await new Promise((r) => setTimeout(r, 50));

                 return window.__appended.map((element) => ({ tag: element.tagName, nonce: element.nonce, src: element.src ?? null }));
             } finally {
                 document.head.append = window.__headAppend;
                 document.querySelector("meta[name=csp-nonce]")?.remove();
             }',
            [
                'provider' => 'google',
                // Its own copy of the controller: the loader is kept for the life of the document, and a scenario finding one already there appends nothing
                'fresh' => true,
                'head' => sprintf(
                    '%s
                     window.__appended = [];
                     window.__headAppend = document.head.append;
                     document.head.append = function (...nodes) { window.__appended.push(...nodes); };',
                    null === $nonce
                        ? ''
                        : sprintf(
                            'const meta = document.createElement("meta");
                             meta.name = "csp-nonce";
                             meta.content = %s;
                             document.head.appendChild(meta);',
                            json_encode($nonce, \JSON_THROW_ON_ERROR)
                        )
                ),
            ]
        );
    }

    private function map(string $probe, array $options = []): mixed
    {
        // Defaults an option overrides, rather than one "??" per option: the complexity gate reads each of those as a branch, where this is a single set of values
        $options = array_replace([
            'points' => self::POINTS,
            'fresh' => false,
            'banner' => false,
            'below' => false,
            'provider' => 'leaflet',
            'script' => $this->url('public/js/leaflet.js'),
            'needsConsent' => 'false',
            'diagnostic' => false,
            'consent' => 'false',
            'head' => '',
        ], $options);

        $points = $options['points'];
        $banner = $options['banner'] ? self::BANNER : '';
        // A block below the fold is not drawn until it is scrolled to, which is the only window a scenario has to say something before the load is attempted
        $banner .= $options['below'] ? '<div class="ui-map__spacer"></div>' : '';

        $items = '';
        foreach (json_decode($points, true, 512, \JSON_THROW_ON_ERROR) as $point) {
            $items .= sprintf('<li>%s</li>', htmlspecialchars((string) $point['label'], \ENT_QUOTES));
        }

        return $this->observe(
            sprintf(
                '%s<div class="ui-map" data-controller="ui-map"
                     data-ui-map-provider-value="%s"
                     data-ui-map-script-value="%s"
                     data-ui-map-stylesheet-value="%s"
                     data-ui-map-tile-url-value="https://127.0.0.1:1/{z}/{x}/{y}.png"
                     data-ui-map-attribution-value="OpenStreetMap"
                     data-ui-map-needs-consent-value="%s"
                     data-ui-map-zoom-value="11"
                     data-ui-map-diagnostic-value="La carte n\'a pas pu etre chargee."
                     data-ui-map-diagnostic-csp-value="La politique du site (%%directive%%) refuse les hotes de Google."
                     data-ui-map-points-value="%s">
                    <div data-ui-map-target="canvas" class="ui-map__canvas" hidden></div>
                    <div data-ui-map-target="consent" hidden><button type="button" data-action="ui-map#accept">Accepter</button></div>
                    %s
                    <ul data-ui-map-target="list">%s</ul>
                </div>',
                $banner,
                $options['provider'],
                $options['script'],
                $this->url('public/css/leaflet.css'),
                $options['needsConsent'],
                htmlspecialchars($points, \ENT_QUOTES),
                $options['diagnostic'] ? '<p class="ui-map__diagnostic" data-ui-map-target="diagnostic" hidden></p>' : '',
                $items
            ),
            ['ui-map' => 'map'],
            $probe,
            [
                'before' => self::HOOK . sprintf(self::CONSENT, $options['consent']) . $options['head'],
                'css' => '.ui-map__canvas { width: 600px; height: 400px; } .ui-map__spacer { height: 3000px; }',
                'fresh' => $options['fresh'],
                'settle' => 350,
            ]
        );
    }
}
