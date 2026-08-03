<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Form\BlockAccentChoiceType;
use PHPUnit\Framework\TestCase;

/*
 * A card's accent colors its header band: each ".card--accent-<hue>" class only points --card-accent at
 * its own token, and ".card-header" is what paints it. A card with no header therefore shows no accent,
 * and an unaccented one stays on --primary, which is what every card stored before the field existed holds.
 */
class CardAccentTest extends TestCase
{
    // White falls under 4.5:1 on these four, so they carry dark text and stop the icon's inversion with it
    private const DARK_TEXT_HUES = ['orange', 'yellow', 'lime', 'teal'];

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

    // Every hue the picker offers has to reach a rule, or a stored value colors nothing at all
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryHuePointsTheAccentAtItsOwnToken(string $file): void
    {
        $css = $this->normalize($file);

        foreach (BlockAccentChoiceType::CHOICES as $hue) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.card--accent-%s\{[^}]*--card-accent:var\(--block-accent-%s\)/', $hue, $hue),
                $css,
                sprintf('"%s" leaves the "%s" accent without a rule setting --card-accent.', $file, $hue)
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheHeaderBandIsWhatPaintsTheAccent(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.card-header,h2\.card-header\{[^}]*background-color:var\(--card-accent,var\(--primary\)\)/',
            $css,
            sprintf('"%s" no longer has the header band read --card-accent, so an accented card is headed with --primary.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.card-header,h2\.card-header\{[^}]*color:var\(--card-accent-color,var\(--white\)\)/',
            $css,
            sprintf('"%s" writes a fixed color on the header band, which the four light hues cannot darken.', $file)
        );
    }

    // Nothing paints the accent but the band: a rule across the top edge would show on a headerless card
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoHueDrawsARuleOfItsOwn(string $file): void
    {
        $this->assertStringNotContainsString(
            '.card--accent-red::before',
            $this->normalize($file),
            sprintf('"%s" still draws the accent as a rule, which a card with no header would show on its own.', $file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheLightHuesCarryDarkTextAndANonInvertedIcon(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::DARK_TEXT_HUES as $hue) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.card--accent-%s\{[^}]*--card-accent-color:#000/', $hue),
                $css,
                sprintf('"%s" leaves the "%s" header on white text, which falls under 4.5:1 on it.', $file, $hue)
            );
            $this->assertMatchesRegularExpression(
                sprintf('/\.card--accent-%s\{[^}]*--card-accent-invert:0/', $hue),
                $css,
                sprintf('"%s" still inverts the icon of a "%s" header, whose title is dark.', $file, $hue)
            );
        }
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
