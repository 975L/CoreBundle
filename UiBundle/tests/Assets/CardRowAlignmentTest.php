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

// A ".cards" row lines its cards up on one top edge and one height. Two rules are what that rests on, and both broke silently: a card is only *reached* through the "display: contents" wrappers a block is rendered inside, and a flip card is only *painted* on the box the row stretched if its inner fills that box.
class CardRowAlignmentTest extends TestCase
{
    // "display: contents" - neither ever becomes the flex item, but both stay nodes in the DOM, which a child combinator reads. Two sheets: the entrance wrapper is styled with the keyframes it exists for, the editor's overlay with the rest of the page
    private const TRANSPARENT_WRAPPERS = [
        'block-animation' => ['animations.css', 'animations.min.css'],
        'block-editable' => ['styles.css', 'styles.min.css'],
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

    // Both kinds drop their own margin inside a row, the row spacing its cards with "gap". Written as a descendant so it still reaches a card wrapped for its entrance animation or for the editor's overlay: with "> " it missed exactly those, and an animated card sat a full margin below the row it belongs to
    #[DataProvider('stylesheetProvider')]
    public function testTheRowResetsAMarginThroughTheTransparentWrappers(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression('/\.cards \.card\{margin:0[;}]/', $css, sprintf('"%s" no longer resets a card\'s margin inside a row.', $file));
        $this->assertMatchesRegularExpression('/\.cards \.flip-card\{margin-block:0[;}]/', $css, sprintf('"%s" no longer resets a flip card\'s margin inside a row.', $file));

        $this->assertStringNotContainsString('.cards>.card{', $css, sprintf('"%s" resets a card\'s margin with a child combinator, which misses every card carrying an animation.', $file));
        $this->assertStringNotContainsString('.cards>.flip-card{', $css, sprintf('"%s" resets a flip card\'s margin with a child combinator, which misses every card carrying an animation.', $file));
    }

    // The wrappers themselves generate no box, so the card stays the flex item the row stretches - the reset above is only needed because they stay in the DOM the selector is read against
    public function testTheWrappersGenerateNoBoxOfTheirOwn(): void
    {
        foreach (self::TRANSPARENT_WRAPPERS as $wrapper => $files) {
            foreach ($files as $file) {
                $this->assertMatchesRegularExpression(
                    sprintf('/\.%s\{[^}]*display:contents/', $wrapper),
                    $this->normalize($file),
                    sprintf('"%s" gives ".%s" a box of its own, so it becomes the flex item and the card inside it stops being stretched by the row.', $file, $wrapper)
                );
            }
        }
    }

    // A grid, so the inner - and the two faces stretched inside it - fills the height the row gave the card. Without it a row is as tall as its longest card while every shorter one paints a box of its own height: one card whose title wraps to a second line and its neighbours read as three different shapes
    #[DataProvider('stylesheetProvider')]
    public function testAFlipCardIsPaintedOnTheHeightTheRowGaveIt(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.flip-card\{[^}]*display:grid/',
            $this->normalize($file),
            sprintf('"%s" no longer stretches a flip card\'s inner to the card\'s own box, so a row of cards paints as many heights as it holds contents.', $file)
        );
    }

    // Normalized so the same assertions hold whatever the compiler wrapped. Collapsed rather than stripped: a space between two classes is the descendant combinator this whole file is about, and dropping it turns ".cards .card" into a compound matching neither
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
