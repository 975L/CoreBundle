<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Form\BlockRadiusChoiceType;
use c975L\UiBundle\Form\BlockShadowChoiceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The corners and the shadow an editor picks per block. Each step only points --block-radius / --block-shadow at its own token and paints nothing itself, the two kinds reading those through a default of their own - so a step that never reaches a rule is a stored value shaping nothing, and a kind that stopped reading the token is a field the back-office offers and the page ignores.
class BlockSurfaceTest extends TestCase
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

    // Every step the picker offers has to reach a rule, or a stored value rounds nothing at all
    #[DataProvider('stylesheetProvider')]
    public function testEveryRadiusStepPointsTheTokenAtItsOwnValue(string $file): void
    {
        $css = $this->normalize($file);

        foreach (BlockRadiusChoiceType::CHOICES as $step) {
            $expected = 'none' === $step ? '0' : sprintf('var\(--block-radius-%s\)', $step);

            $this->assertMatchesRegularExpression(
                sprintf('/\.block-radius-%s\{--block-radius:%s[;}]/', $step, $expected),
                $css,
                sprintf('"%s" leaves the "%s" corner step without a rule setting --block-radius.', $file, $step)
            );
        }
    }

    #[DataProvider('stylesheetProvider')]
    public function testEveryShadowStepPointsTheTokenAtItsOwnValue(string $file): void
    {
        $css = $this->normalize($file);

        foreach (BlockShadowChoiceType::CHOICES as $step) {
            $expected = 'none' === $step ? 'none' : sprintf('var\(--block-shadow-%s\)', $step);

            $this->assertMatchesRegularExpression(
                sprintf('/\.block-shadow-%s\{--block-shadow:%s[;}]/', $step, $expected),
                $css,
                sprintf('"%s" leaves the "%s" shadow step without a rule setting --block-shadow.', $file, $step)
            );
        }
    }

    // Read through the kind's own default, never bare: a block left on "theme" writes no class at all, so the token is unset on it and the fallback is the whole of what it renders as
    #[DataProvider('stylesheetProvider')]
    public function testBothKindsReadTheRadiusThroughTheirOwnDefault(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.card\{[^}]*border-radius:var\(--block-radius,var\(--radius-card\)\)/',
            $css,
            sprintf('"%s" no longer has the card read --block-radius, so the "rounded corners" field shapes nothing on it.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.flip-card-face\{[^}]*border-radius:var\(--block-radius,var\(--flip-card-radius\)\)/',
            $css,
            sprintf('"%s" no longer has a flip card face read --block-radius, so the "rounded corners" field shapes nothing on it.', $file)
        );
    }

    // The band curves one pixel inside the card's own corner, whichever step was picked: matching the outer value rounds it more than the corner containing it, and the card's background shows through as a white sliver
    #[DataProvider('stylesheetProvider')]
    public function testTheHeaderBandFollowsTheCardsOwnCorner(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.card-header,h2\.card-header\{[^}]*border-radius:calc\(var\(--block-radius,var\(--radius-card\)\) - 1px\) calc\(var\(--block-radius,var\(--radius-card\)\) - 1px\) 0 0/',
            $this->normalize($file),
            sprintf('"%s" curves the header band on a value of its own, which shows the card\'s background through the top corners.', $file)
        );
    }

    // A card is a flat panel until a step is picked, hence no box-shadow on the bare ".card" - and the step is stated on two classes so it beats SiteBundle's ".box-shadow" utility whatever order the two sheets are concatenated in
    #[DataProvider('stylesheetProvider')]
    public function testACardCarriesNoShadowUntilAStepIsPicked(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertDoesNotMatchRegularExpression(
            '/\.card\{[^}]*box-shadow:/',
            $css,
            sprintf('"%s" writes a shadow on every card, which a card stored before the field existed never had.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.card:is\(\.block-shadow-none,\.block-shadow-small,\.block-shadow-medium,\.block-shadow-large\)\{box-shadow:var\(--block-shadow,none\)[;}]/',
            $css,
            sprintf('"%s" no longer states the card\'s shadow on two classes, so ".box-shadow" wins or loses it on source order.', $file)
        );
    }

    // A flip card has always been shadowed, so its "theme" is --box-shadow rather than "none"
    #[DataProvider('stylesheetProvider')]
    public function testAFlipCardKeepsItsOwnShadowAsTheThemeDefault(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.flip-card-face\{[^}]*box-shadow:var\(--block-shadow,var\(--box-shadow\)\)/',
            $this->normalize($file),
            sprintf('"%s" no longer has a flip card face fall back on --box-shadow, so a card stored before the field existed lost its lift.', $file)
        );
    }

    // Normalized so the same assertions hold whatever the compiler wrapped. Collapsed rather than stripped: the spaces around the minus of a "calc()" are part of the expression, so the declarations asserted below have to be read with their own spacing intact
    private function normalize(string $file): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($this->path('public/css/' . $file)));
        $css = (string) preg_replace('/\s+/', ' ', $css);

        return (string) preg_replace('/ *([{};:,>]) */', '$1', $css);
    }

    private function path(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $relativePath));

        return $path;
    }
}
