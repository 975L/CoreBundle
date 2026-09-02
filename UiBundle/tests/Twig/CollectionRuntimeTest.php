<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Twig\BlockExtension;
use c975L\UiBundle\Twig\CollectionRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class CollectionRuntimeTest extends TestCase
{
    private function createRuntime(
        CollectionSourceRegistry $sourceRegistry,
        BlockExtension $blockExtension,
        ?Request $request = null,
        ?Environment $twig = null,
    ): CollectionRuntime {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $params) => 'page_preview' === $route
                ? '/pages/' . $params['page'] . '/preview'
                : '/pages/' . $params['page']
        );

        return new CollectionRuntime($sourceRegistry, $blockExtension, $requestStack, $twig ?? $this->createStub(Environment::class), $urlGenerator);
    }

    // Each CollectionItem becomes a never-persisted "collection_item" Block, rendered through the exact same render_block() pipeline as a real, editor-placed block
    public function testRenderItemsBuildsACollectionItemBlockPerItemAndRendersItThroughBlockExtension(): void
    {
        $item = new CollectionItem('Project A', 'A short text', '/uploads/project-a.webp', '/projets/a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createMock(BlockExtension::class);
        $blockExtension->expects($this->once())
            ->method('renderBlock')
            ->willReturnCallback(function (Block $block) use (&$renderedBlock) {
                $renderedBlock = $block;

                return '<div class="card">Project A</div>';
            });

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $result = $runtime->renderItems('site.collection.projects', 6, null);

        $this->assertSame(['<div class="card">Project A</div>'], $result);
        $this->assertSame('collection_item', $renderedBlock->getKind());
        $this->assertSame([
            'title' => 'Project A',
            'content' => 'A short text',
            'url' => '/projets/a',
            'imageUrl' => '/uploads/project-a.webp',
            'buttonLabel' => null,
            'buttonIcon' => null,
            'detailUrl' => null,
            'variant' => null,
        ], $renderedBlock->getData());
    }

    // The whole point of the random order: asking the source for three items straight away would shuffle the same three on every visit, so the source is asked for everything it holds and the limit is applied after the draw
    public function testRenderItemsAsksTheSourceForEverythingBeforeDrawingAtRandom(): void
    {
        $items = array_map(
            static fn (int $index): CollectionItem => new CollectionItem('Item ' . $index, slug: 'item-' . $index),
            range(1, 8)
        );

        $askedLimit = 'untouched';
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturnCallback(function (string $source, ?int $limit) use ($items, &$askedLimit): array {
            $askedLimit = $limit;

            return $items;
        });

        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            static fn (Block $block): string => '<div>' . $block->getData()['title'] . '</div>'
        );

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $rendered = $runtime->renderItems('guild.characters', 3, null, null, 'random');

        $this->assertNull($askedLimit);
        $this->assertCount(3, $rendered);
        $this->assertCount(3, array_unique($rendered));
        foreach ($rendered as $html) {
            $this->assertMatchesRegularExpression('#^<div>Item [1-8]</div>$#', $html);
        }
    }

    // No limit and a random order: the source is drawn whole, not cut
    public function testRenderItemsWithNoLimitDrawsTheWholeSource(): void
    {
        $items = array_map(
            static fn (int $index): CollectionItem => new CollectionItem('Item ' . $index, slug: 'item-' . $index),
            range(1, 5)
        );

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn($items);

        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            static fn (Block $block): string => '<div>' . $block->getData()['title'] . '</div>'
        );

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $rendered = $runtime->renderItems('guild.characters', null, null, null, 'random');

        $this->assertCount(5, $rendered);
        $this->assertCount(5, array_unique($rendered));
    }

    // Anything but "random" is the source's own order, and the limit stays the source's business - the very query it runs
    public function testRenderItemsLeavesTheLimitToTheSourceInTheDefaultOrder(): void
    {
        $askedLimit = 'untouched';
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturnCallback(function (string $source, ?int $limit) use (&$askedLimit): array {
            $askedLimit = $limit;

            return [new CollectionItem('Item 1', slug: 'item-1')];
        });

        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturn('');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $runtime->renderItems('guild.characters', 3, null, null, '');

        $this->assertSame(3, $askedLimit);
    }

    // Whatever else a source has to say about its item travels through to the rendered block, which is what spares this model a property per prop the Card accepts
    public function testRenderItemsMergesTheItemsOwnDataIntoTheBlock(): void
    {
        $item = new CollectionItem(
            title: 'Project A',
            data: ['eyebrow' => 'Seigneur · Guerrier', 'rating' => 5, 'stats' => [['label' => 'Force', 'value' => '1 000']]],
        );

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $runtime->renderItems('site.collection.projects', null, null);

        $data = $renderedBlock->getData();
        $this->assertSame('Seigneur · Guerrier', $data['eyebrow']);
        $this->assertSame(5, $data['rating']);
        $this->assertSame([['label' => 'Force', 'value' => '1 000']], $data['stats']);
    }

    // A source cannot take the place of what this runtime computes: a "detailUrl" or a "variant" of its own would unlink the item or send it to another template
    public function testRenderItemsKeepsItsOwnKeysOverTheItemsData(): void
    {
        $item = new CollectionItem(
            title: 'Project A',
            slug: 'project-a',
            data: ['title' => 'Forged', 'detailUrl' => '/elsewhere', 'variant' => 'portfolio'],
        );

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $request = new Request();
        $request->attributes->set('page', 'projects');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, $request);
        $runtime->renderItems('site.collection.projects', null, 'project-detail');

        $data = $renderedBlock->getData();
        $this->assertSame('Project A', $data['title']);
        $this->assertSame('/pages/projects/project-a', $data['detailUrl']);
        $this->assertNull($data['variant']);
    }

    // detailPage configured, item has a slug, and a "page" route parameter is available: the item's detail link is built from the current page's own slug, never the detail Page's own slug
    public function testRenderItemsBuildsDetailUrlWhenDetailPageAndItemSlugAreBothSet(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $request = new Request();
        $request->attributes->set('page', 'projects');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, $request);
        $runtime->renderItems('site.collection.projects', null, 'project-detail');

        $this->assertSame('/pages/projects/project-a', $renderedBlock->getData()['detailUrl']);
    }

    // The parent page itself is being previewed (page_preview route): the detail link must follow it onto /preview too, so an editor can reach an unpublished detail before going live
    public function testRenderItemsBuildsPreviewDetailUrlWhenParentPageIsBeingPreviewed(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $request = new Request();
        $request->attributes->set('page', 'projects');
        $request->attributes->set('_route', 'page_preview');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, $request);
        $runtime->renderItems('site.collection.projects', null, 'project-detail');

        $this->assertSame('/pages/projects/project-a/preview', $renderedBlock->getData()['detailUrl']);
    }

    // No detailPage configured on the "collection" block: no link is built even if the item has a slug
    public function testRenderItemsLeavesDetailUrlNullWhenDetailPageIsNotSet(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $request = new Request();
        $request->attributes->set('page', 'projects');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, $request);
        $runtime->renderItems('site.collection.projects', null, null);

        $this->assertNull($renderedBlock->getData()['detailUrl']);
    }

    // detailPage configured but the item's own source never supplies a slug: no link either, same tolerant degradation as a source with no "detail" capability at all
    public function testRenderItemsLeavesDetailUrlNullWhenItemHasNoSlug(): void
    {
        $item = new CollectionItem(title: 'Project A');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock) {
            $renderedBlock = $block;

            return '';
        });

        $request = new Request();
        $request->attributes->set('page', 'projects');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, $request);
        $runtime->renderItems('site.collection.projects', null, 'project-detail');

        $this->assertNull($renderedBlock->getData()['detailUrl']);
    }

    public function testRenderItemsReturnsEmptyArrayWhenSourceHasNoItems(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([]);

        $runtime = $this->createRuntime($sourceRegistry, $this->createStub(BlockExtension::class));

        $this->assertSame([], $runtime->renderItems('site.collection.projects', null, null));
    }

    // The item is named after its source and its own slug, never after the block listing it: one character's card is a single entry, hit by the "collection" block on one page and by the one on another
    public function testRenderItemsCachesEachItemUnderTheItemsOwnIdentity(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('cacheTags')->willReturn(['site_collection_4']);

        $keys = [];
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            function (Block $block, ?string $cacheKey = null, array $cacheTags = []) use (&$keys) {
                $keys[] = [$cacheKey, $cacheTags];

                return '';
            }
        );

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $runtime->renderItems('site.collection.projects', null, null);

        $this->assertStringStartsWith('collection_item_', $keys[0][0]);
        $this->assertSame(['site_collection_4'], $keys[0][1]);

        // The very same item under a second block: same key, hence a single shared entry
        $runtime->renderItems('site.collection.projects', null, null);
        $this->assertSame($keys[0][0], $keys[1][0]);
    }

    // The heading its title is drawn with is the block's call, not the source's: it reaches the item as plain data, exactly as a stored block would carry the editor's own
    public function testTheGivenLevelIsCarriedByTheItemsBlockData(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([new CollectionItem('Project A')]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock): string {
            $renderedBlock = $block;

            return '';
        });

        $this->createRuntime($sourceRegistry, $blockExtension)->renderItems('site.collection.projects', null, null, null, null, 'h2');

        $this->assertSame('h2', $renderedBlock->getData()['level']);
    }

    // Added and not defaulted: a source drawing a card of its own may hand back a "level" in its item's data, which a block asking for none must not blank out
    public function testASourcesOwnLevelIsKeptWhenTheBlockAsksForNone(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([new CollectionItem(title: 'Project A', data: ['level' => 'h4'])]);

        $renderedBlock = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$renderedBlock): string {
            $renderedBlock = $block;

            return '';
        });

        $this->createRuntime($sourceRegistry, $blockExtension)->renderItems('site.collection.projects', null, null);

        $this->assertSame('h4', $renderedBlock->getData()['level']);
    }

    // Same reading as the detail url below: the level is the page's decision and part of what the html varies with, so two blocks listing the same source at two levels must not share one entry
    public function testTheLevelIsPartOfTheItemsCacheKey(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('cacheTags')->willReturn(['site_collection_4']);

        $keys = [];
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block, ?string $cacheKey = null) use (&$keys): string {
            $keys[] = $cacheKey;

            return '';
        });

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension);
        $runtime->renderItems('site.collection.projects', null, null, null, null, 'h2');
        $runtime->renderItems('site.collection.projects', null, null, null, null, 'h3');

        $this->assertNotSame($keys[0], $keys[1]);
    }

    // The detail url is the one thing in an item's html the page holding the block decides, so it is part of the item's identity - the same project listed from two pages is two entries, each holding its own link
    public function testTheDetailUrlIsPartOfTheItemsCacheKey(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('cacheTags')->willReturn(['site_collection_4']);

        $keys = [];
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            function (Block $block, ?string $cacheKey = null) use (&$keys) {
                $keys[] = $cacheKey;

                return '';
            }
        );

        foreach (['projects', 'showcase'] as $page) {
            $request = new Request();
            $request->attributes->set('page', $page);
            $this->createRuntime($sourceRegistry, $blockExtension, $request)
                ->renderItems('site.collection.projects', null, 'project-detail');
        }

        $this->assertNotSame($keys[0], $keys[1]);
    }

    // A source declaring no cache tag has no way of saying when its items change: no key, hence no entry, and the item is rendered live like before
    public function testAnItemOfASourceWithNoCacheTagIsNotCached(): void
    {
        $item = new CollectionItem(title: 'Project A', slug: 'project-a');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('cacheTags')->willReturn([]);

        $key = 'untouched';
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            function (Block $block, ?string $cacheKey = null) use (&$key) {
                $key = $cacheKey;

                return '';
            }
        );

        $this->createRuntime($sourceRegistry, $blockExtension)->renderItems('site.collection.projects', null, null);

        $this->assertNull($key);
    }

    // An item with no slug has no stable identity to be named by, whatever its source declared
    public function testAnItemWithoutASlugIsNotCached(): void
    {
        $item = new CollectionItem(title: 'Project A');

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('cacheTags')->willReturn(['site_collection_4']);

        $key = 'untouched';
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(
            function (Block $block, ?string $cacheKey = null) use (&$key) {
                $key = $cacheKey;

                return '';
            }
        );

        $this->createRuntime($sourceRegistry, $blockExtension)->renderItems('site.collection.projects', null, null);

        $this->assertNull($key);
    }

    // A source drawing its items by a template of its own - a card this bundle never draws - hands its entity over in the item's "data", which the template reads under the name it expects
    public function testASourceNamingATemplateHasItsItemsRenderedByIt(): void
    {
        $item = new CollectionItem(title: 'Château Hurlton', slug: 'chateau-hurlton', data: ['album' => 'the entity itself']);

        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([$item]);
        $sourceRegistry->method('itemTemplate')->willReturn('components/Album/AlbumCard.html.twig');

        $rendered = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context) use (&$rendered) {
            $rendered = [$template, $context];

            return '<article class="album-card"></article>';
        });

        $blockExtension = $this->createMock(BlockExtension::class);
        $blockExtension->expects($this->never())->method('renderBlock');

        $runtime = $this->createRuntime($sourceRegistry, $blockExtension, null, $twig);
        $html = $runtime->renderItems('guild.albums', null, null);

        $this->assertSame(['<article class="album-card"></article>'], $html);
        $this->assertSame('components/Album/AlbumCard.html.twig', $rendered[0]);
        $this->assertSame('the entity itself', $rendered[1]['album']);
        $this->assertSame('Château Hurlton', $rendered[1]['title']);
    }

    // The singular of renderItems(): "first" only ever needs the head of the source, which is what the limit says
    public function testAnEntryPickedFirstOnlyAsksTheSourceForItsHead(): void
    {
        $limits = [];
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturnCallback(function (string $source, ?int $limit) use (&$limits) {
            $limits[] = $limit;

            return [new CollectionItem(title: 'Premier'), new CollectionItem(title: 'Deuxième')];
        });

        $titles = [];
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$titles) {
            $titles[] = $block->getData()['title'];

            return '';
        });

        $this->createRuntime($sourceRegistry, $blockExtension)->renderEntry('guild.albums', 'first', null);

        $this->assertSame([1], $limits);
        $this->assertSame(['Premier'], $titles);
    }

    public function testAnEntryPickedLastIsTheSourcesOwnLastItem(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([
            new CollectionItem(title: 'Premier'),
            new CollectionItem(title: 'Dernier'),
        ]);

        $title = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$title) {
            $title = $block->getData()['title'];

            return '';
        });

        $this->createRuntime($sourceRegistry, $blockExtension)->renderEntry('guild.albums', 'last', null);

        $this->assertSame('Dernier', $title);
    }

    public function testAnEntryPickedBySlugIsTheOneCarryingIt(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([
            new CollectionItem(title: 'Premier', slug: 'premier'),
            new CollectionItem(title: 'Celui-là', slug: 'celui-la'),
        ]);

        $title = null;
        $blockExtension = $this->createStub(BlockExtension::class);
        $blockExtension->method('renderBlock')->willReturnCallback(function (Block $block) use (&$title) {
            $title = $block->getData()['title'];

            return '';
        });

        $this->createRuntime($sourceRegistry, $blockExtension)->renderEntry('guild.albums', 'slug', 'celui-la');

        $this->assertSame('Celui-là', $title);
    }

    // An empty source, or a slug matching none: nothing at all rather than a heading standing over a hole, which is what the block's own template reads this empty string as
    public function testAnEntryThatNothingAnswersRendersNothing(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([new CollectionItem(title: 'Premier', slug: 'premier')]);

        $runtime = $this->createRuntime($sourceRegistry, $this->createStub(BlockExtension::class));

        $this->assertSame('', $runtime->renderEntry('guild.albums', 'slug', 'inconnu'));
    }

    public function testAnEntryOfAnEmptySourceRendersNothing(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('items')->willReturn([]);

        $runtime = $this->createRuntime($sourceRegistry, $this->createStub(BlockExtension::class));

        $this->assertSame('', $runtime->renderEntry('guild.albums', 'first', null));
    }
}
