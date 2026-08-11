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
use c975L\UiBundle\Listener\BlockRemovalListener;
use c975L\UiBundle\Tests\Entity\Trait\HasBlocksTraitStub;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

class BlockRemovalListenerTest extends TestCase
{
    // popPendingBlockRemovals() must be called exactly once per owner - once it has been drained, the queue is empty on the next call, which the listener must not confuse with "nothing to remove"
    public function testPreFlushRemovesEveryPendingBlockFromOwnersInTheIdentityMap(): void
    {
        $block = new Block();
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);
        $owner->removeBlock($block);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getIdentityMap')->willReturn([
            HasBlocksTraitStub::class => [1 => $owner],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->once())->method('remove')->with($block);

        new BlockRemovalListener()->preFlush(new PreFlushEventArgs($em));
    }

    public function testPreFlushIgnoresEntitiesThatDoNotOwnBlocks(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getIdentityMap')->willReturn([
            \stdClass::class => [1 => new \stdClass()],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->never())->method('remove');

        new BlockRemovalListener()->preFlush(new PreFlushEventArgs($em));
    }

    public function testPreFlushRemovesNothingWhenNoBlockIsPending(): void
    {
        $owner = new HasBlocksTraitStub();

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getIdentityMap')->willReturn([
            HasBlocksTraitStub::class => [1 => $owner],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->never())->method('remove');

        new BlockRemovalListener()->preFlush(new PreFlushEventArgs($em));
    }
}
