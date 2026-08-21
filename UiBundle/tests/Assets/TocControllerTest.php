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

// The summary is a working list of links without a line of JS: what the controller adds is the two things no selector can ask - which section the reader is in, and how tall the bar covering the page is
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

        $this->assertStringContainsString('static targets = ["link"];', $this->read(self::CONTROLLER_JS));
        $this->assertStringContainsString('data-toc-target="link"', $component);
        $this->assertStringContainsString('data-toc-anchor="{{ entry.anchor }}"', $component);
    }

    // The class marking the entry being read is added after measure and never written in the markup, so a browser reaching no JS gets a summary with no entry wrongly lit
    public function testTheCurrentClassIsAddedByTheControllerAloneAndNeverByTheMarkup(): void
    {
        $this->assertStringContainsString('"toc-link--current"', $this->read(self::CONTROLLER_JS));
        $this->assertStringNotContainsString('toc-link--current', $this->read(self::COMPONENT));
    }

    // Said to a screen reader as well as shown, and taken back off the entry that stops being current
    public function testTheCurrentEntryIsAlsoSaidRatherThanOnlyColoured(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('setAttribute("aria-current", "true")', $controller);
        $this->assertStringContainsString('removeAttribute("aria-current")', $controller);
    }

    // The bar comes to rest over the page, so a section jumped to would land under it. The room is left by the stylesheet alone, on .toc-target: a nonced style-src drops any rule a script writes onto an element, which is what measuring it here would have come to
    public function testTheRoomLeftAboveASectionIsTheStylesheetsAndNeverWrittenByTheController(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('scroll-margin-top: calc(var(--toc-sticky-top) + var(--toc-height));', $this->read(self::STYLESHEET));
        $this->assertStringNotContainsString('scrollMarginTop', $controller);
        $this->assertStringNotContainsString('getBoundingClientRect', $controller);
    }

    // Several sections share the observer's band while scrolling: the one being read is the first of them in the page's own order, not the last the observer happened to report
    public function testTheCurrentSectionIsTheFirstOfThoseInTheBandAndNotTheLastReported(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('for (const section of this.sections.keys()) {', $controller);
        $this->assertStringContainsString('break;', $controller);
    }

    // Turbo caches the page as it stands, so the marks are undone rather than left frozen in a snapshot restored before this controller connects again
    public function testTheControllerUndoesItselfOnDisconnect(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.observer?.disconnect();', $controller);
        $this->assertStringContainsString('for (const link of this.sections.values()) {', $controller);
        $this->assertStringContainsString('this.mark(link, false);', $controller);
    }

    // Between two sections nothing crosses the band: an emptied summary reads as broken where the reader has simply not reached the next one yet
    public function testTheLastLitEntryStaysLitWhileNoSectionCrossesTheBand(): void
    {
        $this->assertStringContainsString("if (!current) {\n            return;\n        }", $this->read(self::CONTROLLER_JS));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
