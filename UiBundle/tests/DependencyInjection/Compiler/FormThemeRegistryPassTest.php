<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\FormThemeRegistryPass;
use c975L\UiBundle\Management\UiFormThemeProvider;
use c975L\UiBundle\Registry\FormThemeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FormThemeRegistryPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new FormThemeRegistryPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements FormThemeProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryFormThemeProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormThemeRegistry::class);
        $container->register('ui.form_theme_provider', UiFormThemeProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new FormThemeRegistryPass()->process($container);

        $calls = $container->getDefinition(FormThemeRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.form_theme_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormThemeRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new FormThemeRegistryPass()->process($container);

        $this->assertSame([], $container->getDefinition(FormThemeRegistry::class)->getMethodCalls());
    }
}
