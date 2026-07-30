<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\DependencyInjection\Compiler\BlockOwnerResolverPass;
use c975L\UiBundle\Registry\BlockOwnerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class BlockOwnerResolverPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new BlockOwnerResolverPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements BlockOwnerResolverInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryOwnerResolverImplementation(): void
    {
        $fakeResolver = new class implements BlockOwnerResolverInterface {
            public function supports(string $ownerType): bool
            {
                return false;
            }

            public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
            {
                return null;
            }
        };

        $container = new ContainerBuilder();
        $container->register(BlockOwnerRegistry::class);
        $container->register('ui.block_owner_resolver', $fakeResolver::class);
        $container->register('unrelated.service', \stdClass::class);

        (new BlockOwnerResolverPass())->process($container);

        $calls = $container->getDefinition(BlockOwnerRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.block_owner_resolver'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockOwnerRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new BlockOwnerResolverPass())->process($container);

        $this->assertSame([], $container->getDefinition(BlockOwnerRegistry::class)->getMethodCalls());
    }
}
