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
 * A hero laying its medias out as a grid shows marks - logos, icons - and a mark is drawn for one
 * background: laid straight on the section's flat, its own colors mix into whatever tone the hero carries.
 * So the tile paints its own opaque plate, and carries no :hover, these tiles not being links.
 */
class HeroMediaGridTileTest extends TestCase
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
    public function testATilePaintsAnOpaquePlate(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.hero__media--gridimg\{[^}]*background:var\(--hero-media-grid-background,#fff\)/',
            $this->normalize($file),
            sprintf('"%s" leaves the grid tiles transparent, so a mark\'s own colors mix into the hero background.', $file)
        );
    }

    // The one measure of this grid a site routinely has to redo, a logo carrying its own margin needing far less of it than a bare glyph
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheInsetOfAMarkIsATokenOfItsOwn(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.hero__media--gridimg\{[^}]*padding:var\(--hero-media-grid-padding,27%\)/',
            $this->normalize($file),
            sprintf('"%s" hardcodes the inset of a mark inside its tile, which a site cannot then retune.', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testATileLightsUpOnNoHoverAtAll(string $file): void
    {
        $this->assertStringNotContainsString(
            '.hero__media--gridimg:hover',
            $this->normalize($file),
            sprintf('"%s" lights a grid tile up on hover, which reads as if these marks were links.', $file)
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
