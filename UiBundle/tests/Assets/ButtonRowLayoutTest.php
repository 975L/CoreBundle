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

// A link button centers itself with "margin: 0 auto", which is what a row of them has to undo: in a flex row auto margins absorb the free space instead, spreading the buttons across the whole width. Two rules hold that, and the second is the one that broke - stated next to the ink a link button restates in each of its states, the layout came back with a pseudo-class in its selector, outweighed what the row had said, and put a hovered button back where it sat before the row grouped them
class ButtonRowLayoutTest extends TestCase
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

    // Only the horizontal margins go, the row being spaced by its own gap
    #[DataProvider('stylesheetProvider')]
    public function testARowTakesTheAutoMarginsOffItsButtons(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.flex-center>\.btn\{[^}]*margin-inline:0/',
            $this->normalize($file),
            sprintf('"%s" leaves the auto margins on the buttons of a ".flex-center" row, which spreads them across its whole width instead of grouping them.', $file)
        );
    }

    // The bare selector is what a row outweighs with one class of its own; a pseudo-class on it wins over that row for as long as the pointer is on the button
    #[DataProvider('stylesheetProvider')]
    public function testTheLinkButtonsLayoutIsStatedWithoutAPseudoClass(string $file): void
    {
        $css = $this->normalize($file);
        $found = false;

        foreach ($this->rules($css) as [$selectors, $body]) {
            if (!str_contains($body, 'margin:0auto')) {
                continue;
            }

            foreach (explode(',', $selectors) as $selector) {
                if ('a.btn' === $selector) {
                    $found = true;

                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![\w.-])a\.btn:/',
                    $selector,
                    sprintf('"%s" states a link button\'s layout on "%s", a selector a row of buttons cannot outweigh.', $file, $selector)
                );
            }
        }

        $this->assertTrue($found, sprintf('"%s" no longer centers a lone link button, this expectation is stale.', $file));
    }

    /**
     * The selector list and the body of every rule, the at-rule wrappers left out - what is read here sits inside them just the same.
     *
     * @return list<array{string, string}>
     */
    private function rules(string $css): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2]], $matches);
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
