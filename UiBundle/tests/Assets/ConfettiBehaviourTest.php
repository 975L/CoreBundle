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

// assets/js/confetti.js, with the library it loads answered by a stub that records the call rather than by the real one
// The promise worth checking here is the one about a visitor asking for less animation: they are spared the whole library, not merely its effect. Nothing about that is readable in the file - the guard sits in connect(), before the download, and moving it below would still look perfectly reasonable
#[Group('browser')]
class ConfettiBehaviourTest extends JsCase
{
    // Stands in for canvas-confetti, so no animation is waited out and the call itself can be read back
    private const string STUB = 'data:text/javascript,window.__fired=window.__fired||[];window.confetti=(options)=>{window.__fired.push(options);return Promise.resolve()};';

    // Also says the controller does not wait for a DOMContentLoaded this page passed long ago: it is imported lazily, so connect() mostly runs after it, and subscribing without reading readyState never fired again
    public function testTheLibraryIsFetchedAndFiredWithTheCallThisBundleMakes(): void
    {
        $fired = $this->confetti('return { fired: window.__fired };');

        $this->assertCount(1, $fired['fired'], 'The library was fetched and never fired, or fired more than once for a single connect.');
        $this->assertSame(500, $fired['fired'][0]['particleCount'], 'The call is no longer the one this bundle makes.');
        $this->assertTrue($fired['fired'][0]['disableForReducedMotion'], 'The library is no longer asked to keep quiet for a visitor who wants less animation.');
    }

    // Not merely "it draws nothing": the point is that nothing is downloaded at all, the guard sitting in connect() before the load
    public function testAVisitorAskingForLessAnimationIsSparedTheDownloadEntirely(): void
    {
        $quiet = $this->confetti(
            // Only what this scenario appended: the head keeps every load of the run, this page being shared by all of them
            'return { fired: window.__fired.length, loaded: [...document.head.children].filter((el) => !window.__head.has(el)).length };',
            'const original = window.matchMedia;
             window.matchMedia = (query) => query.includes("prefers-reduced-motion")
                 ? { matches: true, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }
                 : original.call(window, query);'
        );

        $this->assertSame(0, $quiet['fired'], 'The confetti fired for a visitor who asked for less animation.');
        $this->assertSame(0, $quiet['loaded'], 'The library was downloaded for a visitor who was never going to see it.');
    }

    private function confetti(string $probe, string $before = ''): mixed
    {
        return $this->observe(
            sprintf('<div data-controller="confetti" data-confetti-script-value="%s"></div>', htmlspecialchars(self::STUB, \ENT_QUOTES)),
            ['confetti' => 'confetti'],
            $probe,
            // Cleared per scenario, and the library's own canvas left where it is: it belongs to the page for as long as the page lives
            [
                // The library's own canvas is left where it is: it belongs to the page for as long as the page lives
                'before' => 'window.__fired = []; window.__head = new Set(document.head.children); ' . $before,
                'settle' => 120,
                'keepBody' => true,
            ]
        );
    }
}
