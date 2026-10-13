<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Twig\LinkifyExtension;
use c975L\UiBundle\Twig\Nl2brExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

// A system email's HTML twin: an address alone on its line is a button, one within a sentence a link, the rest escaped text
class EmailTextLinksTest extends TestCase
{
    public function testAnAddressAloneOnItsLineBecomesAButton(): void
    {
        $html = $this->render("A network refused:\n\n- Post - Bluesky : failed\n  https://example.com/retry?a=1&b=2\n\n- Other - Bluesky : failed\n  https://example.com/other");

        $this->assertStringContainsString('<a href="https://example.com/retry?a=1&amp;b=2" target="_blank" class="btn"', $html);
        $this->assertStringContainsString('<a href="https://example.com/other" target="_blank" class="btn"', $html);
        $this->assertSame(2, substr_count($html, 'ui:label.email_open_link'));
        $this->assertStringContainsString('A network refused:<br>' . "\n" . '<br>' . "\n" . '- Post - Bluesky : failed</td>', $html);
        $this->assertStringContainsString('>- Other - Bluesky : failed</td>', $html);
    }

    public function testAnAddressWithinASentenceBecomesALink(): void
    {
        $html = $this->render('See https://example.com/page for more.');

        $this->assertStringContainsString('See <a href="https://example.com/page" target="_blank" rel="noopener noreferrer">https://example.com/page</a> for more.', $html);
        $this->assertStringNotContainsString('class="btn"', $html);
    }

    public function testTextWithoutAddressIsEscapedWithItsLinesKept(): void
    {
        $html = $this->render("28 run(s): 28 ok\n<b>Last</b> run");

        $this->assertStringContainsString('28 run(s): 28 ok<br>' . "\n" . '&lt;b&gt;Last&lt;/b&gt; run</td>', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    // The bare environment with the two filters the template reads through, the bundle's namespace and a "trans" echoing its domain and key
    private function render(string $text): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'c975LUi');
        $twig = new Environment($loader, ['autoescape' => 'html']);
        $twig->addExtension(new LinkifyExtension());
        $twig->addExtension(new Nl2brExtension());
        $twig->addFilter(new TwigFilter('trans', static fn (string $key, array $parameters = [], ?string $domain = null): string => $domain . ':' . $key));

        return $twig->render('@c975LUi/emails/text.html.twig', ['text' => $text]);
    }
}
