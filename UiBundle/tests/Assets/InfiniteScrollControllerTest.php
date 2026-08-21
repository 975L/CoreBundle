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

// The controller reads the page it fetches through the very attributes the listing writes, so the identifier, the barrel and those attributes are one contract - and a consuming bundle's template is the other end of it, with no browser here to catch a drift
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

    // The fetched page is a detached document, where Stimulus has connected nothing: the items and the next link are found by the attributes themselves, which have to spell the registered identifier
    public function testTheFetchedPageIsReadThroughTheIdentifiersOwnAttributes(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString(sprintf('[data-%s-target="list"]', self::IDENTIFIER), $controller);
        $this->assertStringContainsString(sprintf('[data-%s-target="next"]', self::IDENTIFIER), $controller);
    }

    // The link is the fallback the whole thing rests on - without javascript, and for a crawler, it is an ordinary link to the next page
    public function testAClickLoadsInPlaceInsteadOfLeavingThePage(): void
    {
        $this->assertStringContainsString('event?.preventDefault();', $this->read(self::CONTROLLER_JS));
    }

    // An observer reports changes of state and stays silent on a link that never left the viewport, which is what a short page of items leaves it in
    public function testTheLinkIsObservedAgainAfterEachPage(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.observer?.unobserve(this.nextTarget);', $controller);
        $this->assertStringContainsString('this.observer?.observe(this.nextTarget);', $controller);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
