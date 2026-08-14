<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\CacheInvalidatorInterface;
use c975L\UiBundle\Registry\CacheInvalidatorRegistry;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BlockCacheInvalidatorTest extends TestCase
{
    public function testInvalidateAllInvalidatesTheSharedTag(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([BlockCacheInvalidator::CACHE_TAG_ALL]);

        new BlockCacheInvalidator($cache, new CacheInvalidatorRegistry())->invalidateAll();
    }

    // The dashboard tile, a legal model being customized and every cache:clear all come through here - so a cache an app registered alongside the blocks (a Twig fragment, a Doctrine result cache) is emptied by the same gesture, see CacheInvalidatorInterface
    public function testInvalidateAllAlsoRunsTheCachesRegisteredAlongsideTheBlocks(): void
    {
        $called = false;
        $registry = new CacheInvalidatorRegistry();
        $registry->addProvider(new class ($called) implements CacheInvalidatorInterface {
            public function __construct(private bool &$called)
            {
            }

            public function invalidate(): void
            {
                $this->called = true;
            }
        });

        new BlockCacheInvalidator($this->createStub(TagAwareCacheInterface::class), $registry)->invalidateAll();

        $this->assertTrue($called);
    }
}
