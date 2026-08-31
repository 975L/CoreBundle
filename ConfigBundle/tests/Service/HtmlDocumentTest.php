<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\HtmlDocument;
use PHPUnit\Framework\TestCase;

class HtmlDocumentTest extends TestCase
{
    // The whole reason the loader forces the encoding: DOMDocument reads a page with no <meta charset> as ISO-8859-1, and a title read that way comes back mangled
    public function testAccentedTextIsReadAsUtf8WithoutAMetaCharset(): void
    {
        $xpath = HtmlDocument::xpath('<html><body><h1>Généalogie à Thônes</h1></body></html>');

        $this->assertSame('Généalogie à Thônes', $xpath->query('//h1')->item(0)?->textContent);
    }

    public function testAPageDeclaringItsOwnCharsetIsReadTheSameWay(): void
    {
        $xpath = HtmlDocument::xpath('<html><head><meta charset="utf-8"></head><body><p>Été</p></body></html>');

        $this->assertSame('Été', $xpath->query('//p')->item(0)?->textContent);
    }

    // Malformed markup is what a live site actually serves, and a checker reading it has to answer on what it could parse rather than throw
    public function testMalformedMarkupIsParsedWithoutAnError(): void
    {
        $xpath = HtmlDocument::xpath('<html><body><p>Un<div><span>deux</p></body>');

        $this->assertSame('deux', $xpath->query('//span')->item(0)?->textContent);
    }

    // The setting is process-wide: left on, it silences the parse errors every other libxml reader of the process relies on
    public function testTheLibxmlInternalErrorSettingIsRestored(): void
    {
        $before = libxml_use_internal_errors(false);
        HtmlDocument::xpath('<html><body><p>Un</body>');
        $after = libxml_use_internal_errors($before);

        $this->assertFalse($after);
    }

    // Only an element carries the attributes the checkers read off it, so a text node matched by an expression never reaches them
    public function testElementsYieldsOnlyElements(): void
    {
        $xpath = HtmlDocument::xpath('<html><body><a href="/contact">Contact</a><a href="/legal">Legal</a></body></html>');

        $hrefs = [];
        foreach (HtmlDocument::elements($xpath, '//a[@href]') as $element) {
            $hrefs[] = $element->getAttribute('href');
        }

        $this->assertSame(['/contact', '/legal'], $hrefs);
    }

    public function testElementsSkipsTheNodesThatAreNotElements(): void
    {
        $xpath = HtmlDocument::xpath('<html><body><a href="/contact">Contact</a></body></html>');

        $this->assertSame([], iterator_to_array(HtmlDocument::elements($xpath, '//a/@href'), false));
    }

    public function testAnExpressionMatchingNothingYieldsNothing(): void
    {
        $xpath = HtmlDocument::xpath('<html><body><p>Un</p></body></html>');

        $this->assertSame([], iterator_to_array(HtmlDocument::elements($xpath, '//iframe'), false));
    }
}
