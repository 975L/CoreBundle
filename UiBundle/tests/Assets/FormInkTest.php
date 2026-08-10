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

// The two inks of a form each read the token they belong to rather than "--black", which also carries ".lead" and SiteBundle's "--navbar-text" default: retuning a label used to mean retuning all three, and a focused field used to swap its ink under any site setting "--form-input-color" alone
class FormInkTest extends TestCase
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

    // A label reads its own token, defaulted to --black in "sass/_tokens.scss" so it still follows the page into dark mode
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testALabelReadsItsOwnToken(string $file): void
    {
        $this->assertSame(
            'var(--form-label-color)',
            $this->color($file, 'label'),
            sprintf('"%s" no longer draws a label with "--form-label-color".', $file)
        );
    }

    // Focus is marked by the border and the ring, so the ink stays what it is at rest
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAFocusedFieldKeepsTheInkItHasAtRest(string $file): void
    {
        $this->assertSame(
            'var(--form-input-color)',
            $this->color($file, 'textarea:focus'),
            sprintf('"%s" changes the ink of a field on focus.', $file)
        );
    }

    // The color declared by the one rule written for that exact selector, whitespace out so the same assertions read both the expanded and the minified sheet
    private function color(string $file, string $selector): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('/\s+/', '', (string) file_get_contents($path));

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, \PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!\in_array($selector, explode(',', $match[1]), true)) {
                continue;
            }

            if (preg_match('/(?:^|;)color:([^;]+)/', $match[2], $color)) {
                return $color[1];
            }
        }

        $this->fail(sprintf('No "%s" rule declaring a color found in "%s".', $selector, $file));
    }
}
