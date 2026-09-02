<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;
use c975L\UiBundle\DependencyInjection\Compiler\DemoFixtureLinkerPass;
use c975L\UiBundle\Registry\DemoFixtureLinkerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

// No bundle ships a linker of its own yet - the second pass is there for the applications that consume this one, so the discovery is proved against a stand-in
class FakeDemoFixtureLinker implements DemoFixtureLinkerInterface
{
    #[\Override]
    public function getLinkedDemoFixtures(): iterable
    {
        return [];
    }
}

class DemoFixtureLinkerPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new DemoFixtureLinkerPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements DemoFixtureLinkerInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryLinkerImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(DemoFixtureLinkerRegistry::class);
        $container->register('ui.demo_fixture_linker', FakeDemoFixtureLinker::class);
        $container->register('unrelated.service', \stdClass::class);

        new DemoFixtureLinkerPass()->process($container);

        $calls = $container->getDefinition(DemoFixtureLinkerRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.demo_fixture_linker'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(DemoFixtureLinkerRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new DemoFixtureLinkerPass()->process($container);

        $this->assertSame([], $container->getDefinition(DemoFixtureLinkerRegistry::class)->getMethodCalls());
    }
}
