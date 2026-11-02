<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Repository;

use c975L\ConfigBundle\Repository\ConfigRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

// What findAllValues() answers for: the database's values, never an entity already managed and possibly out of date
class ConfigRepositoryTest extends TestCase
{
    // Four columns read as arrays, no entity hydrated
    public function testFindAllValuesReadsArraysRatherThanEntities(): void
    {
        $rows = [['slug' => 'site-name', 'value' => 'My Site', 'isSensitive' => false, 'kind' => 'text']];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getArrayResult')->willReturn($rows);
        $query->expects($this->never())->method('getResult');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('c.slug', 'c.value', 'c.isSensitive', 'c.kind')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createPartialMock(ConfigRepository::class, ['createQueryBuilder']);
        $repository->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->assertSame($rows, $repository->findAllValues());
    }
}
