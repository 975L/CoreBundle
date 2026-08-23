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

// A bare fragment is what a summary entry links to - it used to have to name the page first, the layout writing a <base href="…/"> a fragment resolved against, which sent every entry to the home page. The base went with the merge into this bundle's layout in 08/2026
class TocAnchorHrefTest extends TestCase
{
    public function testAnEntryLinksToItsFragmentAlone(): void
    {
        $twig = $this->template();

        $this->assertStringContainsString('href="#{{ entry.anchor }}"', $twig);
        $this->assertStringNotContainsString('app.request.requestUri', $twig, 'Naming the page before the fragment makes every summary entry a full navigation, the layout writing no <base> to resolve against any more.');
    }

    // The controller reads the anchor off the attribute, never off the href: that is what keeps the two independent
    public function testTheControllerStillReadsTheAnchorOffItsOwnAttribute(): void
    {
        $this->assertStringContainsString('data-toc-anchor="{{ entry.anchor }}"', $this->template());
    }

    private function template(): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/components/Text/Toc.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
