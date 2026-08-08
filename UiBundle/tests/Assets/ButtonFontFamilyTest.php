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

// The two families of button this bundle ships read in the same font, the body one: ".btn" set the title font where ".section-btn" inherited whatever wrapped it, and two buttons of the same page came out drawn differently
class ButtonFontFamilyTest extends TestCase
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

    // Stated on both, rather than inherited: a section wrapping its button in a titled block would otherwise hand it the title font
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testBothButtonFamiliesStateTheBodyFont(string $file): void
    {
        foreach (['.btn', '.section-btn'] as $selector) {
            $this->assertStringContainsString(
                'font-family:var(--font-family-body)',
                $this->declaringRule($file, $selector),
                sprintf('"%s" no longer draws "%s" in the body font.', $file, $selector)
            );
        }
    }

    // The title font on a button is what this locks out, whichever of the two rules would carry it back
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoButtonRuleCarriesTheTitleFont(string $file): void
    {
        foreach (['.btn', '.section-btn'] as $selector) {
            foreach ($this->rules($file, $selector) as $rule) {
                $this->assertStringNotContainsString(
                    'font-family:var(--font-family-title)',
                    $rule,
                    sprintf('"%s" draws "%s" in the title font.', $file, $selector)
                );
            }
        }
    }

    // The one rule of that selector actually naming a font, the others (margins, colors) saying nothing about it
    private function declaringRule(string $file, string $selector): string
    {
        foreach ($this->rules($file, $selector) as $rule) {
            if (str_contains($rule, 'font-family:')) {
                return $rule;
            }
        }

        $this->fail(sprintf('No "%s" rule declaring a font-family found in "%s".', $selector, $file));
    }

    /**
     * The declarations of every rule written for exactly that selector, whitespace out so the same assertions read both the expanded and the minified sheet.
     *
     * @return list<string>
     */
    private function rules(string $file, string $selector): array
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('/\s+/', '', (string) file_get_contents($path));
        preg_match_all('/' . preg_quote($selector, '/') . '\{([^}]*)\}/', $css, $matches);

        $this->assertNotEmpty($matches[1], sprintf('No "%s" rule found in "%s".', $selector, $file));

        return $matches[1];
    }
}
