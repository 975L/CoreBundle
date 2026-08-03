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
 * A card header paints its text on the band's own color, and its icon is an <img>: it paints the SVG file's
 * own fill instead of inheriting that color, so a black icon on a colored band. Whitened by the stylesheet
 * rather than by a "white" class on the markup, so every card gets it whichever template built the icon.
 * The inversion is an amount, not a switch: the four light hues writing dark text set it to 0 (CardAccentTest).
 */
class CardHeaderIconTest extends TestCase
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
    public function testTheIconIsWhitenedByTheStylesheet(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.card-header\.icon\{[^}]*filter:brightness\(0\)invert\(var\(--card-accent-invert,1\)\)/',
            $this->normalize($file),
            sprintf('"%s" no longer flattens then inverts ".card-header .icon", so the icon paints its own fill on the header\'s color.', $file)
        );
    }

    // The rule has to reach the icon the component renders, which never carries a whitening class of its own
    public function testTheComponentRendersTheIconWithoutAWhiteClass(): void
    {
        $template = (string) file_get_contents($this->path('templates/components/Card/Card.html.twig'));

        $this->assertStringContainsString('twig:c975LUi:Image:Icon', $template, 'The card header no longer builds its icon through the Icon component, which the stylesheet rule is written for.');
        $this->assertStringNotContainsString('class="white"', $template, 'The card icon is whitened by the stylesheet, not by a class on the markup.');
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
