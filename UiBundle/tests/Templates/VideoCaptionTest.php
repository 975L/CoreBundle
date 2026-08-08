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

// Same prop and same meaning as Video:Iframe's (see VideoIframeCaptionTest), so a page laying out its own heading around either video kind turns it off the same way
class VideoCaptionTest extends TestCase
{
    private const TEMPLATE = 'templates/components/Video/Video.html.twig';

    public function testTheCaptionIsShownUnlessItIsTurnedOff(): void
    {
        $template = $this->read();

        $this->assertStringContainsString('{% set caption = caption is defined ? caption|to_bool : true %}', $template, sprintf('"%s" no longer defaults its caption to shown, so every existing block loses its heading.', self::TEMPLATE));
        $this->assertStringContainsString('{% if caption and title is defined and title %}', $template);
        $this->assertStringContainsString('{% if caption and description is defined and description %}', $template);
    }

    // The player itself is never behind the flag: turning the caption off hides the figure's own heading, not the video
    public function testThePlayerIsRenderedWhateverTheCaption(): void
    {
        $lines = array_values(array_filter(explode("\n", $this->read()), static fn (string $line): bool => str_contains($line, '<video ')));

        $this->assertCount(1, $lines, sprintf('No single <video> tag found in "%s", the test itself is broken.', self::TEMPLATE));
        $this->assertStringNotContainsString('caption', $lines[0], sprintf('"%s" renders its <video> inside a caption condition, so turning the caption off takes the player with it.', self::TEMPLATE));
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
