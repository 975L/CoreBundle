<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use PHPUnit\Framework\TestCase;

class BlockCacheTagRegistryTest extends TestCase
{
    private function createProvider(array $resolvers): BlockCacheTagProviderInterface
    {
        $provider = $this->createStub(BlockCacheTagProviderInterface::class);
        $provider->method('getCacheTagResolvers')->willReturn($resolvers);

        return $provider;
    }

    public function testGetExtraTagsReturnsEmptyArrayWhenNoResolverRegisteredTheKind(): void
    {
        $registry = new BlockCacheTagRegistry();
        $block = new Block()->setKind('article');

        $this->assertSame([], $registry->getExtraTags($block));
    }

    public function testGetExtraTagsCallsTheRegisteredResolverForTheBlockKind(): void
    {
        $registry = new BlockCacheTagRegistry();
        $registry->addProvider($this->createProvider([
            'articles_slider' => fn (Block $b) => ['page_' . $b->getData()['pageId']],
        ]));

        $block = new Block()->setKind('articles_slider')->setData(['pageId' => 5]);

        $this->assertSame(['page_5'], $registry->getExtraTags($block));
    }

    // Resolvers are merged across providers, same as BlockFixtureRegistry
    public function testResolversFromSeveralProvidersAreMerged(): void
    {
        $registry = new BlockCacheTagRegistry();
        $registry->addProvider($this->createProvider(['articles_slider' => fn () => ['page_1']]));
        $registry->addProvider($this->createProvider(['menu_link' => fn () => ['route_x']]));

        $this->assertSame(['page_1'], $registry->getExtraTags(new Block()->setKind('articles_slider')));
        $this->assertSame(['route_x'], $registry->getExtraTags(new Block()->setKind('menu_link')));
    }
}
