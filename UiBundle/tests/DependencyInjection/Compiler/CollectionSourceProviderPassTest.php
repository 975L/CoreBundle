<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\CollectionSourceProviderPass;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeCollectionSourceProvider implements CollectionSourceProviderInterface
{
    public function getSources(): array
    {
        return [];
    }
}

class CollectionSourceProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new CollectionSourceProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements CollectionSourceProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryCollectionSourceProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(CollectionSourceRegistry::class);
        $container->register('site.collection_source_provider', FakeCollectionSourceProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new CollectionSourceProviderPass())->process($container);

        $calls = $container->getDefinition(CollectionSourceRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('site.collection_source_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(CollectionSourceRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new CollectionSourceProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(CollectionSourceRegistry::class)->getMethodCalls());
    }
}
