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

// A token read with neither a default in _tokens.scss nor an inline fallback silently drops its rule
class TokenDefaultsTest extends TestCase
{
    // Read from EasyAdmin's own Bootstrap theme, which declares them on the management pages
    private const array PROVIDED_BY_EASYADMIN = ['--bs-primary', '--bs-secondary-bg', '--bs-tertiary-bg'];

    // Written by JS on the element itself, never read off :root - a default would apply to nothing
    private const array RUNTIME_ONLY = ['--image-compare-position', '--slider-freeflow-vw'];

    public function testEveryTokenReadHasADefaultOrAnInlineFallback(): void
    {
        $declared = array_keys($this->tokenDefaults());
        $undefaulted = [];

        foreach ($this->sassFiles() as $file) {
            $css = (string) file_get_contents($file);
            $selfDeclared = $this->selfDeclared($css);

            // "var(--x)" with no comma, i.e. no inline fallback of its own
            preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/', $css, $matches);

            foreach (array_unique($matches[1]) as $token) {
                if (
                    \in_array($token, $declared, true)
                    || \in_array($token, $selfDeclared, true)
                    || \in_array($token, self::PROVIDED_BY_EASYADMIN, true)
                    || \in_array($token, self::RUNTIME_ONLY, true)
                ) {
                    continue;
                }

                $undefaulted[] = sprintf('%s (%s)', $token, basename($file));
            }
        }

        $this->assertSame([], $undefaulted, sprintf(
            "Read with no default in sass/_tokens.scss and no inline fallback - without SiteBundle installed, those declarations are dropped:\n- %s",
            implode("\n- ", $undefaulted)
        ));
    }

    // The layer is what makes these defaults lose to a site's own theme, an unlayered rule always winning
    public function testTheDefaultsAreLayered(): void
    {
        $sass = (string) file_get_contents($this->path('sass/_tokens.scss'));
        $this->assertMatchesRegularExpression('/@layer\s+[a-z-]+\s*\{/', $sass, 'sass/_tokens.scss declares its defaults outside a @layer: they would compete with SiteBundle on source order alone.');

        $css = (string) file_get_contents($this->path('public/css/styles.css'));
        $this->assertStringContainsString('@layer ui-defaults', $css, 'The compiled stylesheet carries no layer, the sass has not been recompiled.');

        // Nothing else may join the layer: a component rule put here would stop winning against the base
        $this->assertSame(1, preg_match_all('/@layer/', $css), 'More than one @layer in the compiled stylesheet - only the token defaults belong in one.');
    }

    public function testNoDefaultIsDeclaredForATokenNothingReads(): void
    {
        $read = [];
        foreach ($this->sassFiles() as $file) {
            preg_match_all('/var\(\s*(--[a-z0-9-]+)/', (string) file_get_contents($file), $matches);
            $read = [...$read, ...$matches[1]];
        }
        $read = array_unique($read);

        $orphans = [];
        foreach ($this->tokenDefaults() as $token => $value) {
            // A token only referenced by another default's value is still needed, e.g. --button-background-primary behind --primary
            if (!\in_array($token, $read, true) && !$this->referencedByAnotherDefault($token)) {
                $orphans[] = $token;
            }
        }

        $this->assertSame([], $orphans, sprintf('sass/_tokens.scss defaults %s, which nothing in this bundle reads.', implode(', ', $orphans)));
    }

    private function referencedByAnotherDefault(string $token): bool
    {
        return array_any($this->tokenDefaults(), fn ($value, $name) => $name !== $token && str_contains($value, 'var(' . $token));
    }

    /**
     * @return array<string, string>
     */
    private function tokenDefaults(): array
    {
        $sass = (string) file_get_contents($this->path('sass/_tokens.scss'));
        preg_match_all('/^\s*(--[a-z0-9-]+):\s*(.+?);\s*$/m', $sass, $matches, PREG_SET_ORDER);

        $defaults = [];
        foreach ($matches as $match) {
            $defaults[$match[1]] = trim($match[2]);
        }
        $this->assertNotEmpty($defaults, 'sass/_tokens.scss declares no token, the test itself is broken.');

        return $defaults;
    }

    // Tokens a file sets itself (on :root or on an element) are its own, not the theme's
    private function selfDeclared(string $css): array
    {
        preg_match_all('/^\s*(--[a-z0-9-]+):/m', $css, $matches);

        return $matches[1];
    }

    /**
     * @return string[]
     */
    private function sassFiles(): array
    {
        $dir = $this->path('sass');

        return [...glob($dir . '/*.scss'), ...glob($dir . '/management/*.scss'), ...glob($dir . '/emails/*.scss')];
    }

    private function path(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return $path;
    }
}
