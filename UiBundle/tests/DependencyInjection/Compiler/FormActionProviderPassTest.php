<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\FormActionProviderPass;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Tests\Fixtures\DummyFormAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FormActionProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new FormActionProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements FormActionInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryFormActionImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormActionRegistry::class);
        $container->register('ui.form_action_provider', DummyFormAction::class);
        $container->register('unrelated.service', \stdClass::class);

        new FormActionProviderPass()->process($container);

        $calls = $container->getDefinition(FormActionRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.form_action_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(FormActionRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new FormActionProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(FormActionRegistry::class)->getMethodCalls());
    }
}
