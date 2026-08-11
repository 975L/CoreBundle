<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
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

        new StylesheetRegistryPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersEveryServiceTaggedUiStylesheet(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        $container->register('provider.a')->addTag('ui.stylesheet');

        new StylesheetRegistryPass()->process($container);

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

        new StylesheetRegistryPass()->process($container);

        $calls = $container->getDefinition(StylesheetRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('provider.high'), $calls[0][1][0]);
        $this->assertEquals(new Reference('provider.low'), $calls[1][1][0]);
    }

    public function testProcessTagsAnUntaggedServiceImplementingTheInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        $container->register('app.theme', AppThemeProvider::class);

        new StylesheetRegistryPass()->process($container);

        $calls = $container->getDefinition(StylesheetRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(new Reference('app.theme'), $calls[0][1][0]);
    }

    public function testProcessLoadsAnAutoTaggedProviderAfterEveryBundleOne(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        // Registered first, so a tie on priority would leave it ahead of the bundle's sheet
        $container->register('app.theme', AppThemeProvider::class);
        $container->register('bundle.provider')->addTag('ui.stylesheet', ['priority' => 0]);

        new StylesheetRegistryPass()->process($container);

        $calls = $container->getDefinition(StylesheetRegistry::class)->getMethodCalls();
        $this->assertEquals(new Reference('bundle.provider'), $calls[0][1][0]);
        $this->assertEquals(new Reference('app.theme'), $calls[1][1][0]);
    }

    public function testProcessDoesNotTagAnAlreadyTaggedProviderTwice(): void
    {
        $container = new ContainerBuilder();
        $container->register(StylesheetRegistry::class);
        $container->register('bundle.provider', AppThemeProvider::class)
            ->addTag('ui.stylesheet', ['priority' => 100]);

        new StylesheetRegistryPass()->process($container);

        $definition = $container->getDefinition('bundle.provider');
        $this->assertCount(1, $definition->getTag('ui.stylesheet'));
        $this->assertCount(1, $container->getDefinition(StylesheetRegistry::class)->getMethodCalls());
    }
}

class AppThemeProvider implements BundleStylesheetProviderInterface
{
    public function getStylesheets(): array
    {
        return ['styles/themes/ui.css'];
    }
}
