<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity\Trait;

use c975L\UiBundle\Entity\Block;
use Doctrine\Common\Collections\Collection;

// Provides $blocks collection methods for entities that own blocks (see Readme)
trait HasBlocksTrait
{
    private array $pendingBlockRemovals = [];

    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function addBlock(Block $block): static
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
        }

        return $this;
    }

    public function removeBlock(Block $block): static
    {
        if ($this->blocks->removeElement($block)) {
            $this->pendingBlockRemovals[] = $block;
        }

        return $this;
    }

    // Never queues the block for deletion, for a move within the same flush rather than a removal
    public function detachBlock(Block $block): static
    {
        $this->blocks->removeElement($block);

        return $this;
    }

    public function popPendingBlockRemovals(): array
    {
        $blocks = $this->pendingBlockRemovals;
        $this->pendingBlockRemovals = [];

        return $blocks;
    }

    public function reorderBlocks(): void
    {
        $position = 0;
        foreach ($this->blocks as $block) {
            $block->setPosition($position++);
        }
    }
}
