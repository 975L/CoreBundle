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

// Ink written on a ground that is dark in both color modes - a hero's background media under its fixed black voile, and the "primary"/"dark" section flats - is a stated white and never var(--white): dark mode swaps that token with --black (SiteBundle's sass/_theme-dark.scss), which turns every one of those grounds into near-black text on a dark band. A design wanting another ink restates --section-text from its own theme file.
class DarkGroundInkTest extends TestCase
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
    public function testTheHeroOnABackgroundMediaWritesAStatedWhite(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.hero\.hero--has-bg\{[^}]*--section-text:#fff;--section-text-soft:#fff/',
            $this->normalize($file),
            sprintf('"%s" has the hero title read a swappable token, which dark mode turns near-black on the voile.', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheDarkFlatsWriteAStatedWhite(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.section--bg-primary,\.section--bg-dark\{--section-text:#fff;/',
            $this->normalize($file),
            sprintf('"%s" has the "primary"/"dark" bands read a swappable token, which dark mode turns near-black on them.', $file)
        );
    }

    // The label of that button is a --section-btn-color picked to read on white, so an inverted background hides it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePrimaryCtaOnADarkFlatKeepsItsWhiteBackground(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.section--bg-primary\.section-btn--primary,\.section--bg-dark\.section-btn--primary\{background:#fff/',
            $this->normalize($file),
            sprintf('"%s" paints that button with a swappable token, which dark mode turns near-black under its own label.', $file)
        );
    }

    // A card's header band and the paginator's current chip are painted with a hue in both modes too - the band's own ink is refined per hue through --card-accent-color, which CardAccentTest locks
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheColoredChipsWriteAStatedWhite(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.card-header,h2\.card-header\{[^}]*color:var\(--card-accent-color,#fff\)/',
            $css,
            sprintf('"%s" has a card header read a swappable token, which dark mode turns near-black on its own hue.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.current\{background-color:var\(--primary\);color:#fff/',
            $css,
            sprintf('"%s" has the current page number read a swappable token, which dark mode turns near-black on the chip.', $file)
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
