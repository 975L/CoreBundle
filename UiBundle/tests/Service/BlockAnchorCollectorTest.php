<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockAnchorCollector;
use PHPUnit\Framework\TestCase;

class BlockAnchorCollectorTest extends TestCase
{
    private function createBlock(string $kind, array $data, int $id): Block
    {
        $block = new Block()->setKind($kind)->setData($data);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    // "anchor" (HasAnchorFieldTrait) is rendered as "<anchor>-<blockId>" - see BlockExtension::buildAnchorId()
    public function testAnchorFragmentCarriesTheBlockId(): void
    {
        $blocks = [$this->createBlock('hero', ['anchor' => 'services', 'title' => 'Our services'], 7)];

        $this->assertSame(['services-7' => 'Our services'], new BlockAnchorCollector()->collect($blocks));
    }

    // A "slug" (auto-derived from the title, e.g. "text_section") is rendered as-is, with no block id appended
    public function testSlugFragmentIsUsedAsIs(): void
    {
        $blocks = [$this->createBlock('text_section', ['slug' => 'le-manifeste', 'title' => 'Le manifeste'], 16)];

        $this->assertSame(['le-manifeste' => 'Le manifeste'], new BlockAnchorCollector()->collect($blocks));
    }

    // "article" prefixes its slug in the rendered id - see Article.html.twig
    public function testArticleSlugFragmentIsPrefixed(): void
    {
        $blocks = [$this->createBlock('article', ['slug' => 'nordkapp', 'title' => 'Nordkapp'], 20)];

        $this->assertSame(['article-nordkapp' => 'Nordkapp'], new BlockAnchorCollector()->collect($blocks));
    }

    // The sections actually visible on a page are often a container's slots, not the container itself
    public function testSlotsOfAContainerAreWalked(): void
    {
        $container = $this->createBlock('flex_columns', ['anchor' => null], 58);
        $column = $this->createBlock('flex_column', [], 60);
        $column->addSlot($this->createBlock('text_section', ['slug' => 'le-manifeste', 'title' => 'Le manifeste'], 16));
        $container->addSlot($column);

        $this->assertSame(['le-manifeste' => 'Le manifeste'], new BlockAnchorCollector()->collect([$container]));
    }

    // A TrixEditorType title (hero, cta_band) may carry inline markup that must not leak into a select option or a menu label
    public function testTitleIsStrippedOfMarkup(): void
    {
        $blocks = [$this->createBlock('hero', ['anchor' => 'cta', 'title' => '<strong>Call to action</strong>'], 7)];

        $this->assertSame(['cta-7' => 'Call to action'], new BlockAnchorCollector()->collect($blocks));
    }

    // A tag separating two lines is a word boundary: dropping it outright would weld them together ("Marredes radars")
    public function testMarkupSeparatingTwoLinesBecomesASpace(): void
    {
        $blocks = [$this->createBlock('hero', ['anchor' => 'marre', 'title' => '<div><strong>Marre<br>des radars</strong></div>'], 14)];

        $this->assertSame(['marre-14' => 'Marre des radars'], new BlockAnchorCollector()->collect($blocks));
    }

    // Trix stores "&" and a non-breaking space as entities: both must be decoded back, a select option and a menu label being plain text
    public function testEntitiesAreDecoded(): void
    {
        $blocks = [$this->createBlock('hero', ['anchor' => 'contact', 'title' => '<b>Devis &amp; conseil&nbsp;!</b>'], 21)];

        $this->assertSame(['contact-21' => 'Devis & conseil !'], new BlockAnchorCollector()->collect($blocks));
    }

    // Decoded after the markup is stripped, never before: a "&lt;b&gt;" typed as text stays text instead of coming back as a tag
    public function testAnEscapedTagStaysText(): void
    {
        $blocks = [$this->createBlock('hero', ['anchor' => 'balise', 'title' => 'La balise &lt;b&gt;'], 22)];

        $this->assertSame(['balise-22' => 'La balise <b>'], new BlockAnchorCollector()->collect($blocks));
    }

    // A section typed with no title has its eyebrow as heading (see Text/Section.html.twig), then the raw anchor as last resort
    public function testLabelFallsBackToEyebrowThenToTheAnchorItself(): void
    {
        $blocks = [
            $this->createBlock('text_section', ['slug' => 'nos-offres', 'eyebrow' => 'Nos offres'], 17),
            $this->createBlock('feature_bar', ['anchor' => 'chiffres'], 15),
        ];

        $this->assertSame(
            ['nos-offres' => 'Nos offres', 'chiffres-15' => 'chiffres'],
            new BlockAnchorCollector()->collect($blocks)
        );
    }

    // A block declaring neither key (or only blank ones) contributes nothing
    public function testBlockWithoutAnyAnchorIsSkipped(): void
    {
        $blocks = [
            $this->createBlock('feature_bar', ['anchor' => null], 15),
            $this->createBlock('text_section', ['slug' => '  ', 'title' => 'Untitled'], 16),
        ];

        $this->assertSame([], new BlockAnchorCollector()->collect($blocks));
    }
}
