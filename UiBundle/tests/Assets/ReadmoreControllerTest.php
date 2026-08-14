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

// The fold is the stylesheet's alone (see ReadmoreStyleTest): what the controller adds is the one thing no selector can ask, whether the clamp clamped anything - and it says it across three files no browser here runs, so each end is checked against what the other two assume
class ReadmoreControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/readmore.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Text/Readmore.html.twig';

    // Public pages only, and lazily: the barrel imports it on demand, for a document that actually holds one
    public function testTheControllerIsRegisteredAsALazyFrontControllerNamedAfterWhatTheTemplateWrites(): void
    {
        $this->assertStringContainsString("readmore: () => import('./js/readmore.js'),", $this->read(self::BARREL));
        $this->assertStringContainsString('data-controller="readmore"', $this->read(self::COMPONENT));
    }

    // The measure is taken on the content box, and the open state is read off the checkbox the fold itself is keyed on
    public function testBothTargetsTheControllerReadsAreWrittenByTheComponent(): void
    {
        $component = $this->read(self::COMPONENT);
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static targets = ["content", "toggle"];', $controller);
        $this->assertStringContainsString('data-readmore-target="content"', $component);
        $this->assertStringContainsString('data-readmore-target="toggle"', $component);
    }

    // The class the stylesheet keys the link's removal on, added after measure and never written in the markup: a browser reaching no JS keeps a link that costs at most a click turning nothing
    public function testTheClassIsAddedByTheControllerAloneAndNeverByTheMarkup(): void
    {
        $this->assertStringContainsString('"readmore--complete"', $this->read(self::CONTROLLER_JS));
        $this->assertStringNotContainsString('readmore--complete', $this->read(self::COMPONENT));
    }

    // Nothing to measure while the text is open - the box then holds all of it, and reading that as "complete" would take the link away with no way back
    public function testTheMeasureIsSkippedWhileTheTextIsOpen(): void
    {
        $this->assertStringContainsString('if (this.toggleTarget.checked) {', $this->read(self::CONTROLLER_JS));
    }

    // One measure is never enough: the box is resized by the page around it, and web fonts land after the first one and change how many lines the text takes
    public function testTheMeasureIsRedoneOnResizeAndOnceTheFontsHaveLanded(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('new ResizeObserver(this.measure)', $controller);
        $this->assertStringContainsString('this.observer.observe(this.contentTarget)', $controller);
        $this->assertStringContainsString('document.fonts?.ready.then(this.measure)', $controller);
    }

    // Turbo caches the page as it stands, so a snapshot restored before this controller connects again would carry a class nothing measured
    public function testTheControllerUndoesItselfOnDisconnect(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.observer.disconnect();', $controller);
        $this->assertStringContainsString('this.element.classList.remove("readmore--complete");', $controller);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
