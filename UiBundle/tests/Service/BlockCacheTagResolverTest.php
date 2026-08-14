<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockCacheTagResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockCacheTagResolverTest extends TestCase
{
    private function createRegistry(): BlockRegistry
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = new BlockRegistry($translator);
        $registry->register('article', 'label.article', 'FormClass', 'article.html.twig', cacheable: true);
        $registry->register('form', 'label.form', 'FormClass', 'form.html.twig', cacheable: false);
        $registry->register('collection', 'label.collection', 'FormClass', 'collection.html.twig', cacheable: true);
        $registry->register('section_cards', 'label.section_cards', 'FormClass', 'cards.html.twig', cacheable: true, container: true);
        $registry->register('flex_columns', 'label.flex_columns', 'FormClass', 'columns.html.twig', cacheable: true, container: true);

        return $registry;
    }

    private function createBlock(string $kind, ?int $id): Block
    {
        $block = new Block()->setKind($kind);
        if (null !== $id) {
            new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);
        }

        return $block;
    }

    // The per-kind flag still decides first: a form embeds its own csrf token, and no tag would make its html reusable
    public function testANonCacheableKindResolvesToNull(): void
    {
        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertNull($resolver->resolve($this->createBlock('form', 3)));
    }

    // A kind no satellite bundle registers any more: its row outlives its own kind, and there is nothing to render, let alone to cache
    public function testAnUnknownKindResolvesToNull(): void
    {
        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertNull($resolver->resolve($this->createBlock('dropped_kind', 3)));
    }

    public function testAPlainCacheableKindResolvesToItsRegisteredExtraTags(): void
    {
        $cacheTagRegistry = new BlockCacheTagRegistry();
        $cacheTagRegistry->addProvider($this->createProvider(['article' => static fn (Block $block): array => ['page_5']]));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), $cacheTagRegistry);

        $this->assertSame(['page_5'], $resolver->resolve($this->createBlock('article', 3)));
    }

    // The per-instance veto: "cacheable" is declared once per kind, while a "collection" whose source can't say when its items change has to be rendered live - block by block, not kind by kind
    public function testAResolverReturningNullVetoesThatBlockAlone(): void
    {
        $cacheTagRegistry = new BlockCacheTagRegistry();
        $cacheTagRegistry->addProvider($this->createProvider([
            'collection' => static fn (Block $block): ?array => 5 === $block->getId() ? null : ['guild_character'],
        ]));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), $cacheTagRegistry);

        $this->assertNull($resolver->resolve($this->createBlock('collection', 5)));
        $this->assertSame(['guild_character'], $resolver->resolve($this->createBlock('collection', 6)));
    }

    // A container's entry holds its slots' html verbatim, so editing one of them has to reach it - which is what carrying their own "block_{id}" does
    public function testAContainerCarriesItsSlotsOwnTags(): void
    {
        $container = $this->createBlock('section_cards', 10);
        $container->addSlot($this->createBlock('article', 11));
        $container->addSlot($this->createBlock('article', 12));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertSame(['block_11', 'block_12'], $resolver->resolve($container));
    }

    // Recursion, a nested container inlining its own slots the same way
    public function testANestedContainerContributesItsOwnSlotsTagsToo(): void
    {
        $inner = $this->createBlock('flex_columns', 21);
        $inner->addSlot($this->createBlock('article', 22));

        $container = $this->createBlock('section_cards', 20);
        $container->addSlot($inner);

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertSame(['block_21', 'block_22'], $resolver->resolve($container));
    }

    // One slot that cannot be cached is enough: its output would freeze inside the container's own entry, where nothing invalidates it
    public function testAContainerHoldingANonCacheableSlotResolvesToNull(): void
    {
        $container = $this->createBlock('section_cards', 30);
        $container->addSlot($this->createBlock('article', 31));
        $container->addSlot($this->createBlock('form', 32));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertNull($resolver->resolve($container));
    }

    // A slot vetoed for its own instance takes the container down just the same, the container holding what that slot renders
    public function testAContainerHoldingAVetoedSlotResolvesToNull(): void
    {
        $cacheTagRegistry = new BlockCacheTagRegistry();
        $cacheTagRegistry->addProvider($this->createProvider(['collection' => static fn (Block $block): ?array => null]));

        $container = $this->createBlock('section_cards', 40);
        $container->addSlot($this->createBlock('collection', 41));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), $cacheTagRegistry);

        $this->assertNull($resolver->resolve($container));
    }

    // A slot still to be flushed has no id, so there would be no tag to reach its half of the container's html with
    public function testAContainerHoldingASlotWithoutAnIdResolvesToNull(): void
    {
        $container = $this->createBlock('section_cards', 50);
        $container->addSlot($this->createBlock('article', null));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertNull($resolver->resolve($container));
    }

    // A slot saved without a kind renders as nothing at all, so it puts nothing into the container's html either - skipped rather than treated as uncacheable
    public function testASlotWithNoKindIsSkippedRatherThanVetoingTheContainer(): void
    {
        $container = $this->createBlock('section_cards', 60);
        $container->addSlot(new Block());
        $container->addSlot($this->createBlock('article', 61));

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertSame(['block_61'], $resolver->resolve($container));
    }

    // Guarded rather than trusted, same as BlockCacheInvalidationListener::tagsUpTheChain(): a container put back under itself would otherwise spin here until the fatal, and a 500 on the page holding it
    public function testASlotPointingBackAtItsOwnContainerIsSkippedRatherThanFollowed(): void
    {
        $container = $this->createBlock('section_cards', 70);
        $inner = $this->createBlock('flex_columns', 71);
        $inner->addSlot($this->createBlock('article', 72));
        $inner->addSlot($container);
        $container->addSlot($inner);

        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertSame(['block_71', 'block_72'], $resolver->resolve($container));
    }

    /** @param array<string, callable> $resolvers */
    private function createProvider(array $resolvers): BlockCacheTagProviderInterface
    {
        return new readonly class ($resolvers) implements BlockCacheTagProviderInterface {
            public function __construct(private array $resolvers)
            {
            }

            public function getCacheTagResolvers(): array
            {
                return $this->resolvers;
            }
        };
    }
}
