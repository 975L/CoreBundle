<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\WhatsNewProviderPass;
use c975L\UiBundle\Registry\WhatsNewRegistry;
use c975L\UiBundle\Service\WhatsNewProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class WhatsNewProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new WhatsNewProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements BundleWhatsNewProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryWhatsNewProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(WhatsNewRegistry::class);
        $container->register('ui.whats_new_provider', WhatsNewProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new WhatsNewProviderPass()->process($container);

        $calls = $container->getDefinition(WhatsNewRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.whats_new_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(WhatsNewRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new WhatsNewProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(WhatsNewRegistry::class)->getMethodCalls());
    }
}
