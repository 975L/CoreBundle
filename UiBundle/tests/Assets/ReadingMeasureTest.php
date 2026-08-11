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

// A slider and a comparison sit in the same column as the body copy, so they read the measure SiteBundle declares for it - through a fallback, this bundle standing on its own without SiteBundle
class ReadingMeasureTest extends TestCase
{
    private const string MEASURE = 'max-width: var(--reading-max-width, min(75ch, 90vw))';

    // ".legal" carries it for the whole document, headings included: "ch" is read against the font of the element the max-width is written on, so a heading stating its own would read a measure its body copy never does
    private const array MEASURED_RULES = ['.slider', '.slider-single', '.image-compare', '.readmore', '.legal'];

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
    public function testEveryColumnWideRuleReadsTheSameMeasure(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::MEASURED_RULES as $rule) {
            $this->assertStringContainsString(
                self::MEASURE,
                $this->declarationsOf($css, $rule, $file),
                sprintf('"%s" does not read the reading measure in "%s", so it misaligns with the text beside it.', $rule, $file)
            );
        }
    }

    // Every measure this bundle sets is a token: a site retunes its shape from :root, never by overriding a rule
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheSectionAndHeroMeasuresAreTokenisedToo(string $file): void
    {
        $css = $this->normalize($file);

        foreach (['--section-head-max-width', '--section-head-indent', '--hero-media-max-width', '--hero-text-max-width', '--cta-band-text-max-width', '--alert-max-width'] as $token) {
            $this->assertStringContainsString(
                sprintf('var(%s,', $token),
                $css,
                sprintf('"%s" no longer reads "%s", a measure gone back to a bare length.', $file, $token)
            );
        }
    }

    // The same three blocks opt out of the section margin reset by setting their own vertical room, so that room has to be symmetric: the former "1em auto 3em" left a slider all but flush against the block introducing it. The "auto" is what centers them on the measure above, so the shorthand keeps carrying it.
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryColumnWideRuleKeepsSymmetricRoomAroundIt(string $file): void
    {
        $css = $this->normalize($file);

        foreach (['.slider' => '--slider-margin-block', '.slider-single' => '--slider-margin-block', '.image-compare' => '--image-compare-margin-block'] as $rule => $token) {
            $this->assertStringContainsString(
                sprintf('margin: var(%s, 3em) auto', $token),
                $this->declarationsOf($css, $rule, $file),
                sprintf('"%s" no longer keeps the same room above and below it in "%s".', $rule, $file)
            );
        }
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);

        return (string) preg_replace('/\s+/', ' ', $css);
    }

    // The body of the rule holding $selector, spaced back out the way the sass writes it
    private function declarationsOf(string $css, string $selector, string $file): string
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!in_array($selector, explode(',', trim($match[1])), true)) {
                continue;
            }

            return str_replace([':', ','], [': ', ', '], $match[2]);
        }

        $this->fail(sprintf('"%s" has no "%s" rule at all.', $file, $selector));
    }
}
