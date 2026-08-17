<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\SameAsProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\SameAsProviderPass;
use c975L\UiBundle\Registry\SameAsRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SameAsProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new SameAsProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements SameAsProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEverySameAsProviderImplementation(): void
    {
        $fakeProvider = new class implements SameAsProviderInterface {
            public function getSameAs(): array
            {
                return [];
            }
        };

        $container = new ContainerBuilder();
        $container->register(SameAsRegistry::class);
        $container->register('ui.same_as_provider', $fakeProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new SameAsProviderPass()->process($container);

        $calls = $container->getDefinition(SameAsRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.same_as_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(SameAsRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new SameAsProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(SameAsRegistry::class)->getMethodCalls());
    }
}
