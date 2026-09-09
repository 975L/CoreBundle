<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The picture a page is built around is above the fold, so this component defaults to eager - but a zoom placed further down has to be able to say so, and Twig's "default" filter fires on false as well as on undefined, which silently takes that choice away
class ImageZoomPriorityTest extends TestCase
{
    private const string TEMPLATE = 'templates/components/Image/Zoom.html.twig';

    public function testPriorityCanBeTurnedOff(): void
    {
        $template = $this->read();

        $this->assertStringContainsString('{% set priority = priority is defined ? priority|to_bool : true %}', $template, sprintf('"%s" resolves its priority with a filter that fires on false, so a zoom placed further down the page can no longer be lazy-loaded.', self::TEMPLATE));
        $this->assertStringNotContainsString('priority|default(', $template, sprintf('"%s" is back to the "default" filter, whose empty-value behaviour turns ":priority=\"false\"" into true.', self::TEMPLATE));
    }

    // Both loading modes stay written out, the flag choosing between them rather than only opting out of one
    public function testBothLoadingModesAreWrittenOut(): void
    {
        $template = $this->read();

        $this->assertStringContainsString('{% if priority %} loading="eager" fetchpriority="high"{% else %} loading="lazy"{% endif %}', $template, sprintf('"%s" no longer writes both loading modes, so turning the priority off leaves the image with no loading attribute at all.', self::TEMPLATE));
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
