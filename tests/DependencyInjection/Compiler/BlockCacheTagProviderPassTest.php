<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\BlockCacheTagProviderPass;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function getCacheTagResolvers(): array
    {
        return [];
    }
}

class BlockCacheTagProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new BlockCacheTagProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements BlockCacheTagProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryCacheTagProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockCacheTagRegistry::class);
        $container->register('site.block_cache_tag_provider', FakeBlockCacheTagProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new BlockCacheTagProviderPass())->process($container);

        $calls = $container->getDefinition(BlockCacheTagRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('site.block_cache_tag_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockCacheTagRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new BlockCacheTagProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(BlockCacheTagRegistry::class)->getMethodCalls());
    }
}
