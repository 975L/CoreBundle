<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\PlayTotalRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// Every play adds one to its thing's total in a single statement, and the totals are read back by owner id
#[AllowMockObjectsWithoutExpectations]
class PlayTotalRepositoryTest extends TestCase
{
    // An upsert, so two listens ending at once are both counted
    public function testAPlayIsAddedInOneStatement(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with(
            $this->stringContains('ON DUPLICATE KEY UPDATE plays = plays + 1'),
            ['ownerType' => 'book', 'ownerId' => 12]
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $repository = $this->createPartialMock(PlayTotalRepository::class, ['getEntityManager']);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $repository->increment('book', 12);
    }

    public function testTotalsAreKeyedByOwnerId(): void
    {
        $parameters = [];
        $repository = $this->repository($parameters, [
            ['ownerId' => '12', 'plays' => '3'],
            ['ownerId' => 13, 'plays' => 1],
        ]);

        $this->assertSame([12 => 3, 13 => 1], $repository->totals('book', [12, 13, 14]));
        $this->assertSame(['ownerType' => 'book', 'ownerIds' => [12, 13, 14]], $parameters);
    }

    // No owner asked for, no query run
    public function testNoOwnerIdsReadNothing(): void
    {
        $repository = $this->createPartialMock(PlayTotalRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame([], $repository->totals('book', []));
    }

    /** @param list<array<string, mixed>> $result */
    private function repository(array &$parameters, array $result): PlayTotalRepository
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($result);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use ($queryBuilder, &$parameters): QueryBuilder {
            $parameters[$name] = $value;

            return $queryBuilder;
        });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createPartialMock(PlayTotalRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $repository;
    }
}
