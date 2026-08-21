<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\RatingRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// The tally is counted by the database and never by hydrating the rows: a book everyone rated holds thousands of them, and a catalog page asks for thirty tallies at once
#[AllowMockObjectsWithoutExpectations]
class RatingRepositoryTest extends TestCase
{
    public function testATallyIsRoundedToOneDecimalAndKeyedByItsOwner(): void
    {
        $aggregates = $this->aggregates([
            ['ownerId' => 7, 'average' => '4.23456', 'total' => '37'],
            ['ownerId' => 9, 'average' => '5', 'total' => '2'],
        ], [7, 9]);

        $this->assertSame([
            7 => ['average' => 4.2, 'count' => 37],
            9 => ['average' => 5.0, 'count' => 2],
        ], $aggregates);
    }

    // An id nobody voted on is simply absent: the callers that need a zero put it there themselves (see RatingRuntime::ratings())
    public function testAnOwnerWithNoVoteIsLeftOut(): void
    {
        $this->assertSame([], $this->aggregates([], [7]));
    }

    // An empty list runs no query at all - "IN ()" is not something to hand a database
    public function testAnEmptyListAsksNothing(): void
    {
        $repository = $this->createPartialMock(RatingRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame([], $repository->getAggregates('book', []));
    }

    // What a page prints for one thing, zeroed rather than missing: a template asking for a tally always gets one
    public function testASingleTallyFallsBackToAZeroedOne(): void
    {
        $repository = $this->repository([]);

        $this->assertSame(['average' => 0.0, 'count' => 0], $repository->getAggregate('book', 7));
    }

    private function aggregates(array $rows, array $ownerIds): array
    {
        return $this->repository($rows)->getAggregates('book', $ownerIds);
    }

    private function repository(array $rows): RatingRepository
    {
        $repository = $this->createPartialMock(RatingRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturnCallback(function () use ($rows): QueryBuilder {
            $query = $this->createMock(Query::class);
            $query->method('getArrayResult')->willReturn($rows);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            foreach (['select', 'andWhere', 'setParameter', 'groupBy'] as $method) {
                $queryBuilder->method($method)->willReturnSelf();
            }
            $queryBuilder->method('getQuery')->willReturn($query);

            return $queryBuilder;
        });

        return $repository;
    }
}
