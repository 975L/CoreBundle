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

// Sass writes a UTF-8 BOM into any --style=compressed output holding a non-ASCII byte. A browser drops a
// BOM sitting at the very start of a stylesheet, so it costs nothing while each file is served on its own
// <link> - which is why it went unnoticed. Concatenated by StylesheetCacheWarmer into bundles/build/site.css
// (what every non-debug app is served), every BOM but the first lands mid-file, where it is a stray
// character that turns the rule following it into a parse error: the whole rule is thrown away. Here that
// rule was "@layer ui-defaults", i.e. every token default at once.
//
// Two guards, because either alone would be enough only until the next stylesheet is added: the compiled
// files carry no BOM, and the warmer strips one anyway.
class StylesheetByteOrderMarkTest extends TestCase
{
    private const BOM = "\u{FEFF}";

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        $files = glob(\dirname(__DIR__, 2) . '/public/css/*.css') ?: [];

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoCompiledStylesheetStartsWithAByteOrderMark(string $file): void
    {
        $head = (string) file_get_contents($file, false, null, 0, 3);

        $this->assertNotSame(self::BOM, $head, sprintf(
            '"%s" starts with a UTF-8 BOM. Recompile it with sass --no-charset, or the rule following it is dropped once StylesheetCacheWarmer concatenates it after another stylesheet.',
            basename($file)
        ));
    }

    public function testTheWarmerStripsAByteOrderMarkAnyway(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/CacheWarmer/StylesheetCacheWarmer.php');

        $this->assertStringContainsString('stripByteOrderMark', $source, 'StylesheetCacheWarmer no longer strips the BOM off the stylesheets it concatenates.');
        $this->assertMatchesRegularExpression('/str_starts_with\(\$css, "\\\\u\{FEFF\}"\)/', $source, 'The BOM guard in StylesheetCacheWarmer no longer tests for U+FEFF.');
    }
}
