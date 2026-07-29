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

// Each display:contents wrapper must be named in the margin reset, or its blocks get the gap back
class SectionMarginResetTest extends TestCase
{
    private const RESET_RULE = 'main :is(.blocks, .block-animation, .block-editable) > section';

    // Where each transparent wrapper is declared
    private const WRAPPER_SHEETS = ['_animations-media.scss', '_block-edit-overlay.scss', '_page-sections.scss'];

    public function testTheResetIsCompiledIntoBothStylesheets(): void
    {
        foreach (['styles.css', 'styles.min.css'] as $file) {
            $this->assertStringContainsString(
                str_replace(' ', '', self::RESET_RULE) . '{margin:0}',
                $this->normalize($file),
                sprintf('"%s" no longer drops the page-wide section margin inside a block wrapper.', $file)
            );
        }
    }

    // A rule turning a bare class transparent is such a wrapper; a descendant selector is not
    public function testEveryTransparentWrapperIsNamedByTheReset(): void
    {
        foreach (self::WRAPPER_SHEETS as $sheet) {
            $sass = (string) file_get_contents(dirname(__DIR__, 2) . '/sass/' . $sheet);
            preg_match_all('/^\.([a-z0-9_-]+)\s*\{[^}]*display:\s*contents/mi', $sass, $matches);

            foreach ($matches[1] as $wrapper) {
                $this->assertStringContainsString(
                    '.' . $wrapper,
                    self::RESET_RULE,
                    sprintf('".%s" is display:contents in "%s" but is not named by the section margin reset.', $wrapper, $sheet)
                );
            }
        }
    }

    // Normalized so the same assertion holds on the expanded sheet and on the minified one
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/;\}/', '}', (string) preg_replace('/\s+/', '', $css));
    }
}
