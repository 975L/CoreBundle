<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Security;

use c975L\UiBundle\Tests\Fixtures\HtmlSanitizerKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// "c975l.rich" and "c975l.legal" as FrameworkBundle builds them: what they must take out, and what the block styles and the legal models need to find intact
class HtmlSanitizersTest extends TestCase
{
    private function rich(string $html): string
    {
        return HtmlSanitizerKernel::sanitizer('c975l.rich')->sanitize($html);
    }

    private function legal(string $html): string
    {
        return HtmlSanitizerKernel::sanitizer('c975l.legal')->sanitize($html);
    }

    // What WeasyPrint would read off the server's disk or network and join to a public PDF
    #[DataProvider('weasyPrintPayloads')]
    public function testTheLegalSanitizerNeutralisesWhatWeasyPrintWouldRead(string $payload, string $forbidden): void
    {
        $this->assertStringNotContainsString($forbidden, $this->legal($payload));
    }

    /** @return iterable<string, array{string, string}> */
    public static function weasyPrintPayloads(): iterable
    {
        yield 'attachment link: the rel' => ['<a rel="attachment" href="file:///x">x</a>', 'rel='];
        yield 'attachment link: the url' => ['<a rel="attachment" href="file:///x">x</a>', 'file:'];
        yield 'relative attachment link' => ['<a rel="attachment" href="../../.env">x</a>', 'rel='];
        yield 'link element' => ['<link rel="attachment" href="file:///x">', '<link'];
        yield 'stylesheet' => ['<link rel="stylesheet" href="/x.css">', '<link'];
        yield 'style element' => ['<style>@import url(file:///x);</style>', 'file:'];
        yield 'file image' => ['<img src="file:///x">', 'file:'];
        yield 'internal image' => ['<img src="http://internal/x">', 'internal'];
        yield 'https image' => ['<img src="https://internal/x">', 'internal'];
        yield 'protocol-relative image' => ['<img src="//internal/x">', 'internal'];
        yield 'data image' => ['<img src="data:image/svg+xml;base64,AAAA">', 'data:'];
        yield 'image on the disk' => ['<img src="/etc/server.png">', 'server.png'];
        yield 'image up the tree' => ['<img src="../../var/x.png">', 'x.png'];
        yield 'srcset' => ['<img src="/a.png" srcset="file:///x 1x">', 'file:'];
        yield 'inline style' => ['<p style="background:url(file:///x)">x</p>', 'file:'];
        yield 'object' => ['<object data="file:///x"></object>', 'file:'];
        yield 'iframe' => ['<iframe src="file:///x"></iframe>', 'file:'];
    }

    // What a browser would run, whichever sanitizer stands in front of it
    #[DataProvider('scriptPayloads')]
    public function testNeitherSanitizerLetsAScriptThrough(string $payload, string $forbidden): void
    {
        $this->assertStringNotContainsString($forbidden, $this->rich($payload));
        $this->assertStringNotContainsString($forbidden, $this->legal($payload));
    }

    /** @return iterable<string, array{string, string}> */
    public static function scriptPayloads(): iterable
    {
        yield 'script' => ['<p>x</p><script>alert(1)</script>', 'alert'];
        yield 'onerror' => ['<img src="/a.png" onerror="alert(1)">', 'onerror'];
        yield 'onclick' => ['<a href="/a" onclick="alert(1)">x</a>', 'onclick'];
        yield 'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'];
        yield 'file link' => ['<a href="file:///x">x</a>', 'file:'];
        yield 'data link' => ['<a href="data:text/html,x">x</a>', 'data:'];
        yield 'form' => ['<form action="https://evil.test"><input name="password"></form>', 'evil.test'];
        yield 'link' => ['<link rel="stylesheet" href="https://evil.test/x.css">', 'evil.test'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">', 'evil.test'];
        yield 'style' => ['<style>body{display:none}</style>', 'display'];
    }

