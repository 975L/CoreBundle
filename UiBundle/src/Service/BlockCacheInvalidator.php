<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheInvalidator
{
    // Tagged on every cached block render (see BlockExtension::renderBlock()), letting this invalidate the whole blocks cache pool without knowing every individual block id
    public const CACHE_TAG_ALL = 'blocks_all';

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    // Clears the entire blocks render cache (to be called from the dashboard shortcut)
    public function invalidateAll(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG_ALL]);
    }
}
