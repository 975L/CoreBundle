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
use c975L\UiBundle\Service\BlockCacheTagResolver;
use c975L\UiBundle\Service\BlockRenderContext;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\CspNonceProvider;
use c975L\UiBundle\Twig\BlockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;
use Twig\Extension\AttributeExtension;

class BlockExtensionTest extends TestCase
{
    private function createBlock(string $kind, ?int $id = 1): Block
    {
        $block = new Block();
        $block->setKind($kind);
        $block->setData(['title' => 'Hello']);
        if (null !== $id) {
            new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);
        }

        return $block;
    }

    // Non-cacheable kinds (e.g. embedding a form with its own CSRF token) must render fresh every time - the veto is read inside the cache callback, which renders and asks for nothing to be stored
    public function testRenderBlockRendersWithoutStoringWhenKindIsNotCacheable(): void
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

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('tag');

        $saved = true;
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($item, &$saved): string {
                $save = true;
                $html = $callback($item, $save);
                $saved = $save;

                return $html;
            });

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack([Request::create('/')]), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<p>rendered</p>', $extension->renderBlock($block));
        $this->assertFalse($saved, 'A vetoed block would otherwise be stored under its own key and served to everyone afterwards.');
    }

    // What the translator does on a single-language site, which is the case of every test here: it hands the values back as they are
    private function createContentTranslator(): ContentTranslator
    {
        $translator = $this->createStub(ContentTranslator::class);
        $translator->method('translate')->willReturnArgument(2);

        return $translator;
    }

    // Reading the translations of a container walks the very slot subtree the tags were resolved from, so it belongs inside the miss callback - asked before the get(), a page whose blocks are all cached would pay a query per container for a language it already holds rendered
    public function testTheTranslationsAreNotReadOnAHit(): void
    {
        $block = $this->createBlock('flex_columns', 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn('<div>from the cache</div>');

        $translator = $this->createMock(ContentTranslator::class);
        $translator->expects($this->never())->method('preloadBlocks');

        $extension = new BlockExtension($registry, $this->createStub(Environment::class), $cache, new RequestStack([Request::create('/')]), $this->createStub(BlockCacheTagResolver::class), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $translator);

        $this->assertSame('<div>from the cache</div>', $extension->renderBlock($block));
    }

    // And read on a miss, for the block being rendered and everything it holds: one query for the subtree, where each slot asking for itself would run one apiece
    public function testTheTranslationsAreReadOnAMissForTheWholeSubtree(): void
    {
        $block = $this->createBlock('contact', 7);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getTemplate')->willReturn('contact.html.twig');
        $registry->method('getTranslatable')->willReturn(['title']);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<p>rendered</p>');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback): string {
                $save = true;

                return $callback($this->createStub(ItemInterface::class), $save);
            });

        $translator = $this->createMock(ContentTranslator::class);
        $translator->expects($this->once())->method('preloadBlocks')->with([$block]);
        $translator->method('translate')->willReturnArgument(2);

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack([Request::create('/')]), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $translator);

        $this->assertSame('<p>rendered</p>', $extension->renderBlock($block));
    }

    // The tags of a container are its whole slot subtree, hydrated from the database to be computed - asking for them before the get() would pay that price on every hit, where the entry is already there
    public function testTheCacheTagsAreNotResolvedOnAHit(): void
    {
        $block = $this->createBlock('flex_columns', 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);

        $resolver = $this->createMock(BlockCacheTagResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn('<div>from the cache</div>');

        $extension = new BlockExtension($registry, $this->createStub(Environment::class), $cache, new RequestStack([Request::create('/')]), $resolver, new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<div>from the cache</div>', $extension->renderBlock($block));
    }

    // A slot saved before a kind was picked has none, and must render as nothing instead of crashing
    public function testRenderBlockReturnsEmptyStringWhenBlockHasNoKind(): void
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 1);

        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('isCacheable');
        $registry->expects($this->never())->method('getTemplate');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('', $extension->renderBlock($block));
    }

    // A block set aside by its editor stays on the page with everything it holds, and simply renders nothing at all - the cache is never even reached, so the toggle takes effect with no entry to invalidate
    public function testRenderBlockReturnsEmptyStringWhenBlockIsHidden(): void
    {
        $block = $this->createBlock('text');
        $block->setHidden(true);

        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->never())->method('has');
        $registry->expects($this->never())->method('getTemplate');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack([Request::create('/')]), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('', $extension->renderBlock($block));
    }

    // A row outlives its own kind (a satellite bundle removed, a kind dropped): every registry lookup would throw "Unknown block" and 500 the whole page, so the block is skipped exactly like a kindless one
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

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

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

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<article>fresh</article>', $extension->renderBlock($block));
    }

    // The site's own classes are wrapped around the kind's output rather than passed into its template, so a kind supports the field as soon as its FormType offers it
    public function testRenderBlockWrapsTheBlockInTheSiteClassesItWasGiven(): void
    {
        $block = $this->createUncachedBlock(['cssClasses' => 'big-text accent']);

        $this->assertSame('<div class="big-text accent"><p>copy</p></div>', $this->createExtensionRendering('<p>copy</p>')->renderBlock($block));
    }

    // The field is free text, and block data also reaches the page through an import: anything that is not a plain class name is dropped rather than escaped into the attribute, and a name typed twice lands once
    public function testRenderBlockKeepsOnlyValidClassNames(): void
    {
        $block = $this->createUncachedBlock(['cssClasses' => '  accent  a" onclick="x  9lives accent  ']);

        $this->assertSame('<div class="accent"><p>copy</p></div>', $this->createExtensionRendering('<p>copy</p>')->renderBlock($block));
    }

    // No wrapper at all rather than an empty one, a block of a kind not offering the field included: the extra div would otherwise become the flex/grid item the layout around it addresses
    public function testRenderBlockAddsNoWrapperWhenNoSiteClassIsStored(): void
    {
        $block = $this->createUncachedBlock(['cssClasses' => '   ']);

        $this->assertSame('<p>copy</p>', $this->createExtensionRendering('<p>copy</p>')->renderBlock($block));
    }

    // No id, so the render goes straight through without the cache getting in the way of what is being asserted
    private function createUncachedBlock(array $data): Block
    {
        $block = new Block();
        $block->setKind('readmore');
        $block->setData($data);

        return $block;
    }

    private function createExtensionRendering(string $html): BlockExtension
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getTemplate')->willReturn('readmore.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn($html);

        return new BlockExtension($registry, $twig, $this->createStub(TagAwareCacheInterface::class), new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());
    }

    // anchor_id is computed once here instead of every "Page sections" adapter template repeating its own "{{ anchor ~ '-' ~ block.id }}" - the trailing block id keeps two blocks of the same kind (or the same title/anchor reused elsewhere) on the same page from colliding on the same HTML id
    public function testRenderBlockComputesAnchorIdFromTheBlocksAnchorAndId(): void
    {
        $block = new Block();
        $block->setKind('hero');
        $block->setData(['title' => 'Hello', 'anchor' => 'services']);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 42);

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

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

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

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

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

        $request = Request::create('/');
        $request->setLocale('en');
        $requestStack = new RequestStack([$request]);

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('tag')->with(['block_42', BlockCacheInvalidator::CACHE_TAG_ALL]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('block_render_42_en', $this->isCallable())
            ->willReturnCallback(function (string $key, callable $callback) use ($item): string {
                $save = true;

                return $callback($item, $save);
            });

        $extension = new BlockExtension($registry, $twig, $cache, $requestStack, new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

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
            ->willReturnCallback(function (string $key, callable $callback) use ($item): string {
                $save = true;

                return $callback($item, $save);
            });

        $requestStack = new RequestStack([Request::create('/')]);

        $extension = new BlockExtension($registry, $twig, $cache, $requestStack, new BlockCacheTagResolver($registry, $cacheTagRegistry), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $extension->renderBlock($block);
    }

    // A never-persisted block whose caller names it itself (see CollectionRuntime, whose items are transient by design but identified by their source's own slug) - cached under that key, with the caller's own tags and no "block_{id}" it has none of
    public function testATransientBlockIsCachedUnderTheKeyItsCallerHandsIn(): void
    {
        $block = $this->createBlock('collection_item', null);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->method('getTemplate')->willReturn('collection_item.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<div>card</div>');

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())
            ->method('tag')
            ->with([BlockCacheInvalidator::CACHE_TAG_ALL, 'guild_character']);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('collection_item_abc_en', $this->isCallable())
            ->willReturnCallback(function (string $key, callable $callback) use ($item): string {
                $save = true;

                return $callback($item, $save);
            });

        $request = Request::create('/');
        $request->setLocale('en');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack([$request]), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<div>card</div>', $extension->renderBlock($block, 'collection_item_abc', ['guild_character']));
    }

    // An editor's preview has to show what was just saved, and its own html is not the public one - see BlockRenderContext, armed by SiteBundle's PageController::preview()
    public function testNothingIsReadFromNorWrittenToTheCacheWhileTheRenderContextDisablesIt(): void
    {
        $block = $this->createBlock('article', 42);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(true);
        $registry->method('getTemplate')->willReturn('article.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<article>fresh</article>');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $renderContext = new BlockRenderContext();
        $renderContext->disableCache();

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack([Request::create('/')]), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), $renderContext, $this->createContentTranslator());

        $this->assertSame('<article>fresh</article>', $extension->renderBlock($block));
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

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $this->createStub(CspNonceProvider::class), new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('content', $extension->renderBlock($block));
    }

    public function testGetFunctionsRegistersRenderBlockAsHtmlSafe(): void
    {
        $functions = [];
        foreach (new AttributeExtension(BlockExtension::class)->getFunctions() as $function) {
            $functions[$function->getName()] = $function;
        }

        // Indexed by name rather than read in order: the attributes are collected in the methods' declaration order, which is no part of the contract
        $names = array_keys($functions);
        sort($names);
        $this->assertSame(['block_edit_urls', 'render_block'], $names);
        $this->assertSame(['html'], $functions['render_block']->getSafe(new \Twig\Node\TextNode('', 0)));
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
            $this->createStub(BlockCacheTagResolver::class),
            $registry,
            $this->createStub(CspNonceProvider::class),
            new BlockRenderContext(),
            $this->createContentTranslator()
        );

        $this->assertSame([5 => '/admin/edit'], $extension->getBlockEditUrls([$block]));
    }

    // The rendered html is cached verbatim, so a block writing a real nonce would freeze it into the entry and match nothing on every later request. It writes a marker instead, swapped here for the nonce of the response actually being built
    public function testTheNonceMarkerIsReplacedByTheResponsesOwnNonce(): void
    {
        $extension = $this->extensionRendering('<style data-ui-nonce>#a{color:red}</style>', 'abc123', $this->once());

        $this->assertSame('<style nonce="abc123">#a{color:red}</style>', $extension->renderBlock($this->createBlock('banner', null)), 'The marker survives into the response, so a nonced style-src drops the element.');
    }

    // A block rendered outside the cache (no id here, but a kind the registry declares uncacheable does the same) carries the very same marker: an early return skipping the substitution would ship it raw
    public function testTheMarkerIsAlsoReplacedOnABlockThatIsNeverCached(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(false);
        $registry->method('getTemplate')->willReturn('banner.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<style data-ui-nonce>#a{color:red}</style>');

        $nonces = $this->createMock(CspNonceProvider::class);
        $nonces->expects($this->once())->method('styleNonce')->willReturn('def456');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $extension = new BlockExtension($registry, $twig, $cache, new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $nonces, new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<style nonce="def456">#a{color:red}</style>', $extension->renderBlock($this->createBlock('banner', 7)));
    }

    // A slot's html is stored verbatim in its container's cache entry, so a nonce substituted while rendering the slot would freeze into that entry and match nothing on every later request - the marker is left for the outermost render, the only one that happens on every request
    public function testTheMarkerOfASlotIsLeftForTheOutermostRender(): void
    {
        $slot = $this->createBlock('banner', 8);
        $container = $this->createBlock('flex_columns', 7);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isCacheable')->willReturn(false);
        $registry->method('getTemplate')->willReturn('block.html.twig');

        $nonces = $this->createMock(CspNonceProvider::class);
        $nonces->expects($this->once())->method('styleNonce')->willReturn('abc123');

        $extension = null;
        $slotHtml = null;

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context) use ($slot, &$extension, &$slotHtml): string {
            if ($context['block'] === $slot) {
                return '<style data-ui-nonce>#a{color:red}</style>';
            }

            $slotHtml = $extension->renderBlock($slot);

            return '<div>' . $slotHtml . '</div>';
        });

        $extension = new BlockExtension($registry, $twig, $this->createStub(TagAwareCacheInterface::class), new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $nonces, new BlockRenderContext(), $this->createContentTranslator());

        $this->assertSame('<div><style nonce="abc123">#a{color:red}</style></div>', $extension->renderBlock($container));
        $this->assertSame('<style data-ui-nonce>#a{color:red}</style>', $slotHtml, 'What goes into the container is what its cache entry keeps, so it has to still hold the marker.');
    }

    // Every other block pays nothing for the feature: no marker, no nonce asked for, and asking is what would put one in the response's style-src
    public function testABlockWithoutTheMarkerNeverAsksForANonce(): void
    {
        $extension = $this->extensionRendering('<p>plain</p>', 'unused', $this->never());

        $this->assertSame('<p>plain</p>', $extension->renderBlock($this->createBlock('article', null)));
    }

    // A site with no csp section has no nonce to write: the marker goes away entirely rather than leaving a nonce="" no policy ever matches (see CspNonceProvider, wired without a listener there)
    public function testTheMarkerIsDroppedWhenThereIsNoNonce(): void
    {
        $extension = $this->extensionRendering('<style data-ui-nonce>#a{color:red}</style>', '', $this->once());

        $this->assertSame('<style>#a{color:red}</style>', $extension->renderBlock($this->createBlock('banner', null)));
    }

    // The substitution is scoped to a <style> opening tag: the plain string replacement it used to be also reached the rich text a block renders raw, so the string pasted into a Trix field came back out as a real nonce attribute
    public function testTheMarkerIsOnlyReplacedOnAStyleTag(): void
    {
        $html = '<style data-ui-nonce>#a{color:red}</style><div class="flip-card-text"><span data-ui-nonce>pasted</span>data-ui-nonce</div>';
        $extension = $this->extensionRendering($html, 'abc123', $this->once());

        $this->assertSame(
            '<style nonce="abc123">#a{color:red}</style><div class="flip-card-text"><span data-ui-nonce>pasted</span>data-ui-nonce</div>',
            $extension->renderBlock($this->createBlock('banner', null)),
            'The marker is replaced wherever it sits, so a rich text field is a way to have any element nonced.'
        );
    }

    private function extensionRendering(string $rendered, string $nonce, $nonceCalls): BlockExtension
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getTemplate')->willReturn('block.html.twig');

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn($rendered);

        $nonces = $this->createMock(CspNonceProvider::class);
        $nonces->expects($nonceCalls)->method('styleNonce')->willReturn($nonce);

        return new BlockExtension($registry, $twig, $this->createStub(TagAwareCacheInterface::class), new RequestStack(), new BlockCacheTagResolver($registry, new BlockCacheTagRegistry()), new BlockEditUrlRegistry(), $nonces, new BlockRenderContext(), $this->createContentTranslator());
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
            $this->createStub(BlockCacheTagResolver::class),
            $registry,
            $this->createStub(CspNonceProvider::class),
            new BlockRenderContext(),
            $this->createContentTranslator()
        );

        $extension->getBlockEditUrls((function () use ($block) {
            yield $block;
        })());
    }
}
