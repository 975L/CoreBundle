<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    // The row the unique constraint protects, i.e. this voter's own vote on this thing - what a new vote updates rather than duplicates
    public function findOneByVoter(string $ownerType, int $ownerId, string $voter): ?Rating
    {
        return $this->findOneBy(['ownerType' => $ownerType, 'ownerId' => $ownerId, 'voter' => $voter]);
    }

    /**
     * What a page prints: the average and how many voted. Aggregated by the database rather than by hydrating the rows, a popular book holding thousands of them.
     *
     * @return array{average: float, count: int} average is 0.0 when nobody voted, the templates reading count to tell that apart from an actual zero
     */
    public function getAggregate(string $ownerType, int $ownerId): array
    {
        return $this->getAggregates($ownerType, [$ownerId])[$ownerId] ?? ['average' => 0.0, 'count' => 0];
    }

    /**
     * The same aggregate for a whole set of things at once - a catalog page listing thirty books would otherwise run thirty queries to print thirty averages.
     *
     * @param int[] $ownerIds
     *
     * @return array<int, array{average: float, count: int}> keyed by owner id, ids with no vote at all being absent
     */
    public function getAggregates(string $ownerType, array $ownerIds): array
    {
        if ([] === $ownerIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('r.ownerId AS ownerId, AVG(r.value) AS average, COUNT(r.id) AS total')
            ->andWhere('r.ownerType = :ownerType')
            ->andWhere('r.ownerId IN (:ownerIds)')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerIds', $ownerIds)
            ->groupBy('r.ownerId')
            ->getQuery()
            ->getArrayResult()
        ;

        $aggregates = [];
        foreach ($rows as $row) {
            $aggregates[(int) $row['ownerId']] = [
                'average' => round((float) $row['average'], 1),
                'count' => (int) $row['total'],
            ];
        }

        return $aggregates;
    }

    // Called when the rated thing is deleted for good, not when it is only trashed: a restored book must find its votes again. There is no foreign key to cascade from, $ownerType being a name and not a relation, so whoever deletes says so
    public function deleteForOwner(string $ownerType, int $ownerId): int
    {
        return $this->deleteForOwners($ownerType, [$ownerId]);
    }

    /**
     * The same deletion for a whole set of things at once - deleting a gallery of two thousand photos would otherwise run two thousand queries.
     *
     * @param int[] $ownerIds
     */
    public function deleteForOwners(string $ownerType, array $ownerIds): int
    {
        if ([] === $ownerIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.ownerType = :ownerType')
            ->andWhere('r.ownerId IN (:ownerIds)')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerIds', $ownerIds)
            ->getQuery()
            ->execute()
        ;
    }
}
