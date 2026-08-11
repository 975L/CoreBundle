<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\BlockEditUrlProviderPass;
use c975L\UiBundle\Registry\BlockEditUrlRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class BlockEditUrlProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new BlockEditUrlProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements BlockEditUrlProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryBlockEditUrlProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockEditUrlRegistry::class);
        $container->register('ui.block_edit_url_provider', DummyBlockEditUrlProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new BlockEditUrlProviderPass()->process($container);

        $calls = $container->getDefinition(BlockEditUrlRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.block_edit_url_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockEditUrlRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new BlockEditUrlProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(BlockEditUrlRegistry::class)->getMethodCalls());
    }
}

class DummyBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function getEditUrls(array $blocks): array
    {
        return [];
    }
}
