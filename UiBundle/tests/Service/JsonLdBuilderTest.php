<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\JsonLdBuilder;
use PHPUnit\Framework\TestCase;

class JsonLdBuilderTest extends TestCase
{
    public function testABreadcrumbNumbersItsLevelsAndDropsTheEmptyOnes(): void
    {
        $snippet = new JsonLdBuilder()->breadcrumb([
            ['name' => 'Livres', 'url' => 'https://example.test/livres'],
            ['name' => '', 'url' => 'https://example.test/nowhere'],
            ['name' => 'Le Maquis', 'url' => 'https://example.test/livres/le-maquis'],
        ]);

        $this->assertSame('BreadcrumbList', $snippet['@type']);
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Livres', 'item' => 'https://example.test/livres'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Le Maquis', 'item' => 'https://example.test/livres/le-maquis'],
        ], $snippet['itemListElement']);
    }

    // The page on its own is no trail
    public function testASingleLevelIsNoBreadcrumb(): void
    {
        $this->assertSame([], new JsonLdBuilder()->breadcrumb([['name' => 'Accueil', 'url' => 'https://example.test/']]));
    }

    public function testAnItemListNumbersFromTheOffsetAndCountsWhatItHolds(): void
    {
        $snippet = new JsonLdBuilder()->itemList([
            ['name' => 'Un', 'url' => 'https://example.test/un'],
            ['name' => 'Deux', 'url' => 'https://example.test/deux'],
        ], 20);

        $this->assertSame(2, $snippet['numberOfItems']);
        $this->assertSame(21, $snippet['itemListElement'][0]['position']);
        $this->assertSame('https://example.test/deux', $snippet['itemListElement'][1]['url']);
    }

    public function testAnEmptyListIsNoItemList(): void
    {
        $this->assertSame([], new JsonLdBuilder()->itemList([['name' => '', 'url' => '']]));
    }

    // A "</script>" typed into a field must not close the tag, and a stray byte must not empty the whole graph
    public function testTheEncodingCannotCloseItsTagNorFailOnABadByte(): void
    {
        $json = new JsonLdBuilder()->encode(['name' => "</script>\xB1 Été"]);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString('Été', $json);
        $this->assertNotSame('', $json);
    }

    public function testNothingToPublishIsAnEmptyString(): void
    {
        $this->assertSame('', new JsonLdBuilder()->encode([]));
    }

    // strip_tags leaves "&nbsp;" behind, which decodes to a space "\s" does not match
    public function testPlainTextDecodesEntitiesAndCollapsesEveryKindOfSpace(): void
    {
        $this->assertSame('Le pont sur le Fier est détruit.', new JsonLdBuilder()->plainText("<p>Le pont sur&nbsp; le Fier\nest d&eacute;truit.</p>"));
    }
}
