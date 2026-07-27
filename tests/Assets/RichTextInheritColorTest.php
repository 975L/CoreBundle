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

// A theme coloring every element directly ("* { color: var(--text) }") repaints the <strong>/<em>/... a
// rich text editor produces, instead of letting them follow the color of the section they sit in - which
// turned a white hero title black as soon as a word was set bold. sass/_rich-text.scss puts the browser
// default (inherit) back, and this locks it in the *compiled* stylesheets, the ones actually served.
class RichTextInheritColorTest extends TestCase
{
    private const INLINE_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins', 'sub', 'sup', 'small', 'span'];

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
    public function testInlineFormattingInheritsItsColor(string $file): void
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $inheriting = $this->tagsInheritingColor((string) file_get_contents($path));

        foreach (self::INLINE_TAGS as $tag) {
            $this->assertContains(
                $tag,
                $inheriting,
                sprintf('"%s" has no bare "%s" rule setting "color: inherit", so the theme repaints it.', $file, $tag)
            );
        }
    }

    // Returns the element names of every bare-element rule (no class, no id, no descendant) declaring
    // "color: inherit" - anything more specific is a component rule, not the base layer checked here
    private function tagsInheritingColor(string $css): array
    {
        $tags = [];
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!preg_match('/color\s*:\s*inherit\b/', $match[2])) {
                continue;
            }

            foreach (explode(',', $match[1]) as $selector) {
                $selector = trim($selector);

                if (preg_match('/^[a-z]+$/', $selector)) {
                    $tags[] = $selector;
                }
            }
        }

        return $tags;
    }
}