    // What an editor's text is made of: Trix's alignment class on its div, headings, lists, anchors, links of every scheme kept, and medias both relative and absolute
    public function testTheRichSanitizerKeepsWhatAnEditorWrote(): void
    {
        $kept = [
            '<div class="text-center">x</div>',
            '<h2 id="anchor">x</h2>',
            '<ul><li><strong>x</strong></li></ul>',
            '<blockquote>x</blockquote>',
            '<a href="https://example.org" target="_blank" rel="noopener">x</a>',
            '<a href="/page#anchor">x</a>',
            '<a href="mailto:a&#64;example.org">x</a>',
            '<a href="tel:&#43;33123456789">x</a>',
            '<img src="/medias/a.webp" alt="a" />',
            '<img src="https://example.org/a.webp" alt="a" />',
        ];

        foreach ($kept as $html) {
            $this->assertSame($html, $this->rich($html));
        }
    }

    // The default silently cuts at 20000 characters
    public function testALongTextIsNotCutInTwo(): void
    {
        $html = '<p>' . str_repeat('a', 30000) . '</p>';

        $this->assertSame($html, $this->rich($html));
        $this->assertSame($html, $this->legal($html));
    }

    // The markup of a legal model, down to what the stylesheets and the edit screen find it by
    public function testTheLegalSanitizerKeepsTheMarkupOfALegalModel(): void
    {
        $kept = [
            '<section data-legal-id="acceptance"><h2>x</h2><p>x</p></section>',
            '<h3 data-legal-id="warranties.goods">x</h3>',
            '<div class="legal"><div class="legal-boxed">x</div></div>',
            '<p class="text-center">x</p>',
            '<table><thead><tr><th>x</th></tr></thead><tbody><tr><td>x</td></tr></tbody></table>',
            '<blockquote>x</blockquote>',
            '<a href="https://example.org" target="_blank">x</a>',
            '<a href="/page">x</a>',
            '<a href="mailto:a&#64;example.org">x</a>',
        ];

        foreach ($kept as $html) {
            $this->assertSame($html, $this->legal($html));
        }
    }

    // A legal page in a browser: what an editor's link says of itself stays, and legal-model-edit.js still finds its sections. Only the PDF's sanitizer drops "rel"
    public function testTheRichSanitizerKeepsTheRelAndTheSectionIdsOfALegalPage(): void
    {
        $html = '<section data-legal-id="a"><a href="https://example.org" rel="noopener">x</a></section>';

        $this->assertSame($html, $this->rich($html));
        $this->assertSame('<section data-legal-id="a"><a href="https://example.org">x</a></section>', $this->legal($html));
    }

    // A site with a html_sanitizer of its own keeps it and still gets the bundle's two, which the templates and LegalModelExtension cannot do without
    public function testASiteWithItsOwnSanitizerBootsWithAllThree(): void
    {
        $kernel = new HtmlSanitizerKernel('test_own_sanitizer', false, ['sanitizers' => ['app.own' => ['allow_elements' => ['b' => []]]]]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $this->assertSame('<b>x</b>', $container->get('html_sanitizer.sanitizer.app.own')->sanitize('<b>x</b><i>y</i>'));
        $this->assertSame('<div class="text-center">x</div>', $container->get('html_sanitizer.sanitizer.c975l.rich')->sanitize('<div class="text-center">x</div>'));
        $this->assertSame('<p data-legal-id="a">x</p>', $container->get('html_sanitizer.sanitizer.c975l.legal')->sanitize('<p data-legal-id="a">x</p>'));

        $kernel->shutdown();
    }

    // The file the PDF engine actually receives, through the template and the real filter
    public function testThePdfTemplateHandsTheEngineNothingItWouldRead(): void
    {
        $twig = HtmlSanitizerKernel::register(new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates')));

        $pdf = $twig->render('legal/pdf.html.twig', [
            'locale' => 'fr',
            'html' => '<section data-legal-id="a"><h2>Title</h2><a rel="attachment" href="file:///etc/passwd">x</a><img src="http://internal/x"><link rel="attachment" href="file:///x"></section>',
        ]);

        $this->assertStringContainsString('<section data-legal-id="a"><h2>Title</h2>', $pdf);
        $this->assertStringNotContainsString('attachment', $pdf);
        $this->assertStringNotContainsString('file:', $pdf);
        $this->assertStringNotContainsString('internal', $pdf);
        $this->assertStringNotContainsString('<link', $pdf);
    }
}
