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

// The row of icons a visitor votes with: sized off the type size where a mouse points at it, drawn as a touch target where a finger does
class RatingTouchTargetTest extends TestCase
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

    // A coarse pointer gets the row redrawn at a size a finger can aim at, the default 1.1em leaving a 14px icon on a card's compact widget
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheVoteRowIsDrawnAsATouchTargetWhereThePointerIsCoarse(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/@media\(pointer:coarse\)\{[^@]*\.rating-vote\{--rating-size:2\.2rem/',
            $this->normalize($file),
            sprintf('"%s" no longer enlarges the vote row for a coarse pointer, leaving icons no finger can aim at.', $file)
        );
    }

    // Stated in rem and not in em: .rating-vote--compact prints at 0.8rem, and a size following that font-size would shrink the target back on the very cards it is meant for
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheTouchTargetDoesNotFollowTheWidgetsOwnFontSize(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/@media\(pointer:coarse\)\{[^@]*\.rating-vote\{--rating-size:[0-9.]+rem/',
            $css,
            sprintf('"%s" sizes the coarse-pointer target in em, which a compact card shrinks back below a finger.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.rating-vote--compact\{[^}]*font-size:0?\.8rem/',
            $css,
            sprintf('"%s" no longer prints the compact widget smaller, which is what the rem above guards against.', $file)
        );
    }

    // Icons grown that far need room between them, or two of them read as one target
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheEnlargedIconsAreSpacedApart(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/@media\(pointer:coarse\)\{[^@]*\.rating-vote\.rating\{gap:0?\.3em/',
            $this->normalize($file),
            sprintf('"%s" leaves the enlarged icons at the row default gap.', $file)
        );
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
