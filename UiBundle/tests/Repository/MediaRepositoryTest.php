<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class MediaRepositoryTest extends TestCase
{
    // The search words looked for with their wildcards escaped, the limit kept
    public function testTheSearchWordsAreEscaped(): void
    {
        $this->assertSame(['words' => '%50\%\_off%'], $this->find(' 50%_off ', false, 20, $wheres, $limit));
        $this->assertCount(1, $wheres);
        $this->assertSame(20, $limit);
    }

    // Only the rows without an alt text when asked, no search when the words are blank
    public function testOnlyTheMediasWithoutAnAltText(): void
    {
        $this->assertSame(['empty' => ''], $this->find('  ', true, 50, $wheres, $limit));
        $this->assertSame(['m.alt IS NULL OR m.alt = :empty'], $wheres);
    }

    // No condition at all without words nor filter
    public function testEveryMediaWithoutCondition(): void
    {
        $this->assertSame([], $this->find(null, false, 50, $wheres, $limit));
        $this->assertSame([], $wheres);
    }

    // The parameters findForAgents() sets, its conditions and limit given back by reference
    /**
     * @param-out list<string> $wheres
     * @param-out ?int         $limit
     *
     * @return array<string, mixed>
     */
    private function find(?string $search, bool $withoutAlt, int $max, ?array &$wheres, ?int &$limit): array
    {
        $wheres = [];
        $parameters = [];
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        foreach (['addSelect', 'leftJoin', 'orderBy'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('setMaxResults')->willReturnCallback(static function (int $value) use ($queryBuilder, &$limit): QueryBuilder {
            $limit = $value;

            return $queryBuilder;
        });
        $queryBuilder->method('andWhere')->willReturnCallback(static function (string $where) use ($queryBuilder, &$wheres): QueryBuilder {
            $wheres[] = $where;

            return $queryBuilder;
        });
        $queryBuilder->method('setParameter')->willReturnCallback(static function (string $name, mixed $value) use ($queryBuilder, &$parameters): QueryBuilder {
            $parameters[$name] = $value;

            return $queryBuilder;
        });
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createPartialMock(MediaRepository::class, ['createQueryBuilder']);
        $repository->expects($this->once())->method('createQueryBuilder')->with('m')->willReturn($queryBuilder);

        $this->assertSame([], $repository->findForAgents($search, $withoutAlt, $max));

        return $parameters;
    }
}
