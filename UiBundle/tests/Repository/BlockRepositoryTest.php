<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// What preloadSlots() answers for: the tree of a page or of a menu is read level by level, whatever its depth, instead of one query per block at render time
class BlockRepositoryTest extends TestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testPreloadSlotsReadsOneLevelPerQuery(): void
    {
        $root = $this->block(1);
        $slot = $this->block(2);
        $root->addSlot($slot);

        $asked = $this->preload([$root], [1 => [$root], 2 => [$slot]]);

        // The last level is asked for too: its collections are what that query initializes empty, and leaving it out is what had every leaf queried on its own at render time
        $this->assertSame([[1], [2]], $asked);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPreloadSlotsAsksForEveryBlockOfALevelAtOnce(): void
    {
        $root = $this->block(1);
        $first = $this->block(2);
        $second = $this->block(3);
        $root->addSlot($first);
        $root->addSlot($second);

        $asked = $this->preload([$root], [1 => [$root], 2 => [$first], 3 => [$second]]);

        $this->assertSame([[1], [2, 3]], $asked);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPreloadSlotsStopsOnABlockMadeItsOwnDescendant(): void
    {
        $root = $this->block(1);
        $root->addSlot($root);

        $asked = $this->preload([$root], [1 => [$root]]);

        $this->assertSame([[1]], $asked);
    }

    // Runs preloadSlots() against a repository whose queries are answered from $levels (keyed by block id), and returns the ids each query asked for
    /**
     * @param list<Block>             $blocks
     * @param array<int, list<Block>> $levels
     *
     * @return list<list<int>>
     */
    private function preload(array $blocks, array $levels): array
    {
        $asked = [];

        $repository = $this->createPartialMock(BlockRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturnCallback(function () use (&$asked, $levels): QueryBuilder {
            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('select')->willReturnSelf();
            $queryBuilder->method('leftJoin')->willReturnSelf();
            $queryBuilder->method('andWhere')->willReturnSelf();
            $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use (&$asked, $queryBuilder): QueryBuilder {
                $asked[] = $value;

                return $queryBuilder;
            });

            $queryBuilder->method('getQuery')->willReturnCallback(function () use (&$asked, $levels): Query {
                $result = [];
                foreach (end($asked) ?: [] as $id) {
                    $result = array_merge($result, $levels[$id] ?? []);
                }

                $query = $this->createMock(Query::class);
                $query->method('getResult')->willReturn($result);

                return $query;
            });

            return $queryBuilder;
        });

        $repository->preloadSlots($blocks);

        return $asked;
    }

    private function block(int $id): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }
}
