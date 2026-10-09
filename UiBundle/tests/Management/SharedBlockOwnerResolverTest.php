<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Management\SharedBlockOwnerResolver;
use c975L\UiBundle\Repository\SharedBlockRepository;
use PHPUnit\Framework\TestCase;

class SharedBlockOwnerResolverTest extends TestCase
{
    public function testSupportsOnlyItsOwnType(): void
    {
        $resolver = new SharedBlockOwnerResolver($this->createStub(SharedBlockRepository::class));

        $this->assertTrue($resolver->supports(SharedBlockOwnerResolver::TYPE));
        $this->assertFalse($resolver->supports('page'));
    }

    public function testFindAnswersTheSharedBlockOfItsOwnTypeOnly(): void
    {
        $sharedBlock = new SharedBlock();
        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('find')->willReturn($sharedBlock);
        $resolver = new SharedBlockOwnerResolver($repository);

        $this->assertSame($sharedBlock, $resolver->find(SharedBlockOwnerResolver::TYPE, 4));
        $this->assertNull($resolver->find('page', 4));
    }
}
