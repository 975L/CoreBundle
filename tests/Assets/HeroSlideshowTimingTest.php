<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Form\BlockType;
use PHPUnit\Framework\TestCase;

// The crossfade's keyframes cannot read the slide count, so every turn is a hand-written rule: raising
// the form's cap without writing them leaves the extra images colliding with an earlier slide's timing
class HeroSlideshowTimingTest extends TestCase
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

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEverySlideTheFormAcceptsHasItsOwnDelay(string $file): void
    {
        $css = $this->normalize($file);

        foreach (range(1, $this->heroMediaMax()) as $slide) {
            $this->assertStringContainsString(
                sprintf('.hero__media--slideshowimg:nth-child(%d)', $slide),
                $css,
                sprintf('"%s" holds no delay for slide %d, which BlockType::HERO_MEDIA_MAX lets an editor upload.', $file, $slide)
            );
        }
    }

    // The duration is what spreads the turns over the whole cycle, hence one rule per possible count
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEverySlideCountTheFormAcceptsHasItsOwnDuration(string $file): void
    {
        $css = $this->normalize($file);

        // From 2: the component only builds a slideshow once a second media is attached
        foreach (range(2, $this->heroMediaMax()) as $count) {
            $this->assertStringContainsString(
                sprintf('.hero__media--slideshow[data-count="%d"]', $count),
                $css,
                sprintf('"%s" holds no duration for %d slides, so that count falls back to the last one declared.', $file, $count)
            );
        }
    }

    // The other direction: a lowered cap leaves rules for slides no editor can upload any more
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoTimingIsDeclaredPastTheCap(string $file): void
    {
        $css = $this->normalize($file);
        $past = $this->heroMediaMax() + 1;

        $this->assertStringNotContainsString(sprintf('.hero__media--slideshowimg:nth-child(%d)', $past), $css);
        $this->assertStringNotContainsString(sprintf('.hero__media--slideshow[data-count="%d"]', $past), $css);
        $this->assertStringNotContainsString(sprintf('@keyframeshero-slide-fade-%d', $past), $css);
    }

    // The table above only says when a turn starts: a slide held opaque longer than its own share of the
    // cycle stays stacked over the next ones, and the last one in the DOM then wins for good
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoSlideStaysOpaquePastItsOwnShareOfTheCycle(string $file): void
    {
        $css = $this->normalize($file);

        foreach (range(2, $this->heroMediaMax()) as $count) {
            $opaque = $this->opaqueOffsets($css, $count);
            $share = 100 / $count;
            $window = max($opaque) - min($opaque);

            $this->assertLessThanOrEqual(
                $share,
                $window,
                sprintf('"%s" holds a slide opaque for %.2f%% of the cycle where %d slides leave it %.2f%%, so the turns overlap.', $file, $window, $count, $share)
            );
            $this->assertGreaterThan(
                $share / 2,
                $window,
                sprintf('"%s" shows a slide for %.2f%% of the cycle, less than half the %.2f%% its turn lasts.', $file, $window, $share)
            );
        }
    }

    /**
     * The offsets at which one slide is fully opaque, read from its own keyframes.
     *
     * @return array<int, float>
     */
    private function opaqueOffsets(string $css, int $count): array
    {
        $matched = preg_match(sprintf('/@keyframeshero-slide-fade-%d\{(.+?)\}\}/', $count), $css, $keyframes);
        $this->assertSame(1, $matched, sprintf('No keyframes are declared for %d slides, so that count animates nothing.', $count));

        preg_match_all('/([\d.]+)%\{([^}]*)\}/', $keyframes[1] . '}', $stops, PREG_SET_ORDER);
        $offsets = [];
        foreach ($stops as $stop) {
            if (str_contains($stop[2], 'opacity:1')) {
                $offsets[] = (float) $stop[1];
            }
        }

        $this->assertNotEmpty($offsets, sprintf('The keyframes for %d slides never reach opacity 1, so nothing is ever shown.', $count));

        return $offsets;
    }

    private function heroMediaMax(): int
    {
        // Private: it is an implementation detail of the form, this test being the only other reader
        $constant = (new \ReflectionClass(BlockType::class))->getReflectionConstant('HERO_MEDIA_MAX');
        $this->assertNotFalse($constant, 'BlockType no longer holds HERO_MEDIA_MAX, this test no longer checks anything.');

        return (int) $constant->getValue();
    }

    // Whitespace squeezed out, so expanded and minified are matched against the very same needle
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        return (string) preg_replace('/\s+/', '', (string) file_get_contents($path));
    }
}
