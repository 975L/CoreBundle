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

// The vendored canvas-confetti actually run, through the one call assets/js/confetti.js makes on it
// What it does for a visitor asking for less animation is not checked here: the library reads that preference once, as it loads, and the page this suite shares loads it once for the whole run - so a stub set afterwards changes nothing. That guarantee is this bundle's own anyway, and ConfettiBehaviourTest holds it: the controller downloads nothing at all in that case
// VendorAssetsTest reads the shipped file for the names this bundle calls, which passes on any file carrying the new version number, working or not. This runs it: a bump that renamed an option, or dropped the global a classic script is loaded for, gets caught here and nowhere else
#[Group('browser')]
class ConfettiContractTest extends JsCase
{
    private const string LIBRARY = 'public/js/confetti.browser.min.js';

    // The library is loaded as a classic script by confetti.js, so the global is the whole interface: no global, no confetti, and nothing at all in the console to say why
    public function testTheVendoredLibraryStillDefinesTheGlobalAClassicScriptIsLoadedFor(): void
    {
        $this->assertSame('function', $this->observe('<div></div>', [], 'return typeof window.confetti;', ['scripts' => [self::LIBRARY]]));
    }

    // The exact call confetti.js makes. It answers a promise resolving when the animation is over, which is what says the options were understood rather than quietly ignored
    public function testTheCallThisBundleMakesDrawsOnACanvasAndSettles(): void
    {
        $drawn = $this->fire(
            'const done = window.confetti({ particleCount: 500, disableForReducedMotion: true });
             const promised = done instanceof Promise;
             const drawing = document.querySelectorAll("canvas").length;
             await done;

             return { promised, drawing };'
        );

        $this->assertTrue($drawn['promised'], 'canvas-confetti no longer answers a promise, so nothing can tell when the animation is over.');
        $this->assertGreaterThan(0, $drawn['drawing'], 'The call put no canvas in the page at all: the options this bundle passes are no longer ones the library acts on.');
    }

    // The library keeps the canvas it made for as long as the page lives, so taking it away leaves it drawing on something detached (see JsCase, and the dialog block-picker owns the same way)
    private function fire(string $probe): mixed
    {
        return $this->observe('<div></div>', [], $probe, ['scripts' => [self::LIBRARY], 'keepBody' => true]);
    }
}
