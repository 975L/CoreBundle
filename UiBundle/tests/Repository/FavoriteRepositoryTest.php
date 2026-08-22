<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\FavoriteRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// A whole list is read in one query and grouped by kind, because that is how it is drawn: one provider resolves one kind, and thirty entries over three kinds must run three queries and not thirty
#[AllowMockObjectsWithoutExpectations]
class FavoriteRepositoryTest extends TestCase
{
    public function testAListIsGroupedByKindInTheOrderItWasRead(): void
    {
        $repository = $this->repository([
            ['ownerType' => 'shop_product', 'ownerId' => 39],
            ['ownerType' => 'book', 'ownerId' => 7],
            ['ownerType' => 'shop_product', 'ownerId' => 12],
        ]);

        $this->assertSame(
            ['shop_product' => [39, 12], 'book' => [7]],
            $repository->findIdsByHolder('u7')
        );
    }

    public function testAnEmptyListIsAnEmptyArray(): void
    {
        $this->assertSame([], $this->repository([])->findIdsByHolder('u7'));
    }

    // A kind holding nothing of this holder's is absent rather than mapped to an empty list, which is what the merge reads to tell an entry to move from one to drop
    public function testOnlyWhatWasFoundComesBackFromTheLookup(): void
    {
        $repository = $this->repository([['ownerId' => 39]]);

        $this->assertSame(
            ['shop_product' => [39]],
            $repository->findExistingAmong('u7', ['shop_product' => [39, 12], 'book' => []])
        );
    }

    // An empty set of ids runs no query at all - "IN ()" is not something to hand a database
    public function testAKindWithNoIdAsksNothing(): void
    {
        $repository = $this->createPartialMock(FavoriteRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame([], $repository->findExistingAmong('u7', ['book' => []]));
    }

    // Deleting a catalog of two thousand products takes one query, not two thousand
    public function testAWholeSetIsDeletedInOneQuery(): void
    {
        $parameters = [];
        $repository = $this->deletingRepository(5, $parameters, $calls);

        $this->assertSame(5, $repository->deleteForOwners('shop_product', [39, 12]));
        $this->assertSame(1, $calls);
        $this->assertSame([39, 12], $parameters['ownerIds']);
    }

    // One owner goes through the very same query, the set holding just it
    public function testDeletingOneOwnerDeletesTheSetItAloneMakes(): void
    {
        $parameters = [];
        $repository = $this->deletingRepository(1, $parameters, $calls);

        $this->assertSame(1, $repository->deleteForOwner('shop_product', 39));
        $this->assertSame([39], $parameters['ownerIds']);
    }

    public function testAnEmptySetDeletesNothing(): void
    {
        $repository = $this->createPartialMock(FavoriteRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame(0, $repository->deleteForOwners('shop_product', []));
    }

    // A browser holding nothing has nothing to hand over: the merge runs no query rather than an update matching no row
    public function testMovingAnEmptyListRunsNothing(): void
    {
        $repository = $this->createPartialMock(FavoriteRepository::class, ['findIdsByHolder']);
        $repository->method('findIdsByHolder')->willReturn([]);

        $this->assertSame(0, $repository->moveHolder('0123456789abcdef0123456789abcdef', 'u7'));
    }

    private function deletingRepository(int $deleted, array &$parameters, ?int &$calls = null): FavoriteRepository
    {
        $calls = 0;
        $repository = $this->createPartialMock(FavoriteRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturnCallback(function () use ($deleted, &$parameters, &$calls): QueryBuilder {
            ++$calls;
            $query = $this->createMock(Query::class);
            $query->method('execute')->willReturn($deleted);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            foreach (['delete', 'andWhere'] as $method) {
                $queryBuilder->method($method)->willReturnSelf();
            }
            $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use ($queryBuilder, &$parameters): QueryBuilder {
                $parameters[$name] = $value;

                return $queryBuilder;
            });
            $queryBuilder->method('getQuery')->willReturn($query);

            return $queryBuilder;
        });

        return $repository;
    }

    private function repository(array $rows): FavoriteRepository
    {
        $repository = $this->createPartialMock(FavoriteRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturnCallback(function () use ($rows): QueryBuilder {
            $query = $this->createMock(Query::class);
            $query->method('getArrayResult')->willReturn($rows);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            foreach (['select', 'andWhere', 'setParameter', 'orderBy', 'addOrderBy'] as $method) {
                $queryBuilder->method($method)->willReturnSelf();
            }
            $queryBuilder->method('getQuery')->willReturn($query);

            return $queryBuilder;
        });

        return $repository;
    }
}
