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

// The toggle is a <label>, out of reach of the theme's "a" rules, so it has to take the link color itself
class ReadmoreStyleTest extends TestCase
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
    public function testTheToggleReadsTheThemesLinkColor(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString(
            'color:var(--link-color,#007bff)',
            $css,
            sprintf('"%s" hardcodes the toggle\'s color, so "read more" ignores the theme it sits in.', $file)
        );
    }

    // Mixed out of the link color, not out of --primary, so a theme setting a distinct link color keeps its own hover
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheHoverIsDerivedFromThatSameColor(string $file): void
    {
        $this->assertStringContainsString(
            'color:color-mix(insrgb,var(--link-color,#007bff)85%,black)',
            $this->normalize($file),
            sprintf('"%s" no longer derives the toggle\'s hover from the link color it just took.', $file)
        );
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
