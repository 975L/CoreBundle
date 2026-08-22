<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    // The row the unique constraint protects, i.e. this visitor's own entry for this thing - what a second click removes rather than duplicates
    public function findOneByHolder(string $ownerType, int $ownerId, string $holder): ?Favorite
    {
        return $this->findOneBy(['ownerType' => $ownerType, 'ownerId' => $ownerId, 'holder' => $holder]);
    }

    /**
     * A whole list in one query, newest first: what the visitor put aside last is what they are coming back for.
     *
     * Grouped by kind rather than returned flat, because that is how it is drawn: one provider resolves one kind of thing, and a list of thirty entries spread over three kinds runs three queries and not thirty (see FavoriteItemRegistry).
     *
     * @return array<string, int[]> owner type => its ids, in the order the list shows them
     */
    public function findIdsByHolder(string $holder): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('f.ownerType AS ownerType, f.ownerId AS ownerId')
            ->andWhere('f.holder = :holder')
            ->setParameter('holder', $holder)
            ->orderBy('f.createdAt', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;

        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['ownerType']][] = (int) $row['ownerId'];
        }

        return $ids;
    }

    // How many things a visitor is holding, for the counter a navbar prints next to its icon
    public function countForHolder(string $holder): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.holder = :holder')
            ->setParameter('holder', $holder)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * The things this holder already has, among those asked about - what the merge below reads to tell an entry it has to move from one it has to drop.
     *
     * @param array<string, int[]> $ids owner type => its ids
     *
     * @return array<string, int[]> the same shape, holding only what was found
     */
    public function findExistingAmong(string $holder, array $ids): array
    {
        $existing = [];

        foreach ($ids as $ownerType => $ownerIds) {
            if ([] === $ownerIds) {
                continue;
            }

            $rows = $this->createQueryBuilder('f')
                ->select('f.ownerId AS ownerId')
                ->andWhere('f.holder = :holder')
                ->andWhere('f.ownerType = :ownerType')
                ->andWhere('f.ownerId IN (:ownerIds)')
                ->setParameter('holder', $holder)
                ->setParameter('ownerType', $ownerType)
                ->setParameter('ownerIds', $ownerIds)
                ->getQuery()
                ->getArrayResult()
            ;

            if ([] !== $rows) {
                $existing[$ownerType] = array_map(static fn (array $row): int => (int) $row['ownerId'], $rows);
            }
        }

        return $existing;
    }

    // Hands every entry of one holder over to another, which is what a visitor logging in does with the list their browser was holding.
    // Two bulk queries rather than a row-by-row update: what the account already held would break the unique index, so those entries are dropped first and only the rest changes hands
    public function moveHolder(string $from, string $to): int
    {
        $moving = $this->findIdsByHolder($from);

        if ([] === $moving) {
            return 0;
        }

        foreach ($this->findExistingAmong($to, $moving) as $ownerType => $ownerIds) {
            $this->createQueryBuilder('f')
                ->delete()
                ->andWhere('f.holder = :holder')
                ->andWhere('f.ownerType = :ownerType')
                ->andWhere('f.ownerId IN (:ownerIds)')
                ->setParameter('holder', $from)
                ->setParameter('ownerType', $ownerType)
                ->setParameter('ownerIds', $ownerIds)
                ->getQuery()
                ->execute()
            ;
        }

        return (int) $this->createQueryBuilder('f')
            ->update()
            ->set('f.holder', ':to')
            ->andWhere('f.holder = :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute()
        ;
    }

    // Called when the thing put aside is deleted for good, not when it is only trashed: a restored product must find itself in the lists it was in. There is no foreign key to cascade from, $ownerType being a name and not a relation, so whoever deletes says so
    public function deleteForOwner(string $ownerType, int $ownerId): int
    {
        return $this->deleteForOwners($ownerType, [$ownerId]);
    }

    /**
     * The same deletion for a whole set of things at once.
     *
     * @param int[] $ownerIds
     */
    public function deleteForOwners(string $ownerType, array $ownerIds): int
    {
        if ([] === $ownerIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('f')
            ->delete()
            ->andWhere('f.ownerType = :ownerType')
            ->andWhere('f.ownerId IN (:ownerIds)')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerIds', $ownerIds)
            ->getQuery()
            ->execute()
        ;
    }
}
