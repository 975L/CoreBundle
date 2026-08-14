<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\BlockCacheInvalidationListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheInvalidationListenerTest extends TestCase
{
    private function createEntityManager(?UnitOfWork $unitOfWork = null): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        if (null !== $unitOfWork) {
            $em->method('getUnitOfWork')->willReturn($unitOfWork);
        }

        return $em;
    }

    // A brand new Media attached to an already-cached Block (e.g. adding a slide to an existing Slider) is an INSERT - postPersist is the only Doctrine event that fires for it, postUpdate never does, which used to leave the block's cached render silently missing it
    public function testPostPersistInvalidatesTheOwningBlockTagForANewMedia(): void
    {
        $block = $this->createConfiguredStub(Block::class, ['getId' => 9]);
        $media = new Media();
        $media->setBlock($block);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_9']);

        new BlockCacheInvalidationListener($cache)
            ->postPersist(new PostPersistEventArgs($media, $this->createEntityManager()));
    }

    public function testPostUpdateInvalidatesTheBlockOwnTag(): void
    {
        $block = $this->createConfiguredStub(Block::class, ['getId' => 42]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_42']);

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs($block, $this->createEntityManager()));
    }

    // Media::getBlock() already returns null by the time the listener fires (PHP-side removal runs before flush) - the original, pre-removal reference is only found via the unit of work snapshot
    public function testPreRemoveResolvesBlockIdFromUnitOfWorkSnapshotWhenMediaNoLongerReferencesItsBlock(): void
    {
        $block = $this->createConfiguredStub(Block::class, ['getId' => 7]);
        $media = new Media();

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['block' => $block]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_7']);

        new BlockCacheInvalidationListener($cache)
            ->preRemove(new PreRemoveEventArgs($media, $this->createEntityManager($unitOfWork)));
    }

    public function testPreRemoveUsesTheMediaLiveBlockReferenceWhenStillPresent(): void
    {
        $block = $this->createConfiguredStub(Block::class, ['getId' => 3]);
        $media = new Media();
        $media->setBlock($block);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_3']);

        new BlockCacheInvalidationListener($cache)
            ->preRemove(new PreRemoveEventArgs($media, $this->createEntityManager()));
    }

    public function testInvalidateIsSkippedWhenNoBlockCanBeResolved(): void
    {
        $media = new Media();

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new BlockCacheInvalidationListener($cache)
            ->preRemove(new PreRemoveEventArgs($media, $this->createEntityManager($unitOfWork)));
    }

    public function testInvalidateIsSkippedForEntitiesThatAreNeitherBlockNorMedia(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs(new \stdClass(), $this->createEntityManager()));
    }

    // Singleton-role Media (logo, favicon...) is never attached to a Block (see Media::$block's own comment) - it needs its own "media_singletons" tag instead of "block_{id}", since MediaExtension caches these across requests separately (see MediaExtension::preloadSingletonRoles())
    public function testPostUpdateInvalidatesMediaSingletonsTagForASingletonRoleMedia(): void
    {
        $media = new Media()->setRole('logo');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['media_singletons']);

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs($media, $this->createEntityManager($unitOfWork)));
    }

    // A repeatable role (e.g. "error-image") isn't a singleton role (see Media::SINGLETON_ROLES) and isn't attached to a Block either - nothing to invalidate for it here
    public function testInvalidateSkipsMediaSingletonsTagForARepeatableRole(): void
    {
        $media = new Media()->setRole('error-image');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs($media, $this->createEntityManager($unitOfWork)));
    }

    // A container's cached html holds its slots' verbatim (see BlockCacheTagResolver), so a slot that changed leaves every container above it holding stale output
    public function testPostUpdateInvalidatesEveryContainerAboveTheChangedSlot(): void
    {
        $outer = $this->createBlockWithId(100);
        $inner = $this->createBlockWithId(101)->setParentBlock($outer);
        $slot = $this->createBlockWithId(102)->setParentBlock($inner);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_102', 'block_101', 'block_100']);

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs($slot, $this->createEntityManager()));
    }

    // Adding a slot is what makes the propagation necessary rather than merely tidy: the new row's id was never a tag of its container's entry, so nothing else would ever reach it
    public function testPostPersistInvalidatesTheContainerOfABrandNewSlot(): void
    {
        $container = $this->createBlockWithId(200);
        $slot = $this->createBlockWithId(201)->setParentBlock($container);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_201', 'block_200']);

        new BlockCacheInvalidationListener($cache)
            ->postPersist(new PostPersistEventArgs($slot, $this->createEntityManager()));
    }

    // Block::removeSlot() nulls the owning side well before flush() runs, exactly as removeMedia() does - the pre-flush snapshot is what still holds the container the slot was taken out of
    public function testPreRemoveResolvesTheContainerFromTheUnitOfWorkSnapshot(): void
    {
        $container = $this->createBlockWithId(300);
        $slot = $this->createBlockWithId(301);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(
            static fn (object $entity): array => $entity === $slot ? ['parentBlock' => $container] : []
        );

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_301', 'block_300']);

        new BlockCacheInvalidationListener($cache)
            ->preRemove(new PreRemoveEventArgs($slot, $this->createEntityManager($unitOfWork)));
    }

    // A container and one of its own slots pointing back at each other would otherwise spin forever up the chain
    public function testACycleUpTheChainStopsInsteadOfLoopingForever(): void
    {
        $first = $this->createBlockWithId(400);
        $second = $this->createBlockWithId(401)->setParentBlock($first);
        $first->setParentBlock($second);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['block_401', 'block_400']);

        new BlockCacheInvalidationListener($cache)
            ->postUpdate(new PostUpdateEventArgs($second, $this->createEntityManager()));
    }

    private function createBlockWithId(int $id): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }
}
