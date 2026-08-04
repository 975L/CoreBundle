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
 * A row of cards holds images of unequal heights and texts of unequal lengths, and its buttons still have
 * to sit on one line. That takes a four-rule chain, each link useless without the others: ".cards"
 * stretches the cards to a common height, ".card" is the column that hands that height down, ".card-body"
 * takes what the header leaves, and ".card-data" - the tail the "card"/"collection" adapters wrap the text
 * and the button in - is pinned to its bottom. Locked here because breaking any one of them costs nothing
 * visible on a row of identical cards, and misaligns every real one.
 */
class CardTailAlignmentTest extends TestCase
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
    public function testTheRowStretchesItsCardsToACommonHeight(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.cards\{[^}]*align-items:stretch/',
            $this->normalize($file),
            sprintf('"%s" no longer stretches the cards of a row, so each is only as tall as its own content and no button can line up with another.', $file)
        );
    }

    // The card is the column, the body the part of it that grows: without both, the body ends at its
    // content and its bottom is not the card's
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheCardHandsItsHeightDownToItsBody(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/(?<![\w>-])\.card\{[^}]*flex-direction:column/',
            $css,
            sprintf('"%s" no longer lays a card out as a column, so its body cannot take the height the row gave it.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.card-body\{[^}]*flex:1/',
            $css,
            sprintf('"%s" no longer grows the card body, so it stops at its content instead of reaching the bottom of the card.', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheTailIsPinnedToTheBottomOfTheBody(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.card-body:has\(>\.card-data\)\{[^}]*flex-direction:column/',
            $css,
            sprintf('"%s" no longer lays out a body holding a tail as a column, where "margin-top:auto" is what pins that tail down.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.card-data\{[^}]*margin-top:auto/',
            $css,
            sprintf('"%s" no longer pins the card tail to the bottom of the body, so the buttons of a row follow their own content.', $file)
        );
    }

    // The 1em separating the tail from the image above it: as a margin it would be the pinning itself,
    // and would collapse to nothing on a card tall enough to have no free space left
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheTailKeepsItsSpacingAsPadding(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.card-data\{[^}]*padding-top:1em/',
            $this->normalize($file),
            sprintf('"%s" writes the card tail spacing as something other than padding, which "margin-top:auto" then overrides.', $file)
        );
    }

    // Normalized so the same assertions hold whatever the compiler wrapped
    private function normalize(string $file): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($this->path('public/css/' . $file)));

        return (string) preg_replace('/\s+/', '', $css);
    }

    private function path(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $relativePath));

        return $path;
    }
}
