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

/**
 * Implement to add extra cache tags for a block kind whose rendered output depends on data outside the Block/Media entities themselves (e.g. articles_slider resolves another Page's own blocks live at render time) - BlockCacheInvalidationListener only ever invalidates "block_{id}", so a kind depending on something else needs its own extra tag plus its own invalidation elsewhere.
 */
interface BlockCacheTagProviderInterface
{
    /**
     * One entry per covered kind, each resolver returning the extra cache tags to apply on top of the default "block_{id}"/"blocks_all" ones.
     *
     * @return array<string, callable(Block): string[]> block kind => resolver
     */
    public function getCacheTagResolvers(): array;
}
