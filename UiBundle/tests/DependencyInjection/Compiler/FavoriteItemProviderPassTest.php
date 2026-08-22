<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\FavoriteItemProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\FavoriteItemProviderPass;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeFavoriteItemProvider implements FavoriteItemProviderInterface
{
    public function supports(string $ownerType): bool
    {
        return false;
    }

    public function getItems(string $ownerType, array $ownerIds): array
    {
        return [];
    }
}

class FavoriteItemProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new FavoriteItemProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements FavoriteItemProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryFavoriteItemProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(FavoriteItemRegistry::class);
        $container->register('shop.favorite_item_provider', FakeFavoriteItemProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new FavoriteItemProviderPass()->process($container);

        $calls = $container->getDefinition(FavoriteItemRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('shop.favorite_item_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(FavoriteItemRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new FavoriteItemProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(FavoriteItemRegistry::class)->getMethodCalls());
    }
}
