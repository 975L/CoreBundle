<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\AbstractProviderPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

interface FakeProviderInterface
{
}

class FakeProviderImplementation implements FakeProviderInterface
{
}

class FakeRegistry
{
}

class AbstractProviderPassTest extends TestCase
{
    private function createPass(): AbstractProviderPass
    {
        return new class extends AbstractProviderPass {
            public function __construct()
            {
                parent::__construct(FakeProviderInterface::class, FakeRegistry::class);
            }
        };
    }

    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        $this->createPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersEveryMatchingImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(FakeRegistry::class);
        $container->register('fake.provider', FakeProviderImplementation::class);
        $container->register('unrelated.service', \stdClass::class);

        $this->createPass()->process($container);

        $calls = $container->getDefinition(FakeRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('fake.provider'), $calls[0][1][0]);
    }

    // A provider also matched by an instanceof conditional - anything implementing ResetInterface, say - is left a child definition carrying no class of its own, its class sitting on an abstract ".abstract.instanceof." parent no reference may point at (see CheckReferenceValidityPass). Referencing that parent is what stopped the container building
    public function testProcessRegistersAProviderMatchedByAnInstanceofConditional(): void
    {
        $container = new ContainerBuilder();
        $container->register(FakeRegistry::class);
        $container->registerForAutoconfiguration(FakeProviderInterface::class)->addTag('fake.reset');
        $container->register('fake.provider', FakeProviderImplementation::class)->setAutoconfigured(true);

        // The one pass that has already run when a bundle's own runs: the child definitions it writes are only merged later, by ResolveChildDefinitionsPass
        new ResolveInstanceofConditionalsPass()->process($container);

        $this->createPass()->process($container);

        $calls = $container->getDefinition(FakeRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(new Reference('fake.provider'), $calls[0][1][0]);
    }

    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(FakeRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        $this->createPass()->process($container);

        $this->assertSame([], $container->getDefinition(FakeRegistry::class)->getMethodCalls());
    }
}
