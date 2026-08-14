<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Registry\CacheInvalidatorRegistry;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheInvalidator
{
    // Tagged on every cached block render (see BlockExtension::renderBlock()), letting this invalidate the whole blocks cache pool without knowing every individual block id
    public const CACHE_TAG_ALL = 'blocks_all';

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly CacheInvalidatorRegistry $cacheInvalidatorRegistry,
    ) {
    }

    // Clears the entire blocks render cache (to be called from the dashboard shortcut), plus every cache a bundle or an app registered alongside it - a Twig "{% cache %}" fragment around its own components, a Doctrine result cache on the lists an index shows, anything else keyed on nothing the block cache knows about (see CacheInvalidatorInterface)
    // The blocks go first: they are what the tile is named after, so they are emptied even if a satellite's own invalidator throws
    public function invalidateAll(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_ALL]);
        $this->cacheInvalidatorRegistry->invalidateAll();
    }
}
