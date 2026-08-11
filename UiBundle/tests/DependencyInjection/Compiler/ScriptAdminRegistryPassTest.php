<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\ScriptAdminRegistryPass;
use c975L\UiBundle\Registry\ScriptAdminRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ScriptAdminRegistryPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new ScriptAdminRegistryPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersEveryServiceTaggedUiAdminScript(): void
    {
        $container = new ContainerBuilder();
        $container->register(ScriptAdminRegistry::class);
        $container->register('provider.a')->addTag('ui.admin_script');

        new ScriptAdminRegistryPass()->process($container);

        $calls = $container->getDefinition(ScriptAdminRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('provider.a'), $calls[0][1][0]);
    }

    public function testProcessOrdersProvidersByDescendingPriority(): void
    {
        $container = new ContainerBuilder();
        $container->register(ScriptAdminRegistry::class);
        $container->register('provider.low')->addTag('ui.admin_script', ['priority' => 0]);
        $container->register('provider.high')->addTag('ui.admin_script', ['priority' => 10]);

        new ScriptAdminRegistryPass()->process($container);

        $calls = $container->getDefinition(ScriptAdminRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('provider.high'), $calls[0][1][0]);
        $this->assertEquals(new Reference('provider.low'), $calls[1][1][0]);
    }
}
