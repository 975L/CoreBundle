<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Translation>
 */
class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    /**
     * What a whole page needs, in one query: every field of every owner given, said in that language.
     *
     * Read as scalars rather than hydrated: a render only ever wants the strings, and hydrating a few hundred
     * entities to throw them away is the cost this table was designed to avoid.
     *
     * @param list<int> $ownerIds
     *
     * @return array<int, array<string, string|null>> owner id => field => value
     */
    public function findValues(string $ownerType, array $ownerIds, string $locale): array
    {
        if ([] === $ownerIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.ownerId AS ownerId, t.field AS field, t.value AS value')
            ->where('t.ownerType = :ownerType')
            ->andWhere('t.ownerId IN (:ownerIds)')
            ->andWhere('t.locale = :locale')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerIds', $ownerIds)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getArrayResult();

        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['ownerId']][(string) $row['field']] = $row['value'];
        }

        return $values;
    }

    /**
     * Every language one owner has been given, for the screen that writes them.
     *
     * @return array<string, array<string, string|null>> locale => field => value
     */
    public function findByOwner(string $ownerType, int $ownerId): array
    {
        $values = [];
        foreach ($this->findBy(['ownerType' => $ownerType, 'ownerId' => $ownerId]) as $translation) {
            $values[$translation->getLocale()][$translation->getField()] = $translation->getValue();
        }

        return $values;
    }

    // The row the unique constraint protects, which a write updates rather than duplicates
    public function findOneField(string $ownerType, int $ownerId, string $field, string $locale): ?Translation
    {
        return $this->findOneBy(['ownerType' => $ownerType, 'ownerId' => $ownerId, 'field' => $field, 'locale' => $locale]);
    }

    // Everything an owner leaves behind when it is deleted: without a foreign key, nothing else would take these away
    public function deleteByOwner(string $ownerType, int $ownerId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.ownerType = :ownerType')
            ->andWhere('t.ownerId = :ownerId')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->execute();
    }
}
