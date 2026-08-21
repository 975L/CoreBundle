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

// SiteBundle's layout writes a <base href="…/">, against which a bare "#anchor" resolves: every entry of the summary then leads to the home page, silently, on every site running that layout
class TocAnchorHrefTest extends TestCase
{
    public function testAnEntryNamesThePageBeforeItsFragment(): void
    {
        $twig = $this->template();

        $this->assertStringContainsString('href="{{ app.request.requestUri }}#{{ entry.anchor }}"', $twig);
        $this->assertStringNotContainsString('href="#{{ entry.anchor }}"', $twig, 'A bare fragment resolves against the layout\'s <base>, sending every summary entry to the home page.');
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
