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

// What the barrel and the component have to say for the controller to have anything to work with, read as text - which is what they are, and what ReadmoreBehaviourTest cannot see: it mounts markup of its own rather than this component
// What the controller then does with that markup is that test's, run in a browser: the measure, the one skipped while the text is open, the one redone on resize, and what disconnecting leaves behind were all asserted here as lines of source
class ReadmoreControllerTest extends TestCase
{
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

        $this->assertStringContainsString('data-readmore-target="content"', $component);
        $this->assertStringContainsString('data-readmore-target="toggle"', $component);
    }

    // The class the stylesheet keys the link's removal on, added after measure and never written in the markup: a browser reaching no JS keeps a link that costs at most a click turning nothing
    public function testTheClassIsAddedByTheControllerAloneAndNeverByTheMarkup(): void
    {
        $this->assertStringNotContainsString('readmore--complete', $this->read(self::COMPONENT));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
