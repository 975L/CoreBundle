<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\SharedBlockUsage;
use PHPUnit\Framework\TestCase;

// Where a shared block is shown, the answer the delete guard and the index column both read
class SharedBlockUsageTest extends TestCase
{
    public function testThePointersNamingASlugAreFound(): void
    {
        $usage = new SharedBlockUsage($this->createRepository(['contact', 'bundles', 'contact']));

        $this->assertCount(2, $usage->findPointers('contact'));
        $this->assertCount(1, $usage->findPointers('bundles'));
        $this->assertSame([], $usage->findPointers('nowhere'));
    }

    // A pointer saved without a slug names nothing and counts for nothing
    public function testThePointersAreCountedPerSlug(): void
    {
        $usage = new SharedBlockUsage($this->createRepository(['contact', 'bundles', 'contact', '']));

        $this->assertSame(['contact' => 2, 'bundles' => 1], $usage->countBySlug());
    }

    /** @param string[] $slugs */
    private function createRepository(array $slugs): BlockRepository
    {
        $repository = $this->createStub(BlockRepository::class);
        $repository->method('findByKind')->willReturn(array_map(
            static fn (string $slug): Block => new Block()->setKind(SharedBlock::POINTER_KIND)->setData(['slug' => $slug]),
            $slugs
        ));

        return $repository;
    }
}
