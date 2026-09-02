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

// The barrel entry, and the one arming a listing that stopped growing cannot be seen from: an observer reports changes of state, and says nothing at all about a link that never left the viewport
// What the controller does with a fetched page - what it appends, what it resolves the next url against, when it pauses and when it gives up - is InfiniteScrollBehaviourTest's, run in a browser. It used to be asserted here as lines of source
class InfiniteScrollControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/infinite-scroll.js';
    private const string BARREL = 'assets/controllers.js';
    private const string IDENTIFIER = 'infiniteScroll';

    // Lazily registered: a listing that grows on scroll is one page kind among many, and the barrel only loads what the document asks for
    public function testTheControllerIsRegisteredAsLazy(): void
    {
        $this->assertStringContainsString(
            sprintf("%s: () => import('./js/infinite-scroll.js'),", self::IDENTIFIER),
            $this->read(self::BARREL)
        );
    }

    // An observer reports changes of state and stays silent on a link that never left the viewport, which is what a short page of items leaves it in
    public function testTheLinkIsObservedAgainAfterEachPage(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.observer?.unobserve(this.nextTarget);', $controller);
        $this->assertStringContainsString('this.observer?.observe(this.nextTarget);', $controller);
    }

    // The pause lasts until the visitor scrolls by themselves, which is them reading the listing again rather than the footer they asked for - "pointerdown" among them because dragging the scrollbar fires none of the other three
    public function testTheListingGrowsAgainOnTheVisitorsOwnScroll(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        // The four gestures are a declaration rather than a behaviour: a scenario can only ever fire one of them, and "pointerdown" is there because dragging the scrollbar fires none of the other three
        $this->assertStringContainsString('static RESUME_EVENTS = ["wheel", "touchstart", "keydown", "pointerdown"];', $controller);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
