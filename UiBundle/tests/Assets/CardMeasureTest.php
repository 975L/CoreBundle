<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Three cards fit a row, six compact ones do and two big ones do, whatever measure a site frames its pages on - the invariant the three width tokens exist to hold. It rests on numbers that have to keep agreeing: the gaps taken off the measure and the ones ".cards" actually puts between the cards, the divisor and the count, and the cap each token stops at against the room the default page leaves
class CardMeasureTest extends TestCase
{
    // The two chains as they read once every space is out
    private const string MEASURE_CHAIN = 'var(--section-wrap-max-width,var(--body-max-width,1440px))';

    private const string GUTTER_CHAIN = 'var(--section-wrap-gutter,clamp(20px,5vw,64px))';

    // The page the three caps below were computed against, and the gutter at its own ceiling
    private const int DEFAULT_MEASURE = 1440;

    private const int DEFAULT_GUTTER = 64;

    // Each token, the cap it stops at and how many of that card a row holds - 380 * 3, 190 * 6 and 570 * 2 are the same 1140px of cards, which is what makes the three one series
    private const array WIDTHS = [
        '--card-width' => [380, 3],
        '--card-width-compact' => [190, 6],
        '--card-width-big' => [570, 2],
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

    // Written down, a width is a number computed against the default measure: a site framing its content tighter dropped a card off the row with nothing to say so
    #[DataProvider('stylesheetProvider')]
    public function testEveryWidthIsReadOffThePageMeasure(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (array_keys(self::WIDTHS) as $token) {
            $value = $this->valueOf($css, $token, $file);

            $this->assertStringContainsString(self::MEASURE_CHAIN, $value, sprintf('"%s" no longer reads the page measure in "%s", so it holds its row on the default page only.', $token, $file));
            $this->assertStringContainsString(self::GUTTER_CHAIN, $value, sprintf('"%s" no longer takes the wrap gutters off the measure in "%s".', $token, $file));
        }
    }

    // A floor is what made a card overflow its own wrap under a 420px viewport, where 90vw is already less than it - so all three are capped and none is floored
    #[DataProvider('stylesheetProvider')]
    public function testNoWidthCarriesAFloor(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::WIDTHS as $token => [$cap]) {
            $value = $this->valueOf($css, $token, $file);

            $this->assertStringStartsWith('min(', $value, sprintf('"%s" is not a plain cap in "%s": a floor wins over the viewport ceiling on a narrow phone, and the card overflows its wrap.', $token, $file));
            $this->assertStringContainsString($cap . 'px', $value, sprintf('"%s" no longer stops at its %dpx cap in "%s".', $token, $cap, $file));
        }
    }

    // The gaps taken off the measure are the row's own, one less than the cards they space. Retune ".cards"'s gap alone and the row silently holds one card less
    #[DataProvider('stylesheetProvider')]
    public function testTheGapsTakenOffTheMeasureAreTheOnesTheRowPuts(string $file): void
    {
        $css = $this->stylesheet($file);
        $gap = $this->rowGap($css, $file);

        foreach (self::WIDTHS as $token => [, $perRow]) {
            [$gaps, $divisor] = $this->arithmeticOf($css, $token, $file);

            $this->assertSame($perRow, $divisor, sprintf('"%s" divides the measure by %d in "%s", where a row is meant to hold %d.', $token, $divisor, $file, $perRow));
            $this->assertSame($gap * ($perRow - 1), $gaps, sprintf('"%s" takes %dpx of gaps off the measure in "%s", where %d cards spaced by ".cards"\'s own %dpx gap leave %dpx.', $token, $gaps, $file, $perRow, $gap, $gap * ($perRow - 1)));
        }
    }

