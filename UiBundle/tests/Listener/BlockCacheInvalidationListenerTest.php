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
}
