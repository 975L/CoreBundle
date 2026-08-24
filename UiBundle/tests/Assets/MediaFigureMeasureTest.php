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

// A "video", "video_iframe" or "audio" block dropped straight in a page has no ".section-wrap" around it: these rules are what put it on the page measure and its title at a section title's size, and dropping any of them sends the figure back to the full width of the screen
class MediaFigureMeasureTest extends TestCase
{
    // The selectors as they read once normalized: no space around the combinators
    private const string FIGURE = ':is(.blocks,.block-animation,.block-editable)>:is(.video-figure,.video-iframe-figure,.audio-figure)';
    private const string TITLE = self::FIGURE . '>:is(.video-title,.video-iframe-title,.audio-title)';
    // The vertical step stands apart: a sticky audio player paints its own padding, so it is the one figure the step is kept off
    private const string STEP = self::FIGURE . ':not(.audio-figure--sticky)';

    // Read as ".section-wrap" reads them, else the figure misaligns with the section above it
    private const array MEASURE = [
        'box-sizing:border-box',
        'max-width:var(--section-wrap-max-width,var(--body-max-width,1440px))',
        'margin-inline:auto',
        'padding-left:var(--section-wrap-gutter,clamp(20px,5vw,64px))',
        'padding-right:var(--section-wrap-gutter,clamp(20px,5vw,64px))',
    ];

    // ".section-title"'s own scale and staircase indent, the multiplication written in two needles: sass keeps the spaces around its operator in the expanded sheet and drops them in the minified one
    private const array TITLE_SCALE = [
        'font-size:calc(clamp(30px,4.4vw,42px)',
        'var(--font-size-title-scale,1))',
        'padding-inline-start:var(--section-head-indent,32px)',
    ];

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

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheStandaloneFigureStopsAtThePageMeasure(string $file): void
    {
        $declarations = $this->declarationsOf($this->normalize($file), self::FIGURE, $file);

        foreach (self::MEASURE as $needle) {
            $this->assertStringContainsString(
                $needle,
                $declarations,
                sprintf('The standalone media figure no longer reads "%s" in "%s".', $needle, $file)
            );
        }
    }

    // Off a sticky audio player, whose own thin bar this step would turn into a band following the reader down the page
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheStandaloneFigureTakesOneVerticalStepButNotWhenSticky(string $file): void
    {
        $this->assertStringContainsString(
            'padding-top:var(--section-space,clamp(48px,8vw,84px))',
            $this->declarationsOf($this->normalize($file), self::STEP, $file),
            sprintf('The standalone media figure no longer takes the page step in "%s".', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheStandaloneTitleReadsASectionTitleScale(string $file): void
    {
        $declarations = $this->declarationsOf($this->normalize($file), self::TITLE, $file);

        foreach (self::TITLE_SCALE as $needle) {
            $this->assertStringContainsString(
                $needle,
                $declarations,
                sprintf('The standalone media title no longer reads "%s" in "%s".', $needle, $file)
            );
        }
    }

    // What makes the back-office width and height optional: the player takes the width of the box it stands in, instead of the "iframe { width: 95vw; max-width: 400px }" a bare frame is drawn at
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePlayerFillsTheFigureItStandsIn(string $file): void
    {
        $declarations = $this->declarationsOf($this->normalize($file), '.video-iframe-consent iframe', $file);

        foreach (['width:100%', 'max-width:100%'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $declarations,
                sprintf('The consent player no longer reads "%s" in "%s", so a video without a width falls back to the bare iframe size.', $needle, $file)
            );
        }
    }

    // Strips comments and collapses whitespace, commas included, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:>,])\s*/', '$1', $css);

        return (string) preg_replace('/\s+/', ' ', $css);
    }

    // The body of the rule opened by $selector - matched on the selector itself rather than on a comma-split list, ":is()" carrying commas of its own
    private function declarationsOf(string $css, string $selector, string $file): string
    {
        $pattern = '/(?:^|[{}])\s*' . preg_quote($selector, '/') . '\{([^{}]*)\}/';
        if (1 !== preg_match($pattern, $css, $match)) {
            $this->fail(sprintf('"%s" has no "%s" rule at all.', $file, $selector));
        }

        return $match[1];
    }
}
