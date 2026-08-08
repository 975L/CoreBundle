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

// A downloadable document reads as a card, not as a full-width bar: several of them sit side by side and wrap, and each one lights up whole rather than by the part the pointer landed on
class DocumentDownloadCardTest extends TestCase
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

    // The row the cards sit in, stretched so every badge lines up whatever the height of the titles above it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheCardsSitSideBySideAndWrap(string $file): void
    {
        $rule = $this->rule($file, '.document-downloads');

        $this->assertStringContainsString('display:flex', $rule, sprintf('"%s" stacks the document cards instead of laying them out in a row.', $file));
        $this->assertStringContainsString('flex-wrap:wrap', $rule);
        $this->assertStringContainsString('align-items:stretch', $rule);
    }

    // A narrow fixed column, thumbnail then title then format - what keeps a single document from spanning the whole measure
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testACardIsANarrowColumnOfItsOwnWidth(string $file): void
    {
        $rule = $this->rule($file, '.document-download');

        $this->assertStringContainsString('flex-direction:column', $rule);
        $this->assertStringContainsString('width:160px', $rule, sprintf('"%s" lets a document card take the width of its container.', $file));
    }

    // SiteBundle's global "a:hover" underlines every link, which reads as a text link inside what is drawn as a card
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheWholeCardLightsUpWithoutBeingUnderlined(string $file): void
    {
        $rule = $this->rule($file, '.document-download:hover,.document-download:focus-visible');

        $this->assertStringContainsString('border-color:var(--primary)', $rule);
        $this->assertStringContainsString('text-decoration:none', $rule, sprintf('"%s" underlines a document card on hover.', $file));
    }

    // The declarations of one rule, whitespace out so the same assertions read both the expanded and the minified sheet
    private function rule(string $file, string $selector): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('/\s+/', '', (string) file_get_contents($path));
        $pattern = '/' . preg_quote($selector, '/') . '\{([^}]*)\}/';

        $this->assertMatchesRegularExpression($pattern, $css, sprintf('No "%s" rule found in "%s".', $selector, $file));
        preg_match($pattern, $css, $matches);

        return $matches[1];
    }
}
