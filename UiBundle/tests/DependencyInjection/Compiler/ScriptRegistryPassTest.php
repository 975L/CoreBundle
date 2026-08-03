<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\ScriptRegistryPass;
use c975L\UiBundle\Registry\ScriptRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ScriptRegistryPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new ScriptRegistryPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersEveryServiceTaggedUiScript(): void
    {
        $container = new ContainerBuilder();
        $container->register(ScriptRegistry::class);
        $container->register('provider.a')->addTag('ui.script');

        (new ScriptRegistryPass())->process($container);

        $calls = $container->getDefinition(ScriptRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('provider.a'), $calls[0][1][0]);
    }

    // Tagged providers are registered highest priority first
    public function testProcessOrdersProvidersByDescendingPriority(): void
    {
        $container = new ContainerBuilder();
        $container->register(ScriptRegistry::class);
        $container->register('provider.low')->addTag('ui.script', ['priority' => 0]);
        $container->register('provider.high')->addTag('ui.script', ['priority' => 10]);

        (new ScriptRegistryPass())->process($container);

        $calls = $container->getDefinition(ScriptRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('provider.high'), $calls[0][1][0]);
        $this->assertEquals(new Reference('provider.low'), $calls[1][1][0]);
    }

    // Untagged priority defaults to 0
    public function testProcessDefaultsMissingPriorityToZero(): void
    {
        $container = new ContainerBuilder();
        $container->register(ScriptRegistry::class);
        $container->register('provider.a')->addTag('ui.script');
        $container->register('provider.b')->addTag('ui.script', ['priority' => -5]);

        (new ScriptRegistryPass())->process($container);

        $calls = $container->getDefinition(ScriptRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('provider.a'), $calls[0][1][0]);
        $this->assertEquals(new Reference('provider.b'), $calls[1][1][0]);
    }
}
