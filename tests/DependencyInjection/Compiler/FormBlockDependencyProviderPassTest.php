<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\FormBlockDependencyProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\FormBlockDependencyProviderPass;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FormBlockDependencyProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new FormBlockDependencyProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service implementing the interface is auto-discovered, no tag needed
    public function testProcessRegistersEveryFormBlockDependencyProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormBlockDependencyRegistry::class);
        $container->register('site.form_block_dependency_provider', DummyFormBlockDependencyProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new FormBlockDependencyProviderPass())->process($container);

        $calls = $container->getDefinition(FormBlockDependencyRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('site.form_block_dependency_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormBlockDependencyRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new FormBlockDependencyProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(FormBlockDependencyRegistry::class)->getMethodCalls());
    }
}

class DummyFormBlockDependencyProvider implements FormBlockDependencyProviderInterface
{
    public function ensureFormBlockDependenciesExist(array $blockData): void
    {
    }
}
