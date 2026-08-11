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
use c975L\UiBundle\Service\BlockRelocator;
use c975L\UiBundle\Tests\Entity\Trait\HasBlocksTraitStub;
use PHPUnit\Framework\TestCase;

class BlockRelocatorTest extends TestCase
{
    public function testMovesATopLevelBlockIntoAContainersSlots(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);
        $container = new Block();

        new BlockRelocator()->relocate($block, $owner, $container);

        $this->assertFalse($owner->getBlocks()->contains($block));
        $this->assertTrue($container->getSlots()->contains($block));
        $this->assertSame($container, $block->getParentBlock());
    }

    // The moved block must never be queued for deletion (see BlockRemovalListener/HasBlocksTrait::removeBlock()) - it's still alive, just relocated
    public function testMovingATopLevelBlockDoesNotQueueItForDeletion(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);
        $container = new Block();

        new BlockRelocator()->relocate($block, $owner, $container);

        $this->assertSame([], $owner->popPendingBlockRemovals());
    }

    public function testMovesASlotBackToTopLevel(): void
    {
        $owner = new HasBlocksTraitStub();
        $container = new Block();
        $block = new Block();
        $container->addSlot($block);

        new BlockRelocator()->relocate($block, $owner, null);

        $this->assertFalse($container->getSlots()->contains($block));
        $this->assertTrue($owner->getBlocks()->contains($block));
        $this->assertNull($block->getParentBlock());
    }

    public function testMovesASlotFromOneContainerToAnother(): void
    {
        $owner = new HasBlocksTraitStub();
        $oldContainer = new Block();
        $newContainer = new Block();
        $block = new Block();
        $oldContainer->addSlot($block);

        new BlockRelocator()->relocate($block, $owner, $newContainer);

        $this->assertFalse($oldContainer->getSlots()->contains($block));
        $this->assertTrue($newContainer->getSlots()->contains($block));
        $this->assertSame($newContainer, $block->getParentBlock());
    }

    public function testAppendsTheMovedBlockAtTheEndOfTheTargetSlots(): void
    {
        $owner = new HasBlocksTraitStub();
        $container = new Block();
        $container->addSlot(new Block());
        $container->addSlot(new Block());
        $block = new Block();
        $owner->addBlock($block);

        new BlockRelocator()->relocate($block, $owner, $container);

        $this->assertSame(2, $block->getPosition());
    }

    public function testAppendsTheMovedBlockAtTheEndOfTheTargetTopLevelBlocks(): void
    {
        $owner = new HasBlocksTraitStub();
        $owner->addBlock(new Block());
        $owner->addBlock(new Block());
        $container = new Block();
        $block = new Block();
        $container->addSlot($block);

        new BlockRelocator()->relocate($block, $owner, null);

        $this->assertSame(2, $block->getPosition());
    }

    // Remaining siblings must be renumbered contiguously, else a later move collides with a stale index
    public function testRemovingASlotRenumbersRemainingSiblingsContiguously(): void
    {
        $owner = new HasBlocksTraitStub();
        $container = new Block();
        $first = new Block();
        $second = new Block();
        $third = new Block();
        $container->addSlot($first);
        $container->addSlot($second);
        $container->addSlot($third);
        $first->setPosition(0);
        $second->setPosition(1);
        $third->setPosition(2);

        new BlockRelocator()->relocate($first, $owner, null);

        $this->assertSame(0, $second->getPosition());
        $this->assertSame(1, $third->getPosition());
    }

    public function testRelocatingIntoAContainerAfterAPriorRemovalDoesNotCollideWithSiblingPositions(): void
    {
        $owner = new HasBlocksTraitStub();
        $container = new Block();
        $first = new Block();
        $second = new Block();
        $third = new Block();
        $container->addSlot($first);
        $container->addSlot($second);
        $container->addSlot($third);
        $first->setPosition(0);
        $second->setPosition(1);
        $third->setPosition(2);
        new BlockRelocator()->relocate($first, $owner, null);

        $incoming = new Block();
        $owner->addBlock($incoming);
        new BlockRelocator()->relocate($incoming, $owner, $container);

        $this->assertSame(2, $incoming->getPosition());
        $this->assertNotSame($third->getPosition(), $incoming->getPosition());
    }
}
