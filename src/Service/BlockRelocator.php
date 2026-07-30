<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;

// Moves a persisted Block, keeping its id and Medias; uses detachBlock(), removeBlock() queueing a deletion. Never flushes
class BlockRelocator
{
    public function relocate(Block $block, HasBlocksInterface $owner, ?Block $targetContainer): void
    {
        $currentParent = $block->getParentBlock();
        if (null !== $currentParent) {
            $currentParent->removeSlot($block);
            $currentParent->reorderSlots();
        } else {
            $owner->detachBlock($block);
            $owner->reorderBlocks();
        }

        if (null !== $targetContainer) {
            $targetContainer->addSlot($block);
            $block->setPosition($targetContainer->getSlots()->count() - 1);
        } else {
            $owner->addBlock($block);
            $block->setPosition($owner->getBlocks()->count() - 1);
        }
    }
}
