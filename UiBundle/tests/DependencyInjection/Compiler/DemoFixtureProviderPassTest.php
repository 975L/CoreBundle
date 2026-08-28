<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\DemoFixtureProviderPass;
use c975L\UiBundle\Registry\DemoFixtureRegistry;
use c975L\UiBundle\Tests\Fixtures\DummyDemoFixtureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DemoFixtureProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new DemoFixtureProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements DemoFixtureProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryDemoFixtureProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(DemoFixtureRegistry::class);
        $container->register('app.demo_fixture_provider', DummyDemoFixtureProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new DemoFixtureProviderPass()->process($container);

        $calls = $container->getDefinition(DemoFixtureRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('app.demo_fixture_provider'), $calls[0][1][0]);
    }
}
