<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\PlayRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// Plays are counted under the keys the favorites use, and handed over to the account whole
#[AllowMockObjectsWithoutExpectations]
class PlayRepositoryTest extends TestCase
{
    public function testPlaysAreCountedUnderTheFavoritesKeys(): void
    {
        $parameters = [];
        $repository = $this->repository($parameters, result: [
            ['ownerType' => 'book', 'ownerId' => 12, 'plays' => '3'],
            ['ownerType' => 'film', 'ownerId' => 4, 'plays' => 1],
        ]);

        $this->assertSame(['book:12' => 3, 'film:4' => 1], $repository->countsByHolder('u42'));
        $this->assertSame(['holder' => 'u42'], $parameters);
    }

    public function testAHolderWhoPlayedNothingCountsNothing(): void
    {
        $parameters = [];

        $this->assertSame([], $this->repository($parameters, result: [])->countsByHolder('u42'));
    }

    // Nothing to reconcile: every play of the device moves in one update
    public function testADevicesPlaysMoveToTheAccount(): void
    {
        $parameters = [];
        $repository = $this->repository($parameters, moved: 2);

        $this->assertSame(2, $repository->moveHolder('0123456789abcdef0123456789abcdef', 'u42'));
        $this->assertSame(['from' => '0123456789abcdef0123456789abcdef', 'to' => 'u42'], $parameters);
    }

    /** @param list<array<string, mixed>> $result */
    private function repository(array &$parameters, array $result = [], int $moved = 0): PlayRepository
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($result);
        $query->method('execute')->willReturn($moved);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'andWhere', 'groupBy', 'update', 'set'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use ($queryBuilder, &$parameters): QueryBuilder {
            $parameters[$name] = $value;

            return $queryBuilder;
        });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createPartialMock(PlayRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $repository;
    }
}
