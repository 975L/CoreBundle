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

// The ".help-text" Symfony's form_div_layout puts on a field's help sits closer to its field than to the next label, which it touched without a rule of its own
class FormHelpTextTest extends TestCase
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

    // Pulled up under its field and pushed off the next label
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAHelpTextKeepsOffTheNextLabel(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.help-text\{[^}]*margin-block:-0?\.5em1em/',
            $this->normalize($file),
            sprintf('"%s" no longer spaces a field\'s help, which touches the next field\'s label again.', $file)
        );
    }

    // Normalized so the same assertion holds whatever the compiler wrapped
    private function normalize(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
