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

// The three rules setting the page measure must read the same token chain, else they misalign
class SectionWrapMeasureTest extends TestCase
{
    private const MEASURE = 'max-width: var(--section-wrap-max-width, var(--body-max-width, 1440px))';

    // Selectors as they read once normalized: no space around the combinators, one around a descendant
    private const MEASURED_RULES = [
        '.section-wrap',
        '.blocks>.cards',
        '.feature-bar.section--bg-dark .feature-bar__grid',
    ];

    private const GUTTERED_RULES = ['.section-wrap', '.blocks>.cards'];

    private const GUTTERS = [
        'padding-left: var(--section-wrap-gutter, clamp(20px, 5vw, 64px))',
        'padding-right: var(--section-wrap-gutter, clamp(20px, 5vw, 64px))',
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
    public function testEveryMeasuredRuleReadsTheSameChain(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::MEASURED_RULES as $rule) {
            $this->assertStringContainsString(
                self::MEASURE,
                $this->declarationsOf($css, $rule, $file),
                sprintf('"%s" does not read the measure chain in "%s".', $rule, $file)
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheWrappingRulesReadTheGutterToken(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::GUTTERED_RULES as $rule) {
            $declarations = $this->declarationsOf($css, $rule, $file);

            foreach (self::GUTTERS as $gutter) {
                $this->assertStringContainsString(
                    $gutter,
                    $declarations,
                    sprintf('"%s" does not read "%s" in "%s".', $rule, $gutter, $file)
                );
            }
        }
    }

    // Both wrapping rules pad themselves, and SiteBundle's box-sizing reset is absent on its own
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheWrappingRulesDeclareBorderBox(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::GUTTERED_RULES as $rule) {
            $this->assertStringContainsString(
                'box-sizing: border-box',
                $this->declarationsOf($css, $rule, $file),
                sprintf('"%s" pads itself without declaring "box-sizing: border-box" in "%s".', $rule, $file)
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
