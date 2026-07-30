<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\StylesheetRegistryPass;
use c975L\UiBundle\Registry\StylesheetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class StylesheetRegistryPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new StylesheetRegistryPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersEveryServiceTaggedUiStylesheet(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        $container->register('provider.a')->addTag('ui.stylesheet');

        (new StylesheetRegistryPass())->process($container);

        $calls = $container->getDefinition(StylesheetRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('provider.a'), $calls[0][1][0]);
    }

    public function testProcessOrdersProvidersByDescendingPriority(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        $container->register('provider.low')->addTag('ui.stylesheet', ['priority' => 0]);
        $container->register('provider.high')->addTag('ui.stylesheet', ['priority' => 10]);

        (new StylesheetRegistryPass())->process($container);

        $calls = $container->getDefinition(StylesheetRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('provider.high'), $calls[0][1][0]);
        $this->assertEquals(new Reference('provider.low'), $calls[1][1][0]);
    }
}
