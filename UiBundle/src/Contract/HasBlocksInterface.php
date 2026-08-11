<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Entity\Block;
use Doctrine\Common\Collections\Collection;

// Each entity that owns blocks must implement this interface (see Readme).
interface HasBlocksInterface
{
    /**
     * @return Collection<int, Block>
     */
    public function getBlocks(): Collection;

    public function addBlock(Block $block): static;

    public function removeBlock(Block $block): static;

    // Same as removeBlock() but never queues the block for deletion, for relocating it elsewhere.
    public function detachBlock(Block $block): static;

    // Renumbers the remaining blocks from 0, called by BlockRelocator after a detach - HasBlocksTrait implements it, an entity writing its own has to as well.
    public function reorderBlocks(): void;
}
