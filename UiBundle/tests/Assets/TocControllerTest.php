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

// What the markup, the barrel and the stylesheet have to say for the controller to have anything to work with - read as text, which is what they are
// What the controller then does with it is TocBehaviourTest's, run in a browser: which entry lights up, what it announces, and what it leaves behind on disconnect were all asserted here as lines of source, which passes on a line sitting in a branch nothing reaches and fails on a rename that changed nothing
class TocControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/toc.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Text/Toc.html.twig';
    private const string STYLESHEET = 'sass/_toc.scss';

    // Public pages only, and lazily: the barrel imports it for a document that actually holds a summary
    public function testTheControllerIsRegisteredAsALazyFrontControllerNamedAfterWhatTheTemplateWrites(): void
    {
        $this->assertStringContainsString("toc: () => import('./js/toc.js'),", $this->read(self::BARREL));
        $this->assertStringContainsString('data-controller="toc"', $this->read(self::COMPONENT));
    }

    // The links are the controller's whole reading of the page: each one names the section it points at, which is how the observer finds what to watch
    public function testEachLinkNamesTheSectionTheControllerGoesLookingFor(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('data-toc-target="link"', $component);
        $this->assertStringContainsString('data-toc-anchor="{{ entry.anchor }}"', $component);
    }

    // The class marking the entry being read is added after measure and never written in the markup, so a browser reaching no JS gets a summary with no entry wrongly lit
    public function testTheCurrentClassIsAddedByTheControllerAloneAndNeverByTheMarkup(): void
    {
        $this->assertStringNotContainsString('toc-link--current', $this->read(self::COMPONENT));
    }

    // The bar comes to rest over the page, so a section jumped to would land under it. The room is left by the stylesheet alone, on .toc-target: a nonced style-src drops any rule a script writes onto an element, which is what measuring it here would have come to
    public function testTheRoomLeftAboveASectionIsTheStylesheetsAndNeverWrittenByTheController(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('scroll-margin-top: calc(var(--toc-sticky-top) + var(--toc-height));', $this->read(self::STYLESHEET));
        $this->assertStringNotContainsString('scrollMarginTop', $controller);
        $this->assertStringNotContainsString('getBoundingClientRect', $controller);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
