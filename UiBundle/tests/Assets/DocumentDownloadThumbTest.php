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

// The thumbnail box is A4-shaped, so every card of a multi-document block stays aligned - which only works as long as a document that is not A4 is letterboxed rather than cropped to fill it
class DocumentDownloadThumbTest extends TestCase
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

    // "cover" crops a landscape document down to a vertical strip of its left edge, where its first page is exactly what the thumbnail is there to show
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheThumbnailIsContainedInItsBoxRatherThanCropped(string $file): void
    {
        $rule = $this->rule($file);

        $this->assertStringContainsString('object-fit:contain', $rule, sprintf('"%s" crops the document thumbnail instead of containing it.', $file));
        $this->assertStringNotContainsString('object-fit:cover', $rule);
    }

    // The box itself: the card sets the width, the ratio sets the height - dropped, every card of a multi-document block takes the height of its own thumbnail
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheBoxKeepsItsA4Ratio(string $file): void
    {
        $rule = $this->rule($file);

        $this->assertStringContainsString('width:100%', $rule);
        $this->assertStringContainsString('aspect-ratio:1/1.414', $rule);
        // An img's height attribute is a presentational hint outweighing an aspect-ratio declared with no height
        $this->assertStringContainsString('height:auto', $rule);
    }

    // The ".document-download__thumb" declarations, whitespace out so the same assertions read both the expanded and the minified sheet
    private function rule(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path);

        $css = (string) preg_replace('/\s+/', '', (string) file_get_contents($path));

        $this->assertMatchesRegularExpression('/\.document-download__thumb\{([^}]*)\}/', $css, sprintf('No ".document-download__thumb" rule found in "%s".', $file));
        preg_match('/\.document-download__thumb\{([^}]*)\}/', $css, $matches);

        return $matches[1];
    }
}
