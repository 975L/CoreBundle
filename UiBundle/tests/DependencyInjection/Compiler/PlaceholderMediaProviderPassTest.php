<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\PlaceholderMediaProviderPass;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PlaceholderMediaProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new PlaceholderMediaProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements PlaceholderMediaProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryPlaceholderMediaProviderImplementation(): void
    {
        $fakeProvider = new class implements PlaceholderMediaProviderInterface {
            public function getPlaceholderMedia(): array
            {
                return [];
            }
        };

        $container = new ContainerBuilder();
        $container->register(PlaceholderMediaRegistry::class);
        $container->register('ui.placeholder_media_provider', $fakeProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new PlaceholderMediaProviderPass()->process($container);

        $calls = $container->getDefinition(PlaceholderMediaRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.placeholder_media_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(PlaceholderMediaRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new PlaceholderMediaProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(PlaceholderMediaRegistry::class)->getMethodCalls());
    }
}
