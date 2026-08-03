<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\MediaUsageProviderPass;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Service\BlockMediaUsageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MediaUsageProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new MediaUsageProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements MediaUsageProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryMediaUsageProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(MediaUsageRegistry::class);
        $container->register('ui.media_usage_provider', BlockMediaUsageProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new MediaUsageProviderPass())->process($container);

        $calls = $container->getDefinition(MediaUsageRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.media_usage_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(MediaUsageRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new MediaUsageProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(MediaUsageRegistry::class)->getMethodCalls());
    }
}
