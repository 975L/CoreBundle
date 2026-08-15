<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\PrivilegedAccountCounter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class PrivilegedAccountCounterTest extends TestCase
{
    // Builds the counter over the roles column the query would have returned, the match itself being made in php
    private function createCounter(array $rows): PrivilegedAccountCounter
    {
        $query = $this->createStub(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        return new PrivilegedAccountCounter($entityManager);
    }

    public function testCountHoldingCountsTheAccountsCarryingTheRole(): void
    {
        $counter = $this->createCounter([
            ['roles' => ['ROLE_USER', 'ROLE_ADMIN']],
            ['roles' => ['ROLE_USER']],
            ['roles' => ['ROLE_ADMIN']],
        ]);

        $this->assertSame(2, $counter->countHolding('ROLE_ADMIN'));
    }

    // Held outright or not at all: a site granting the admin role through role_hierarchy counts zero, which is why the check reading this compares a run to the one before rather than to an expected number
    public function testCountHoldingIgnoresARoleOnlyGrantedThroughAnother(): void
    {
        $counter = $this->createCounter([
            ['roles' => ['ROLE_SUPER_ADMIN']],
            ['roles' => ['ROLE_USER']],
        ]);

        $this->assertSame(0, $counter->countHolding('ROLE_ADMIN'));
    }

    // A user row whose roles column was never written, and one holding an empty array
    public function testCountHoldingReadsARowWithoutRoles(): void
    {
        $counter = $this->createCounter([
            ['roles' => null],
            ['roles' => []],
            ['roles' => ['ROLE_ADMIN']],
        ]);

        $this->assertSame(1, $counter->countHolding('ROLE_ADMIN'));
    }

    public function testCountHoldingReturnsZeroOnASiteWithNoAccountAtAll(): void
    {
        $this->assertSame(0, $this->createCounter([])->countHolding('ROLE_ADMIN'));
    }
}
