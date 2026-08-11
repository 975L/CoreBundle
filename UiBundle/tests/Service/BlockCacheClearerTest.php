<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockCacheClearer;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheClearerTest extends TestCase
{
    public function testClearInvalidatesEveryCachedBlockRender(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([BlockCacheInvalidator::CACHE_TAG_ALL]);

        new BlockCacheClearer(new BlockCacheInvalidator($cache))->clear('/var/cache/prod');
    }

    // The whole point is that cache:clear picks it up on its own - FrameworkBundle autoconfigures this interface into the "kernel.cache_clearer" tag, so dropping it would silently stop deployments from invalidating anything, with nothing failing anywhere
    public function testItIsACacheClearerSoDeploymentsPickItUpWithoutATagOfItsOwn(): void
    {
        $this->assertInstanceOf(CacheClearerInterface::class, new BlockCacheClearer(new BlockCacheInvalidator($this->createStub(TagAwareCacheInterface::class))));
    }
}
