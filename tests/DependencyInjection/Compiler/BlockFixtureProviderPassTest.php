<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\BlockFixtureProviderPass;
use c975L\UiBundle\Registry\BlockFixtureRegistry;
use c975L\UiBundle\Service\BlockFixtureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class BlockFixtureProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new BlockFixtureProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements BlockFixtureProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryFixtureProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockFixtureRegistry::class);
        $container->register('ui.block_fixture_provider', BlockFixtureProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new BlockFixtureProviderPass())->process($container);

        $calls = $container->getDefinition(BlockFixtureRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.block_fixture_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockFixtureRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new BlockFixtureProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(BlockFixtureRegistry::class)->getMethodCalls());
    }
}
