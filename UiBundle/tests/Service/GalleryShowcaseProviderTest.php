<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\GalleryShowcaseProvider;
use c975L\UiBundle\Twig\BlockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class GalleryShowcaseProviderTest extends TestCase
{
    /** @var Block[] */
    private array $rendered = [];

    /** @var array<int, array{0: string, 1: array<string, mixed>}> */
    private array $twigRenders = [];

    // The three built-in kinds a BlockFixtureProvider entry can't express - two containers and one live-sourced - and no other: everything else belongs in the fixtures
    public function testTheThreeKindsNoFixtureCanExpressAreCovered(): void
    {
        $showcases = $this->createProvider()->getShowcases();

        $this->assertSame(
            ['flex_columns', 'section_cards', 'collection'],
            array_column($showcases, 'kind')
        );
    }

    // "kind" is what suppresses the empty preview card the showcase draws for a kind of its own, so each showcase shows up once rather than twice
    public function testEachShowcaseNamesTheKindItStandsInForAndDescribesIt(): void
    {
        foreach ($this->createProvider()->getShowcases() as $label => $showcase) {
            $this->assertNotEmpty($label);
            $this->assertNotEmpty($showcase['description']);
            $this->assertNotEmpty($showcase['variants']);
        }
    }

    // The widths are the point of this kind: a single balanced row would never show the twelfths at work
    public function testFlexColumnsShowsTwoWidthSplits(): void
    {
        $variants = $this->createProvider()->getShowcases()['label.gallery_showcase_flex_columns']['variants'];

        $this->assertSame(['Deux colonnes égales', 'Colonnes 8 / 4'], array_keys($variants));

        $splits = [];
        foreach ($this->renderedOfKind('flex_columns') as $section) {
            $splits[] = array_map(
                static fn (Block $column): string => $column->getData()['columnWidth'],
                $section->getSlots()->toArray()
            );
        }

        $this->assertSame([['6', '6'], ['8', '4']], $splits);
    }

    // A column holds blocks of its own - the nested relation that no plain data array can carry, and the whole reason this kind is here rather than in the fixtures
    public function testAFlexColumnHoldsItsOwnBlocksAsSlots(): void
    {
        $this->createProvider()->getShowcases();

        $columns = $this->renderedOfKind('flex_columns')[0]->getSlots();

        $this->assertCount(2, $columns);
        foreach ($columns as $column) {
            $this->assertSame('flex_column', $column->getKind());
            $this->assertCount(1, $column->getSlots());
            $this->assertContains($column->getSlots()->first()->getKind(), ['text_section', 'card']);
        }
    }

    public function testSectionCardsHoldsThreeCardSlotsInOrder(): void
    {
        $this->createProvider()->getShowcases();

        $slots = $this->renderedOfKind('section_cards')[0]->getSlots();

        $this->assertCount(3, $slots);
        foreach ($slots as $position => $card) {
            $this->assertSame('card', $card->getKind());
            $this->assertSame($position, $card->getPosition());
        }
    }

    // Rendered through the Grid component rather than the block's own template, which would query a collection source no showcase has - the items themselves stay real "collection_item" blocks
    public function testCollectionRendersItsItemsAsCollectionItemBlocks(): void
    {
        $this->createProvider()->getShowcases();

        $items = $this->renderedOfKind('collection_item');

        $this->assertCount(6, $items, 'Three items, in each of the two variants');
        $this->assertSame(['@c975LUi/components/Collection/Grid.html.twig'], array_unique(array_column($this->twigRenders, 0)));
        $this->assertSame(['', 'portfolio'], array_column(array_column($this->twigRenders, 1), 'variant'));
    }

    // The one render this provider hand-feeds instead of letting the block pipeline build it, so the component has to find every variable it reads
    public function testTheGridComponentRendersWithTheContextItIsHandedRatherThanFallingShort(): void
    {
        $this->createProvider()->getShowcases();
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        $rendered = [];
        foreach ($this->twigRenders as [, $context]) {
            $rendered[] = $twig->render('components/Collection/Grid.html.twig', $context);
        }

        $this->assertStringNotContainsString('collection-grid--portfolio', $rendered[0]);
        $this->assertStringContainsString('collection-grid--portfolio', $rendered[1]);
        foreach ($rendered as $html) {
            $this->assertStringContainsString('section-title', $html);
            $this->assertSame(3, substr_count($html, '<div>collection_item</div>'));
        }
    }

    // The bundle ships no image of its own, so the portfolio variant borrows whatever the app declared for its showcase
    public function testThePortfolioVariantUsesTheDeclaredPlaceholderImage(): void
    {
        $this->createProvider($this->createPlaceholderMedia(['images' => ['showcase/sample.webp']]))->getShowcases();

        $urls = array_map(
            static fn (Block $item): string => $item->getData()['imageUrl'],
            $this->renderedOfKind('collection_item')
        );

        // Leading "/" so the src is a site-root path whatever page the showcase is rendered on
        $this->assertSame(['', '', '', '/showcase/sample.webp', '/showcase/sample.webp', '/showcase/sample.webp'], $urls);
    }

    // Nothing declared: the cards show their text alone rather than a broken image
    public function testThePortfolioVariantCarriesNoImageWhenNoneIsDeclared(): void
    {
        $this->createProvider()->getShowcases();

        foreach ($this->renderedOfKind('collection_item') as $item) {
            $this->assertSame('', $item->getData()['imageUrl']);
        }
    }

    private function createProvider(?PlaceholderMediaRegistry $placeholderMedia = null): GalleryShowcaseProvider
    {
        $this->rendered = [];
        $this->twigRenders = [];

        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block): string {
            $this->rendered[] = $block;

            return '<div>' . $block->getKind() . '</div>';
        });

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            $this->twigRenders[] = [$template, $context];

            return '<div>grid</div>';
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new GalleryShowcaseProvider($blockExtension, $twig, $translator, $placeholderMedia);
    }

    /** @return Block[] */
    private function renderedOfKind(string $kind): array
    {
        return array_values(array_filter($this->rendered, static fn (Block $block): bool => $kind === $block->getKind()));
    }

    private function createPlaceholderMedia(array $media): PlaceholderMediaRegistry
    {
        $provider = $this->createStub(PlaceholderMediaProviderInterface::class);
        $provider->method('getPlaceholderMedia')->willReturn($media);

        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($provider);

        return $registry;
    }
}
