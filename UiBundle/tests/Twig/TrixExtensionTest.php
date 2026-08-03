<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\TrixExtension;
use PHPUnit\Framework\TestCase;

class TrixExtensionTest extends TestCase
{
    public function testTrixInlineReturnsEmptyStringForNull(): void
    {
        $extension = new TrixExtension();

        $this->assertSame('', $extension->trixInline(null));
    }

    public function testTrixInlineReturnsEmptyStringForEmptyString(): void
    {
        $extension = new TrixExtension();

        $this->assertSame('', $extension->trixInline(''));
    }

    public function testTrixInlineStripsSingleWrappingDiv(): void
    {
        $extension = new TrixExtension();

        $this->assertSame('Hello world', $extension->trixInline('<div>Hello world</div>'));
    }

    public function testTrixInlineJoinsMultipleLinesWithBr(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Line 1<br>Line 2<br>Line 3',
            $extension->trixInline('<div>Line 1</div><div>Line 2</div><div>Line 3</div>')
        );
    }

    public function testTrixInlineStripsWrappingDivAttributes(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Line 1<br>Line 2',
            $extension->trixInline('<div data-trix-id="1">Line 1</div><div class="x">Line 2</div>')
        );
    }

    public function testTrixInlinePreservesInlineFormattingTags(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            '<strong>Bold</strong> text',
            $extension->trixInline('<div><strong>Bold</strong> text</div>')
        );
    }

    public function testPlainTextReturnsEmptyStringForNull(): void
    {
        $extension = new TrixExtension();

        $this->assertSame('', $extension->plainText(null));
    }

    public function testPlainTextReturnsEmptyStringForEmptyString(): void
    {
        $extension = new TrixExtension();

        $this->assertSame('', $extension->plainText(''));
    }

    public function testPlainTextDropsEveryTag(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Bold text',
            $extension->plainText('<div class="text-center"><strong>Bold</strong> text</div>')
        );
    }

    public function testPlainTextDecodesEntitiesSoTwigDoesNotEscapeThemTwice(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Superéthanol E85 & FlexFuel « l\'essai »',
            $extension->plainText('<div>Superéthanol E85 &amp; FlexFuel &laquo; l&#39;essai &raquo;</div>')
        );
    }

    public function testPlainTextCollapsesTheSpacesLeftByTheBlockTags(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Line 1 Line 2 Line 3',
            $extension->plainText('<div>Line 1</div><div>Line 2<br>Line 3</div>')
        );
    }

    public function testPlainTextLeavesNoSpaceWhereAnInlineTagClosedMidSentence(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Votre Garage Branché by REVOLTE. Pour 100 ans !',
            $extension->plainText('<div><strong>Votre Garage Branché by </strong><a href="#"><strong>REVOLTE</strong></a><strong>. Pour 100 ans !</strong></div>')
        );
    }

    public function testGetFiltersRegistersTrixInlineFilterAsHtmlSafe(): void
    {
        $extension = new TrixExtension();
        $filters = $extension->getFilters();

        $this->assertCount(2, $filters);
        $this->assertSame('trix_inline', $filters[0]->getName());
        $this->assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\TextNode('', 0)));
    }

    public function testGetFiltersRegistersPlainTextFilterAsEscapable(): void
    {
        $extension = new TrixExtension();
        $filters = $extension->getFilters();

        $this->assertSame('plain_text', $filters[1]->getName());
        $this->assertSame([], $filters[1]->getSafe(new \Twig\Node\TextNode('', 0)));
    }
}
