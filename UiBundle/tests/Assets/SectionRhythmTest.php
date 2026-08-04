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

/*
 * The page's vertical rhythm: one step, declared on the top edge of every section-level block and nowhere
 * else, so any two blocks are parted by exactly one. Every defect this locks was live at once on 975l.com -
 * a kind left out of the rhythm sat flush against the one above it, and the two kinds padding their bottom
 * too parted their pair by two steps.
 */
class SectionRhythmTest extends TestCase
{
    // Read through the token, never written out: a rule carrying the value itself is one a theme cannot retune
    private const STEP = 'var(--section-space,clamp(48px,8vw,84px))';

    private const STEP_TIGHT = 'var(--section-space-tight,clamp(24px,4vw,48px))';

    // Its step is a margin, the band painting its own background between its own hairlines
    private const SPACED_BY_MARGIN = ['.feature-bar'];

    // Laid out by their own container rather than by the page, and deliberately outside the rhythm
    private const OUTSIDE_THE_RHYTHM = ['.hero'];

    /**
     * Every kind the margin reset names is a page-level block, and every page-level block owns a step.
     * A kind added to the reset and left out of the rhythm is the bug ".flex-columns-section" carried.
     */
    public function testEveryPageLevelKindOwnsAStep(): void
    {
        $css = $this->normalize('styles.min.css');

        foreach ($this->kindsNamedByTheReset($css) as $kind) {
            if (\in_array($kind, self::OUTSIDE_THE_RHYTHM, true)) {
                continue;
            }

            $property = \in_array($kind, self::SPACED_BY_MARGIN, true) ? 'margin-block-start' : 'padding-top';

            $this->assertMatchesRegularExpression(
                $this->declarationPattern($kind, $property, [self::STEP, self::STEP_TIGHT]),
                $css,
                sprintf('"%s" is a page-level block but declares no %s of its own: it sits flush against the block above it.', $kind, $property)
            );
        }
    }

    /*
     * The step is declared on the top edge only. A bottom one is added to the next block's top one and parts
     * that pair by two - which is what .hero and .cta-band did until the rhythm was harmonized. The exception
     * is a flat: it paints down to its own edge, and its content would otherwise sit right on that edge.
     */
    public function testOnlyAFlatPadsItsBottomEdge(): void
    {
        $css = $this->normalize('styles.min.css');
        $offenders = [];

        foreach ($this->kindsNamedByTheReset($css) as $kind) {
            preg_match_all('/([^{}]*' . preg_quote($kind, '/') . '[^{}]*)\{([^}]*padding-bottom[^}]*)\}/', $css, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                // A flat says so in the selector itself, through .section--bg-* or the hero's own --has-bg
                if (preg_match('/section--bg-|--has-bg/', $match[1])) {
                    continue;
                }

                // The subject has to be the kind itself, not something nested under it carrying its own padding
                if (!preg_match('/' . preg_quote($kind, '/') . '[a-z0-9_-]*(\)|,|\{|:|\.)?$/', $this->subject($match[1]))) {
                    continue;
                }

                $offenders[] = $match[1];
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "These page-level rules pad their bottom edge without painting a flat, parting their pair by two steps:\n- %s",
            implode("\n- ", $offenders)
        ));
    }

    /*
     * The row of cards Blocks.html.twig synthesizes around consecutive "card" blocks is a page-level block with
     * no kind of its own for the reset to name, and its cards drop their margin inside it - so the rhythm reaches
     * it through this rule alone, without which it sits flush against the block above.
     */
    public function testTheBareRowOfCardsOwnsAStep(): void
    {
        $this->assertMatchesRegularExpression(
            $this->declarationPattern('.blocks>.cards', 'padding-top', [self::STEP]),
            $this->normalize('styles.min.css'),
            'A bare run of "card" blocks declares no step of its own: it sits flush against the block above it.'
        );
    }

    // A value written out is a value no theme can reach: the rhythm has to stay retunable from one token
    public function testTheStepIsNeverWrittenOutBesideTheToken(): void
    {
        $sass = (string) file_get_contents($this->path('sass/_page-sections.scss'));
        $sass = (string) preg_replace('#//[^\n]*#', '', $sass);

        // The SCSS variables the token's own fallback is interpolated from are where the value belongs
        $sass = (string) preg_replace('/^\$section-space[a-z-]*:[^;]+;/m', '', $sass);

        $this->assertDoesNotMatchRegularExpression(
            '/clamp\(\s*48px\s*,\s*8vw\s*,\s*84px\s*\)|clamp\(\s*24px\s*,\s*4vw\s*,\s*48px\s*\)/',
            $sass,
            'sass/_page-sections.scss writes the step out instead of reading it through --section-space: a site retuning the token would leave that rule behind.'
        );
    }

    /**
     * The kinds the reset names, which is the list of everything laid out as a page-level block.
     *
     * @return list<string>
     */
    private function kindsNamedByTheReset(string $css): array
    {
        preg_match('/main:is\(\.blocks,\.block-animation,\.block-editable\)>:is\(([^)]*)\)/', $css, $matches);
        $this->assertNotEmpty($matches, 'The compiled stylesheet no longer holds the section margin reset, which this test reads the kinds off.');

        return [...explode(',', $matches[1]), ...self::SPACED_BY_MARGIN];
    }

    // Whether a kind declares the property, at the step or at the tight step
    private function declarationPattern(string $kind, string $property, array $values): string
    {
        $alternatives = implode('|', array_map(static fn (string $value): string => preg_quote($value, '/'), $values));

        return '/(^|[,{}])[^{}]*' . preg_quote($kind, '/') . '[^{}]*\{[^}]*' . preg_quote($property, '/') . ':(' . $alternatives . ')/';
    }

    // The last compound of the last selector in a list, i.e. what the rule actually applies to
    private function subject(string $selectors): string
    {
        $last = (string) strrchr(',' . $selectors, ',');

        return trim((string) preg_replace('/^.*[ >+~]/', '', trim($last, ', ')));
    }

    // Normalized so the same assertions hold whatever the compiler wrapped
    private function normalize(string $file): string
    {
        $path = $this->path('public/css/' . $file);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }

    private function path(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $relativePath));

        return $path;
    }
}
