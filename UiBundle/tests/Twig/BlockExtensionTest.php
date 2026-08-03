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
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Registry\BlockEditUrlRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use c975L\UiBundle\Twig\BlockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;

class BlockExtensionTest extends TestCase
{
    private function createBlock(string $kind, ?int $id = 1): Block
    {
        $block = new Block();
        $block->setKind($kind);
        $block->setData(['title' => 'Hello']);
        if (null !== $id) {
            (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);
        }

        return $block;
    }

    // Non-cacheable kinds (e.g. embedding a form with its own CSRF token) must render fresh every time, bypassing the cache pool entirely
    public function testRenderBlockRendersDirectlyWithoutCachingWhenKindIsNotCacheable(): void
    {
        $block = $this->createBlock('contact_form');

        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->once())->method('isCacheable')->with('contact_form')->willReturn(false);
        $registry->expects($this->once())->method('getTemplate')->with('contact_form')->willReturn('contact.html.twig');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('contact.html.twig', ['block' => $block, 'anchor_id' => '', 'title' => 'Hello'])
            ->willReturn('<p>rendered</p>');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('<p>rendered</p>', $extension->renderBlock($block));
    }

    // A slot saved before a kind was picked has none, and must render as nothing instead of crashing
    public function testRenderBlockReturnsEmptyStringWhenBlockHasNoKind(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 1);

        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('isCacheable');
        $registry->expects($this->never())->method('getTemplate');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('', $extension->renderBlock($block));
    }

    // A row outlives its own kind (a satellite bundle removed, a kind dropped): every registry lookup would throw
    // "Unknown block" and 500 the whole page, so the block is skipped exactly like a kindless one
    public function testRenderBlockReturnsEmptyStringWhenKindIsNoLongerRegistered(): void
    {
        $block = $this->createBlock('rich_snippet');

        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->once())->method('has')->with('rich_snippet')->willReturn(false);
        $registry->expects($this->never())->method('isCacheable');
        $registry->expects($this->never())->method('getTemplate');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('', $extension->renderBlock($block));
    }

    // A never-persisted block (e.g. a block showcase's in-memory fixture previews) has no id - caching it would collapse onto the same key as every other unpersisted block of a cacheable kind, silently serving one block's rendered HTML for every other one
    public function testRenderBlockRendersDirectlyWithoutCachingWhenBlockHasNoId(): void
    {
        $block = $this->createBlock('article', null);

        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->expects($this->once())->method('getTemplate')->with('article')->willReturn('article.html.twig');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('article.html.twig', ['block' => $block, 'anchor_id' => '', 'title' => 'Hello'])
            ->willReturn('<article>fresh</article>');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('<article>fresh</article>', $extension->renderBlock($block));
    }

    // anchor_id is computed once here instead of every "Page sections" adapter template repeating its own "{{ anchor ~ '-' ~ block.id }}" - the trailing block id keeps two blocks of the same kind (or the same title/anchor reused elsewhere) on the same page from colliding on the same HTML id
    public function testRenderBlockComputesAnchorIdFromTheBlocksAnchorAndId(): void
    {
        $block = new Block();
        $block->setKind('hero');
        $block->setData(['title' => 'Hello', 'anchor' => 'services']);
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(false);
        $registry->method('getTemplate')->willReturn('hero.html.twig');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('hero.html.twig', ['block' => $block, 'anchor_id' => 'services-42', 'title' => 'Hello', 'anchor' => 'services'])
            ->willReturn('<section id="services-42"></section>');

        $cache = $this->createStub(TagAwareCacheInterface::class);

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('<section id="services-42"></section>', $extension->renderBlock($block));
    }

    // A never-persisted block (e.g. a gallery fixture preview) has no id yet - the anchor still needs to render into something rather than crash, even without the trailing "-{id}" uniqueness suffix
    public function testRenderBlockComputesAnchorIdWithoutATrailingIdWhenBlockIsNeverPersisted(): void
    {
        $block = $this->createBlock('hero', null);
        $block->setData(['title' => 'Hello', 'anchor' => 'services']);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(false);
        $registry->method('getTemplate')->willReturn('hero.html.twig');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('hero.html.twig', ['block' => $block, 'anchor_id' => 'services-', 'title' => 'Hello', 'anchor' => 'services'])
            ->willReturn('<section id="services-"></section>');

        $cache = $this->createStub(TagAwareCacheInterface::class);

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('<section id="services-"></section>', $extension->renderBlock($block));
    }

    // Cacheable kinds go through the cache pool, keyed by block id and current locale
    public function testRenderBlockUsesCacheForCacheableKind(): void
    {
        $block = $this->createBlock('article', 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->method('getTemplate')->willReturn('article.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<article>cached content</article>');

        $requestStack = new RequestStack();
        $request = Request::create('/');
        $request->setLocale('en');
        $requestStack->push($request);

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('tag')->with(['block_42', BlockCacheInvalidator::CACHE_TAG_ALL]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('block_render_42_en', $this->isCallable())
            ->willReturnCallback(function (string $key, callable $callback) use ($item) {
                return $callback($item);
            });

        $extension = new BlockExtension($registry, $twig, $cache, $requestStack, new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('<article>cached content</article>', $extension->renderBlock($block));
    }

    // A kind registered with BlockCacheTagProviderInterface (e.g. articles_slider depending on another Page's blocks) gets its extra tags merged in alongside the default "block_{id}"/"blocks_all" ones
    public function testRenderBlockMergesExtraCacheTagsFromTheCacheTagRegistry(): void
    {
        $block = $this->createBlock('articles_slider', 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->method('getTemplate')->willReturn('articles_slider.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<div>slider</div>');

        $cacheTagRegistry = $this->createStub(BlockCacheTagRegistry::class);
        $cacheTagRegistry->method('getExtraTags')->willReturn(['page_5']);

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())
            ->method('tag')
            ->with(['block_42', BlockCacheInvalidator::CACHE_TAG_ALL, 'page_5']);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($item) {
                return $callback($item);
            });

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $extension = new BlockExtension($registry, $twig, $cache, $requestStack, $cacheTagRegistry, new BlockEditUrlRegistry());

        $extension->renderBlock($block);
    }

    // Without a current request (e.g. CLI/message consumer context), the render is never cached: the RequestContext then answers "http://localhost" to anything host-dependent, and that entry would afterwards be served to every visitor
    public function testRenderBlockIsNotCachedWithoutACurrentRequest(): void
    {
        $block = $this->createBlock('article', 7);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->method('getTemplate')->willReturn('article.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('content');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagRegistry(), new BlockEditUrlRegistry());

        $this->assertSame('content', $extension->renderBlock($block));
    }

    public function testGetFunctionsRegistersRenderBlockAsHtmlSafe(): void
    {
        $extension = new BlockExtension(
            $this->createStub(BlockRegistry::class),
            $this->createStub(Environment::class),
            $this->createStub(TagAwareCacheInterface::class),
            new RequestStack(),
            new BlockCacheTagRegistry(),
            new BlockEditUrlRegistry()
        );
        $functions = $extension->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertSame('render_block', $functions[0]->getName());
        $this->assertSame(['html'], $functions[0]->getSafe(new \Twig\Node\TextNode('', 0)));
        $this->assertSame('block_edit_urls', $functions[1]->getName());
    }

    // Resolved once for the whole collection, not once per block - avoids a query per block (see BlockEditUrlRegistry)
    public function testGetBlockEditUrlsDelegatesToTheRegistryForTheWholeCollection(): void
    {
        $block = $this->createBlock('article', 5);

        $registry = $this->createMock(BlockEditUrlRegistry::class);
        $registry->expects($this->once())->method('getEditUrls')->with([$block])->willReturn([5 => '/admin/edit']);

        $extension = new BlockExtension(
            $this->createStub(BlockRegistry::class),
            $this->createStub(Environment::class),
            $this->createStub(TagAwareCacheInterface::class),
            new RequestStack(),
            new BlockCacheTagRegistry(),
            $registry
        );

        $this->assertSame([5 => '/admin/edit'], $extension->getBlockEditUrls([$block]));
    }

    // Twig collections (Doctrine PersistentCollection/ArrayCollection) are iterable but not necessarily arrays
    public function testGetBlockEditUrlsAcceptsAnyIterable(): void
    {
        $block = $this->createBlock('article', 6);

        $registry = $this->createMock(BlockEditUrlRegistry::class);
        $registry->expects($this->once())->method('getEditUrls')->with([$block])->willReturn([]);

        $extension = new BlockExtension(
            $this->createStub(BlockRegistry::class),
            $this->createStub(Environment::class),
            $this->createStub(TagAwareCacheInterface::class),
            new RequestStack(),
            new BlockCacheTagRegistry(),
            $registry
        );

        $extension->getBlockEditUrls((function () use ($block) {
            yield $block;
        })());
    }
}
