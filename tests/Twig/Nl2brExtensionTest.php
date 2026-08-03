<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\Nl2brExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class Nl2brExtensionTest extends TestCase
{
    // Newlines are converted to HTML5 <br> tags, without a trailing slash (is_xhtml=false)
    public function testNl2brConvertsNewlinesToBrTags(): void
    {
        $this->assertSame("Line 1<br>\nLine 2", Nl2brExtension::nl2br("Line 1\nLine 2"));
    }

    // A null input (e.g. an empty Twig variable) must not trigger a deprecation, and yields an empty string
    public function testNl2brHandlesNullGracefully(): void
    {
        $this->assertSame('', Nl2brExtension::nl2br(null));
    }

    // The 'pre_escape' option of the native filter must be kept, or {{ value|nl2br }} would output unescaped user content
    public function testNl2brEscapesItsInput(): void
    {
        $this->assertSame(
            "&lt;script&gt;x&lt;/script&gt;<br>\nline",
            $this->render('{{ v|nl2br }}', "<script>x</script>\nline")
        );
    }

    // ... while an explicitly raw value (e.g. Trix rich text) still goes through untouched, the way the templates of this bundle use it
    public function testNl2brLeavesRawValuesUntouched(): void
    {
        $this->assertSame(
            "<b>bold</b><br>\nline",
            $this->render('{{ v|raw|nl2br }}', "<b>bold</b>\nline")
        );
    }

    // Renders a one-liner through a real autoescaping Twig environment carrying the extension
    private function render(string $template, string $value): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]), ['autoescape' => 'html']);
        $twig->addExtension(new Nl2brExtension());

        return $twig->render('t', ['v' => $value]);
    }
}
