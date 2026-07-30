<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class BlockTest extends TestCase
{
    public function testAddSlotSetsReciprocalParentBlock(): void
    {
        $container = new Block();
        $slot = new Block();

        $container->addSlot($slot);

        $this->assertTrue($container->getSlots()->contains($slot));
        $this->assertSame($container, $slot->getParentBlock());
    }

    public function testRemoveSlotClearsReciprocalParentBlock(): void
    {
        $container = new Block();
        $slot = new Block();
        $container->addSlot($slot);

        $container->removeSlot($slot);

        $this->assertFalse($container->getSlots()->contains($slot));
        $this->assertNull($slot->getParentBlock());
    }

    public function testReorderSlotsAssignsSequentialZeroBasedPositions(): void
    {
        $container = new Block();
        $first = new Block();
        $second = new Block();
        $container->addSlot($first);
        $container->addSlot($second);

        $container->reorderSlots();

        $this->assertSame(0, $first->getPosition());
        $this->assertSame(1, $second->getPosition());
    }
}
