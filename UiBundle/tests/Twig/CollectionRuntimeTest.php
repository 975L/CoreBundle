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

class CollectionRuntimeTest extends TestCase
{
    private function createRuntime(
        CollectionSourceRegistry $sourceRegistry,
        BlockExtension $blockExtension,
        ?Request $request = null,
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

        return new CollectionRuntime($sourceRegistry, $blockExtension, $requestStack, $urlGenerator);
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
}
