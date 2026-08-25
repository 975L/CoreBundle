<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Repository;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// What findLatestPerUrlAndKindIn() answers for: the row a run reads back for each (url, kind) of a whole batch of urls, in one query (see ExternalLinkCheckSchedule)
class HealthCheckResultRepositoryTest extends TestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testFindLatestPerUrlAndKindInKeepsTheMostRecentRowOfEachPair(): void
    {
        $latest = $this->row('/a', 'urls-site', '2026-08-25');
        $older = $this->row('/a', 'urls-site', '2026-07-25');

        $found = $this->find(['/a'], [$latest, $older]);

        $this->assertSame([$latest], $found);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindLatestPerUrlAndKindInTellsTwoKindsOfTheSameUrlApart(): void
    {
        $quality = $this->row('/a', 'urls-site', '2026-08-25');
        $headers = $this->row('/a', 'security-headers', '2026-08-24');

        $found = $this->find(['/a'], [$quality, $headers]);

        // Both kept: the same url is checked by several providers, and only one of them carries what the caller is looking for
        $this->assertSame([$quality, $headers], $found);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindLatestPerUrlAndKindInAsksForEveryUrlAtOnce(): void
    {
        $asked = [];
        $this->find(['/a', '/b'], [], $asked);

        $this->assertSame([['/a', '/b']], $asked);
    }

    public function testFindLatestPerUrlAndKindInQueriesNothingOnAnEmptyList(): void
    {
        $repository = $this->createPartialMock(HealthCheckResultRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame([], $repository->findLatestPerUrlAndKindIn([]));
    }

    // Runs findLatestPerUrlAndKindIn() against a repository whose query answers $rows, collecting into $asked the parameters each query was given
    /**
     * @param list<string>            $urls
     * @param list<HealthCheckResult> $rows
     * @param list<list<string>>|null $asked
     *
     * @return HealthCheckResult[]
     */
    private function find(array $urls, array $rows, ?array &$asked = null): array
    {
        $asked ??= [];

        $repository = $this->createPartialMock(HealthCheckResultRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturnCallback(function () use (&$asked, $rows): QueryBuilder {
            $query = $this->createMock(Query::class);
            $query->method('getResult')->willReturn($rows);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('andWhere')->willReturnSelf();
            $queryBuilder->method('orderBy')->willReturnSelf();
            $queryBuilder->method('addOrderBy')->willReturnSelf();
            $queryBuilder->method('getQuery')->willReturn($query);
            $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use (&$asked, $queryBuilder): QueryBuilder {
                $asked[] = $value;

                return $queryBuilder;
            });

            return $queryBuilder;
        });

        return $repository->findLatestPerUrlAndKindIn($urls);
    }

    private function row(string $url, string $kind, string $checkedAt): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setUrl($url)
            ->setKind($kind)
            ->setStatus(HealthCheckResult::STATUS_OK)
            ->setSummary('')
            ->setCheckedAt(new \DateTime($checkedAt));
    }
}
