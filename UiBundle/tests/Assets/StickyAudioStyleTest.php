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

// The player an <audio> given sticky="true" prints: a bar coming to rest against the bottom of the screen, the way the summary of anchors rests against its top
class StickyAudioStyleTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // Sticky and opaque: the column slides under the player, and sticky rather than fixed is what lets the bar take its own room at the end of that column instead of hiding what ends it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePlayerRestsAgainstTheBottomAndHidesWhatSlidesUnderIt(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.audio-figure--sticky{position:sticky;bottom:0', $css, sprintf('"%s" no longer rests the sticky player against the bottom of the screen.', $file));
        $this->assertStringContainsString('background:var(--audio-sticky-background)', $css, sprintf('"%s" leaves the player transparent, the column then scrolling through it.', $file));
    }

    // A plain <audio> is untouched: the bar is what sticky="true" asks for, never what every player has to undo
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAPlainPlayerIsLeftWhereItWasPrinted(string $file): void
    {
        $this->assertStringNotContainsString('.audio-figure{position:sticky', $this->normalize($file), sprintf('"%s" pins every audio player, not the ones asking for it.', $file));
    }

    // A player resting against the bottom of a screen has no meaning on paper
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePlayerIsNotPinnedOnPaper(string $file): void
    {
        $this->assertStringContainsString('@mediaprint{.audio-figure--sticky{position:static', $this->normalize($file), sprintf('"%s" prints a pinned player onto paper.', $file));
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
