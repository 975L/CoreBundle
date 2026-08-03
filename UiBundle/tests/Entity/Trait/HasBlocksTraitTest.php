<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity\Trait;

use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class HasBlocksTraitTest extends TestCase
{
    public function testAddBlockAddsIt(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();

        $owner->addBlock($block);

        $this->assertTrue($owner->getBlocks()->contains($block));
    }

    // Adding the same block twice must not create a duplicate entry
    public function testAddBlockIsIdempotent(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();

        $owner->addBlock($block);
        $owner->addBlock($block);

        $this->assertCount(1, $owner->getBlocks());
    }

    public function testRemoveBlockRemovesItFromTheCollection(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);

        $owner->removeBlock($block);

        $this->assertFalse($owner->getBlocks()->contains($block));
    }

    // Removed blocks are queued for explicit deletion by BlockRemovalListener, since dropping them from a ManyToMany collection alone only deletes the join row, not the orphaned Block itself
    public function testRemoveBlockQueuesItForPendingRemoval(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);

        $owner->removeBlock($block);

        $this->assertSame([$block], $owner->popPendingBlockRemovals());
    }

    // Removing a block that was never part of the collection must not queue it
    public function testRemoveBlockDoesNotQueueUnknownBlock(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();

        $owner->removeBlock($block);

        $this->assertSame([], $owner->popPendingBlockRemovals());
    }

    public function testPopPendingBlockRemovalsClearsTheQueue(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);
        $owner->removeBlock($block);

        $owner->popPendingBlockRemovals();

        $this->assertSame([], $owner->popPendingBlockRemovals());
    }

    // detachBlock() must not queue the block for deletion, being used to relocate it
    public function testDetachBlockRemovesItWithoutQueuingForRemoval(): void
    {
        $owner = new HasBlocksTraitStub();
        $block = new Block();
        $owner->addBlock($block);

        $owner->detachBlock($block);

        $this->assertFalse($owner->getBlocks()->contains($block));
        $this->assertSame([], $owner->popPendingBlockRemovals());
    }

    public function testReorderBlocksAssignsSequentialZeroBasedPositions(): void
    {
        $owner = new HasBlocksTraitStub();
        $first = new Block();
        $second = new Block();
        $owner->addBlock($first);
        $owner->addBlock($second);

        $owner->reorderBlocks();

        $this->assertSame(0, $first->getPosition());
        $this->assertSame(1, $second->getPosition());
    }
}
