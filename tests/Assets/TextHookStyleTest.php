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

// The "text_hook" block is nothing but a class, so its compiled rule going missing is invisible
class TextHookStyleTest extends TestCase
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
    public function testTheHookRuleIsCompiledIntoBothStylesheets(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.text-hook\{[^}]*font-size:/',
            $css,
            sprintf('"%s" holds no ".text-hook" rule, so a hook reads like the text around it.', $file)
        );
    }

    // What the standalone block has and an article's hook has not: the bar marking it out where nothing
    // around it does. Its own rule, so the base one stays shared instead of being split in two
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheStandaloneModifierCarriesTheBar(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.text-hook--standalone\{[^}]*border-inline-start:/',
            $this->normalize($file),
            sprintf('"%s" holds no ".text-hook--standalone" rule, so the block renders like an article hook.', $file)
        );
    }

    // Nothing declares the hook's own tokens, so each needs an inline fallback or the rule is dropped
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheHooksOwnTokensCarryTheirFallback(string $file): void
    {
        preg_match_all('/var\(--(text-hook-[a-z0-9-]+)([,)])/', $this->normalize($file), $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches, 'The stylesheet reads none of the hook\'s tokens, this test no longer checks anything.');

        foreach ($matches as $match) {
            $this->assertSame(
                ',',
                $match[2],
                sprintf('"%s": "--%s" is read with no fallback, the browser drops that declaration.', $file, $match[1])
            );
        }
    }

    // Whitespace squeezed out, so expanded and minified are matched against the very same needle
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        return (string) preg_replace('/\s+/', '', (string) file_get_contents($path));
    }
}
