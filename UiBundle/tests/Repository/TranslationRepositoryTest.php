<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// What a whole page needs in one query, reshaped into what a render reads: owner id => field => value
class TranslationRepositoryTest extends TestCase
{
    // Read as scalars and regrouped here rather than hydrated, which is the cost this table was designed to avoid
    #[AllowMockObjectsWithoutExpectations]
    public function testFindValuesGroupsEveryFieldUnderItsOwner(): void
    {
        $repository = $this->createRepositoryReturning([
            ['ownerId' => 7, 'field' => 'title', 'value' => 'Hola'],
            ['ownerId' => 7, 'field' => 'content', 'value' => 'Buenos dias'],
            ['ownerId' => 9, 'field' => 'title', 'value' => 'Adios'],
        ]);

        $values = $repository->findValues(Translation::OWNER_BLOCK, [7, 9], 'es');

        $this->assertSame([
            7 => ['title' => 'Hola', 'content' => 'Buenos dias'],
            9 => ['title' => 'Adios'],
        ], $values);
    }

    // A block whose entry was opened then left blank: the null is kept here, ContentTranslator being the one that refuses to lay it over the original
    #[AllowMockObjectsWithoutExpectations]
    public function testFindValuesKeepsAFieldStoredEmpty(): void
    {
        $repository = $this->createRepositoryReturning([['ownerId' => 7, 'field' => 'title', 'value' => null]]);

        $this->assertSame([7 => ['title' => null]], $repository->findValues(Translation::OWNER_BLOCK, [7], 'es'));
    }

    // No ids means no query at all: a page whose blocks are all unsaved would otherwise send an empty IN()
    public function testFindValuesAsksNothingWhenNoOwnerIsGiven(): void
    {
        $repository = $this->createPartialMock(TranslationRepository::class, ['createQueryBuilder']);
        $repository->expects($this->never())->method('createQueryBuilder');

        $this->assertSame([], $repository->findValues(Translation::OWNER_BLOCK, [], 'es'));
    }

    // The screen that writes them reads one owner across every language it has been given
    #[AllowMockObjectsWithoutExpectations]
    public function testFindByOwnerGroupsOneOwnerByLanguage(): void
    {
        $repository = $this->createPartialMock(TranslationRepository::class, ['findBy']);
        $repository->method('findBy')->willReturn([
            new Translation(Translation::OWNER_BLOCK, 7, 'title', 'es')->setValue('Hola'),
            new Translation(Translation::OWNER_BLOCK, 7, 'content', 'es')->setValue('Buenos dias'),
            new Translation(Translation::OWNER_BLOCK, 7, 'title', 'en')->setValue('Hello'),
        ]);

        $this->assertSame([
            'es' => ['title' => 'Hola', 'content' => 'Buenos dias'],
            'en' => ['title' => 'Hello'],
        ], $repository->findByOwner(Translation::OWNER_BLOCK, 7));
    }

    // A repository whose single query answers the given scalar rows
    private function createRepositoryReturning(array $rows): TranslationRepository
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createPartialMock(TranslationRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $repository;
    }
}
