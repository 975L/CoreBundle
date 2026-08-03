<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Registry\BlockOwnerRegistry;
use PHPUnit\Framework\TestCase;

class BlockOwnerRegistryTest extends TestCase
{
    public function testFindReturnsNullWhenNoProviderSupportsTheOwnerType(): void
    {
        $registry = new BlockOwnerRegistry();

        $this->assertNull($registry->find('page', 1));
    }

    public function testFindDelegatesToTheFirstSupportingProvider(): void
    {
        $owner = $this->createStub(HasBlocksInterface::class);

        $nonSupporting = $this->createStub(BlockOwnerResolverInterface::class);
        $nonSupporting->method('supports')->willReturn(false);

        $supporting = $this->createStub(BlockOwnerResolverInterface::class);
        $supporting->method('supports')->willReturn(true);
        $supporting->method('find')->willReturn($owner);

        $registry = new BlockOwnerRegistry();
        $registry->addProvider($nonSupporting);
        $registry->addProvider($supporting);

        $this->assertSame($owner, $registry->find('page', 1));
    }

    public function testFindReturnsNullWhenTheOnlySupportingProviderReturnsNull(): void
    {
        $first = $this->createMock(BlockOwnerResolverInterface::class);
        $first->method('supports')->willReturn(true);
        $first->expects($this->once())->method('find')->willReturn(null);

        $second = $this->createMock(BlockOwnerResolverInterface::class);
        $second->method('supports')->willReturn(false);
        $second->expects($this->never())->method('find');

        $registry = new BlockOwnerRegistry();
        $registry->addProvider($first);
        $registry->addProvider($second);

        $this->assertNull($registry->find('page', 1));
    }

    // Two providers claiming the same ownerType must fail loudly, not leave one silently unreachable
    public function testFindThrowsWhenSeveralProvidersSupportTheSameOwnerType(): void
    {
        $first = $this->createStub(BlockOwnerResolverInterface::class);
        $first->method('supports')->willReturn(true);

        $second = $this->createStub(BlockOwnerResolverInterface::class);
        $second->method('supports')->willReturn(true);

        $registry = new BlockOwnerRegistry();
        $registry->addProvider($first);
        $registry->addProvider($second);

        $this->expectException(\LogicException::class);

        $registry->find('page', 1);
    }
}
