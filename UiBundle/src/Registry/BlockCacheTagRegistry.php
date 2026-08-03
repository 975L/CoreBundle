<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

class BlockCacheTagRegistry
{
    private array $resolvers = [];

    // Called once per tagged provider by BlockCacheTagProviderPass
    public function addProvider(BlockCacheTagProviderInterface $provider): void
    {
        $this->resolvers = array_merge($this->resolvers, $provider->getCacheTagResolvers());
    }

    // Extra cache tags for this block's kind, empty when none was registered for it
    public function getExtraTags(Block $block): array
    {
        $resolver = $this->resolvers[$block->getKind()] ?? null;

        return null !== $resolver ? $resolver($block) : [];
    }
}
