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

// A hero's background media and a banner's picture are decorative grounds their own title is laid over, kept readable on screen by a fixed dark voile. Paper carries black ink (SiteBundle's sass/_print.scss drops every painted ground, that voile included), so a printed title would come out black on a photograph: the picture goes instead, and the block prints as the plain heading it holds.
class PrintedBackgroundMediaTest extends TestCase
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
    public function testAHeroDropsItsBackgroundMediaAndTheRoomGivenToAVideo(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/@mediaprint\{\.hero\.hero--has-bg\.hero__bg\{display:none/',
            $css,
            sprintf('"%s" prints the hero\'s background media, which a black title would be laid over.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/@mediaprint\{[^@]*\.hero\.hero--has-bg:has\(\.hero__bg--video\)\{min-height:0/',
            $css,
            sprintf('"%s" keeps the room a background video was given, so the printed page opens on a blank half-page.', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testABannerDropsItsPicture(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/@mediaprint\{\.banner-title-img\{display:none/',
            $this->normalize($file),
            sprintf('"%s" prints the banner\'s picture, which a black title would be laid over.', $file)
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
