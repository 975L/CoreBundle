<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Service\MapCacheTagProvider;
use PHPUnit\Framework\TestCase;

class MapCacheTagProviderTest extends TestCase
{
    public function testItIsDiscoverableByTheProviderPass(): void
    {
        $this->assertInstanceOf(BlockCacheTagProviderInterface::class, new MapCacheTagProvider());
    }

    // What BlockExtension::renderBlock() puts on a map or contact entry, and what MapSettingsCacheListener invalidates
    public function testTheBlocksReadingTheMapSettingsCarryTheirTag(): void
    {
        $registry = new BlockCacheTagRegistry();
        $registry->addProvider(new MapCacheTagProvider());

        $this->assertSame([MapCacheTagProvider::CACHE_TAG], $registry->getExtraTags(new Block()->setKind('map')));
        $this->assertSame([MapCacheTagProvider::CACHE_TAG], $registry->getExtraTags(new Block()->setKind('contact_details')), 'A contact block keeps linking its address to the map service the site left.');
        $this->assertSame([], $registry->getExtraTags(new Block()->setKind('article')));
    }
}
