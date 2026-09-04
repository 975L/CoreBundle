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
use Twig\Extension\AttributeExtension;

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
            'Line 1 <br>Line 2 <br>Line 3',
            $extension->trixInline('<div>Line 1</div><div>Line 2</div><div>Line 3</div>')
        );
    }

    public function testTrixInlineStripsWrappingDivAttributes(): void
    {
        $extension = new TrixExtension();

        $this->assertSame(
            'Line 1 <br>Line 2',
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
        $filters = $this->filtersByName();

        // Indexed by name rather than read in order: the attributes are collected in the methods' declaration order, which is no part of the contract
        $names = array_keys($filters);
        sort($names);
        $this->assertSame(['plain_text', 'trix_inline'], $names);
        $this->assertSame(['html'], $filters['trix_inline']->getSafe(new \Twig\Node\TextNode('', 0)));
    }

    public function testGetFiltersRegistersPlainTextFilterAsEscapable(): void
    {
        $filters = $this->filtersByName();

        $this->assertArrayHasKey('plain_text', $filters);
        $this->assertSame([], $filters['plain_text']->getSafe(new \Twig\Node\TextNode('', 0)));
    }

    // What TwigBundle reads from the #[AsTwigFilter] attributes, keyed by the name each one declares
    private function filtersByName(): array
    {
        $filters = [];
        foreach (new AttributeExtension(TrixExtension::class)->getFilters() as $filter) {
            $filters[$filter->getName()] = $filter;
        }

        return $filters;
    }

    // The heart of the space: a title typed on two lines is read by a search engine as one word without it, and "Marredes radars" matches no query anyone types
    public function testTheGeneratedBreakKeepsTheWordsApartForWhoeverDoesNotRenderIt(): void
    {
        $inline = new TrixExtension()->trixInline('<div>Marre</div><div>des radars</div>');

        $this->assertSame('Marre des radars', strip_tags($inline));
    }

    // A break already stored as "<br>" - written by an older editor, an import or by hand - is separated too: the rule above only covers the ones this method makes out of the editor's divs
    public function testABreakAlreadyInTheStoredTextIsSeparatedAsWell(): void
    {
        $inline = new TrixExtension()->trixInline('<div><strong>Marre<br>des radars</strong></div>');

        $this->assertSame('Marre des radars', strip_tags($inline));
    }

    // A break already written with its space keeps it, rather than collecting a second one
    public function testABreakThatAlreadyHasItsSpaceIsLeftAlone(): void
    {
        $inline = new TrixExtension()->trixInline('<div>Marre <br>des radars</div>');

        $this->assertSame('Marre <br>des radars', $inline);
    }
}
