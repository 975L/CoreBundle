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
use c975L\UiBundle\Listener\OwnedBlocksCacheListener;
use c975L\UiBundle\Tests\Entity\Trait\HasBlocksTraitStub;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class OwnedBlocksCacheListenerTest extends TestCase
{
    private function flush(OwnedBlocksCacheListener $listener, array $collections = [], array $updates = []): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn($collections);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
    }

    // A block added, removed or moved only writes the join table: the owner's collection is the one trace of it
    public function testAnOwnersRunOfBlocksChangedEmptiesIt(): void
    {
        // Final, so a real one with its owner set the way the unit of work does
        $collection = new PersistentCollection($this->createStub(EntityManagerInterface::class), new ClassMetadata(Block::class), new ArrayCollection());
        new \ReflectionProperty(PersistentCollection::class, 'owner')->setValue($collection, new HasBlocksTraitStub());

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['owned_blocks_hasblockstraitstub_']);

        $this->flush(new OwnedBlocksCacheListener($cache), [$collection]);
    }

    public function testAnOwnerSavedEmptiesIt(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['owned_blocks_hasblockstraitstub_']);

        $this->flush(new OwnedBlocksCacheListener($cache), updates: [new HasBlocksTraitStub()]);
    }

    // A block edited in place reaches the entry through its own tag, nothing to add here
    public function testNothingElseEmptiesAnything(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $this->flush(new OwnedBlocksCacheListener($cache), updates: [new Block()]);
    }
}
