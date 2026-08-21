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

// A band carrying a mention beside its title lays the two on one line. Written on ":has()" and not on every band on purpose: "flex" changes how a long title wraps, and a card with nothing beside its title has to stay the block it has always been.
class CardHeaderAsideTest extends TestCase
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
    public function testOnlyABandHoldingAMentionIsLaidOut(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.card-header:has\(\.card-header__aside\)\{[^}]*display:flex/',
            $this->normalize($file),
            sprintf('"%s" no longer lays out a band holding a mention, so the title and the mention stack instead of sharing one line.', $file)
        );
    }

    // The mention sits at the far end, on the title's own baseline and never squeezed by it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheMentionIsPushedToTheEndOfTheBand(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression('/\.card-header:has\(\.card-header__aside\)\{[^}]*align-items:baseline/', $css);
        $this->assertMatchesRegularExpression('/\.card-header:has\(\.card-header__aside\)\{[^}]*justify-content:space-between/', $css);
        $this->assertMatchesRegularExpression('/\.card-header__aside\{[^}]*flex:none/', $css);
    }

    // Both are read through a token, so a site retunes the mention from its theme file without touching a template
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheMentionReadsItsSizeAndItsOpacityFromTokens(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression('/\.card-header__aside\{[^}]*font-size:var\(--card-header-aside-size\)/', $css);
        $this->assertMatchesRegularExpression('/\.card-header__aside\{[^}]*opacity:var\(--card-header-aside-opacity\)/', $css);
        $this->assertMatchesRegularExpression('/--card-header-aside-size:0?\.75em/', $css);
        $this->assertMatchesRegularExpression('/--card-header-aside-opacity:0?\.8/', $css);
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
