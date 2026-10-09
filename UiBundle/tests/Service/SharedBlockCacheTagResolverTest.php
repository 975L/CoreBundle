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
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Service\BlockCacheTagResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// A "shared_block" pointer holds the html of the run it names: its entry has to be emptied whenever that run changes, and not kept at all when one of its blocks cannot be
class SharedBlockCacheTagResolverTest extends TestCase
{
    // The shared block's own tag (a block added or moved in it), each block's tag, and the slug's tag for a shared block created later
    public function testAPointerCarriesTheTagsOfTheRunItShows(): void
    {
        $shared = $this->createShared('contact-banner', 4, [$this->createBlock('cta_band', 21)]);

        $tags = $this->createResolver(['contact-banner' => $shared])->resolve($this->createPointer(9, 'contact-banner'));

        $this->assertSame(['shared_block_contact-banner', 'owned_blocks_sharedblock_4', 'block_21'], $tags);
    }

    // Nothing to show yet, but the pointer has to be refreshed the day that shared block is created
    public function testAPointerToAnUnknownSlugCarriesTheSlugTagAlone(): void
    {
        $this->assertSame(['shared_block_nowhere'], $this->createResolver([])->resolve($this->createPointer(9, 'nowhere')));
    }

    // A form and its csrf token in the run, and the pointer's html stops being reusable, the same rule as a container's slots
    public function testAPointerToARunHoldingAnUncacheableBlockIsNotCached(): void
    {
        $shared = $this->createShared('newsletter', 4, [$this->createBlock('cta_band', 21), $this->createBlock('form', 22)]);

        $this->assertNull($this->createResolver(['newsletter' => $shared])->resolve($this->createPointer(9, 'newsletter')));
    }

    // A shared block showing itself, through a pointer in its own run, must not spin here forever
    public function testAPointerLoopTerminates(): void
    {
        $shared = $this->createShared('loop', 4, [$this->createPointer(30, 'loop')]);

        $tags = $this->createResolver(['loop' => $shared])->resolve($this->createPointer(9, 'loop'));

        $this->assertSame(['shared_block_loop', 'owned_blocks_sharedblock_4', 'block_30', 'shared_block_loop'], $tags);
    }

    // A resolver built without the repository cannot know what a pointer shows, so it caches none
    public function testAPointerIsNotCachedWithoutTheRepository(): void
    {
        $resolver = new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry());

        $this->assertNull($resolver->resolve($this->createPointer(9, 'contact-banner')));
    }

    /** @param array<string, SharedBlock> $sharedBlocks */
    private function createResolver(array $sharedBlocks): BlockCacheTagResolver
    {
        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(static fn (string $slug): ?SharedBlock => $sharedBlocks[$slug] ?? null);

        return new BlockCacheTagResolver($this->createRegistry(), new BlockCacheTagRegistry(), $repository);
    }

    private function createRegistry(): BlockRegistry
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $registry = new BlockRegistry($translator);
        $registry->register('cta_band', 'label.cta_band', 'FormClass', 'cta.html.twig', cacheable: true);
        $registry->register('form', 'label.form', 'FormClass', 'form.html.twig', cacheable: false);
        $registry->register(SharedBlock::POINTER_KIND, 'label.shared_block', 'FormClass', 'shared.html.twig', cacheable: true);

        return $registry;
    }

    /** @param Block[] $blocks */
    private function createShared(string $slug, int $id, array $blocks): SharedBlock
    {
        $shared = new SharedBlock()->setName($slug)->setSlug($slug);
        new \ReflectionProperty(SharedBlock::class, 'id')->setValue($shared, $id);
        foreach ($blocks as $block) {
            $shared->addBlock($block);
        }

        return $shared;
    }

    private function createPointer(int $id, string $slug): Block
    {
        return $this->createBlock(SharedBlock::POINTER_KIND, $id)->setData(['slug' => $slug]);
    }

    private function createBlock(string $kind, int $id): Block
    {
        $block = new Block()->setKind($kind);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }
}