    // Nothing moves on the default page: it leaves more room than the cap, so each token still resolves to the very value it used to be written as
    #[DataProvider('stylesheetProvider')]
    public function testTheDefaultPageStillResolvesToTheWrittenCap(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::WIDTHS as $token => [$cap]) {
            [$gaps, $divisor] = $this->arithmeticOf($css, $token, $file);
            $room = (self::DEFAULT_MEASURE - 2 * self::DEFAULT_GUTTER - $gaps) / $divisor;

            $this->assertGreaterThanOrEqual($cap, $room, sprintf('"%s" resolves to %.1fpx on the default page in "%s", under its own %dpx cap: a default site no longer gets the width it had.', $token, $room, $file, $cap));
        }
    }

    // What is read is the measure a page declares, which says nothing about the box a given row sits in - a ".cards" nested in a "flex_columns" cell, in a panel or in a modal is narrower than that
    #[DataProvider('stylesheetProvider')]
    public function testACardNeverExceedsTheBoxItsRowSitsIn(string $file): void
    {
        foreach (['.card', '.flip-card'] as $selector) {
            $declarations = $this->measuredDeclarations($this->stylesheet($file), $selector, $file);

            $this->assertStringContainsString('max-width:100%', $declarations, sprintf('"%s" no longer caps itself on its own box in "%s", so a row nested in a slot narrower than the page measure pushes past it.', $selector, $file));
        }
    }

    // The cap above is read but not applied on a flip card unless both its boxes can go under the min-content of what they hold: an "auto" track never does, and a card nested in a face carries "width: var(--card-width)" - a length, which counts in full towards that min-content. The track then held the asked-for width whatever box it sat in, and a phone scrolled sideways on every page carrying a flip card
    #[DataProvider('stylesheetProvider')]
    public function testBothBoxesOfAFlipCardFollowThatCapDown(string $file): void
    {
        foreach (['.flip-card', '.flip-card-inner'] as $selector) {
            $declarations = $this->declarationsOf($this->stylesheet($file), $selector, $file);

            $this->assertStringContainsString('grid-template-columns:minmax(0,1fr)', $declarations, sprintf('"%s" lays its grid out on a track that cannot shrink under its content in "%s", so the cap on the card is never applied and a narrow screen scrolls sideways.', $selector, $file));
        }
    }

    // Comments out and every space with them, so the same assertions hold on both sheets - nothing here reads a descendant combinator
    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }

    // The value a token is declared with, the trailing ";" left out
    private function valueOf(string $css, string $token, string $file): string
    {
        if (1 !== preg_match(sprintf('/%s:([^;]+);/', preg_quote($token, '/')), $css, $matches)) {
            $this->fail(sprintf('"%s" declares no "%s" at all.', $file, $token));
        }

        return $matches[1];
    }

    /**
     * The two numbers the token computes with: the gaps it takes off the measure, and the count it divides by.
     *
     * @return array{int, int}
     */
    private function arithmeticOf(string $css, string $token, string $file): array
    {
        $value = $this->valueOf($css, $token, $file);

        if (1 !== preg_match('/-(\d+)px\)\/(\d+)\)/', $value, $matches)) {
            $this->fail(sprintf('"%s" no longer divides the measure in "%s": %s', $token, $file, $value));
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    // The gap the row itself declares, which is what the tokens have to take off the measure - written on the row or read by it off "--cards-gap", the two being the same number and the token being what a site retunes
    private function rowGap(string $css, string $file): int
    {
        if (1 !== preg_match('/(?:^|[};])\.cards\{([^}]*)\}/', $css, $rule)) {
            $this->fail(sprintf('"%s" has no ".cards" rule spacing its cards with a "gap".', $file));
        }

        if (1 === preg_match('/gap:(\d+)px/', $rule[1], $matches)) {
            return (int) $matches[1];
        }

        if (1 === preg_match('/gap:\s*var\(--cards-gap/', $rule[1]) && 1 === preg_match('/--cards-gap:\s*(\d+)px/', $css, $matches)) {
            return (int) $matches[1];
        }

        $this->fail(sprintf('"%s" has no ".cards" rule spacing its cards with a "gap".', $file));
    }

    // The body of a kind's own rule, told apart from the ones a row or a cell restates by the width it reads. Both kinds line up in the same ".cards" row, so both read that one token: a width written down again is a number computed against the default page, which is what dropped a flip card off a row every site framing its content tighter
    private function measuredDeclarations(string $css, string $selector, string $file): string
    {
        $declarations = $this->declarationsOf($css, $selector, $file);

        $this->assertStringContainsString('width:var(--card-width)', $declarations, sprintf('"%s" no longer measures "%s" off the shared token.', $file, $selector));

        return $declarations;
    }

    // The rule's own block, read off the selector alone: a flip card's inner measures nothing of its own and carries no width for the assertion above to find
    private function declarationsOf(string $css, string $selector, string $file): string
    {
        if (1 !== preg_match(sprintf('/(?:^|[};])%s\{([^}]*)\}/', preg_quote($selector, '/')), $css, $matches)) {
            $this->fail(sprintf('"%s" has no "%s" rule at all.', $file, $selector));
        }

        return $matches[1];
    }
}
