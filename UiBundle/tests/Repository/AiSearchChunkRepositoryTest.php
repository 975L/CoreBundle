<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Entity\AiSearchChunk;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The stemming MariaDB's FULLTEXT index doesn't do: "contacter" has to find the "Contact" page
class AiSearchChunkRepositoryTest extends TestCase
{
    public function testAQuestionKeepsTheStemsOfTheWordsThatSaySomething(): void
    {
        $this->assertSame('conta*', AiSearchChunkRepository::booleanQuery('Comment vous contacter ?'));
        $this->assertSame('tarif* site* vitri*', AiSearchChunkRepository::booleanQuery('Quels sont vos tarifs pour un site vitrine ?'));
    }

    // "+", "-", quotes and parentheses mean something in BOOLEAN MODE, and a visitor's question is no query
    public function testTheOperatorsAVisitorTypesAreDropped(): void
    {
        $this->assertSame('horai* samed*', AiSearchChunkRepository::booleanQuery('+horaires -"samedi" (horaires)'));
    }

    public function testAQuestionSayingNothingLeavesNothingToLookFor(): void
    {
        $this->assertSame('', AiSearchChunkRepository::booleanQuery('Comment vous ?'));
    }

    // The search asks it on every page: read once per request, then once per pool until the index is rebuilt
    public function testCurrentVersionIsReadOnceThroughThePool(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());

        $this->assertSame('v3', $this->repository($cache, 'v3', 1)->currentVersion());
        $this->assertSame('v3', $this->repository($cache, 'v9', 0)->currentVersion());
    }

    // An index never built is cached too, as the empty string a miss could not be told from
    public function testCurrentVersionIsNullWhileNothingWasIndexed(): void
    {
        $repository = $this->repository(new TagAwareAdapter(new ArrayAdapter()), null, 1);

        $this->assertNull($repository->currentVersion());
        $this->assertNull($repository->currentVersion());
    }

    // A repository whose one query answers $version, asked exactly $queries times
    private function repository(TagAwareCacheInterface $cache, ?string $version, int $queries): AiSearchChunkRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(AiSearchChunk::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $query = $this->createStub(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null === $version ? null : ['indexVersion' => $version]);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(AiSearchChunkRepository::class)
            ->setConstructorArgs([$registry, $cache])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->expects($this->exactly($queries))->method('createQueryBuilder')->willReturn($queryBuilder);

        return $repository;
    }
}
