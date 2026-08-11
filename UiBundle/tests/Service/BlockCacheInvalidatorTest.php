<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheInvalidatorTest extends TestCase
{
    public function testInvalidateAllInvalidatesTheSharedTag(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([BlockCacheInvalidator::CACHE_TAG_ALL]);

        new BlockCacheInvalidator($cache)->invalidateAll();
    }
}
