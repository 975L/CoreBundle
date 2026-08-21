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

// Mobile first: the summary is a bar of chips coming to rest under the header, and only becomes a column once there is room beside the text for one
class TocStyleTest extends TestCase
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

    // Sticky and opaque: the bar has the page sliding under it, and comes to rest exactly where a fixed header stops covering it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheBarComesToRestUnderTheHeaderAndHidesWhatSlidesUnderIt(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('position:sticky;top:var(--toc-sticky-top)', $css, sprintf('"%s" no longer pins the summary under the header.', $file));
        $this->assertStringContainsString('background:var(--toc-background)', $css, sprintf('"%s" leaves the summary transparent, the page then scrolling through it.', $file));
    }

    // The chips are the whole navigation of the page on a phone, and a thumb is what reaches them
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAnEntryIsAlwaysAsTallAsAThumbNeeds(string $file): void
    {
        $this->assertStringContainsString('min-height:44px', $this->normalize($file), sprintf('"%s" lets a summary entry fall under the 44px touch target.', $file));
    }

    // Nine sections cost one line rather than nine, the row scrolling sideways with no scrollbar laid over the chips themselves
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheEntriesScrollSidewaysRatherThanWrapping(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.toc-list{display:flex', $css);
        $this->assertStringContainsString('overflow-x:auto', $css);
    }

    // The column is the second state of the same list, never the first: written under a min-width, a phone never reads it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheColumnIsAskedForRatherThanUndone(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('@media(min-width:1200px)', $css, sprintf('"%s" no longer holds the desktop step of the summary.', $file));
        $this->assertStringContainsString('.toc{width:var(--toc-column-width)', $css);
        // The bar's own rules stand outside any query, the column being what a wide screen adds - never a phone undoing what it was given first
        $this->assertStringContainsString('.toc-list{flex-direction:column', $css);
    }

    // A summary of anchors leads nowhere on paper, where every section it points at is printed below anyway
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheSummaryIsNotPrinted(string $file): void
    {
        $this->assertStringContainsString('@mediaprint{.toc{display:none', $this->normalize($file), sprintf('"%s" prints a summary of links onto paper.', $file));
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
