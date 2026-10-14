<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\PlayTotal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayTotal>
 */
class PlayTotalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayTotal::class);
    }

    // One more play, in one statement: two listens ending at once must both be counted, which reading the count then writing it back would not guarantee
    public function increment(string $ownerType, int $ownerId): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO site_play_total (owner_type, owner_id, plays) VALUES (:ownerType, :ownerId, 1) ON DUPLICATE KEY UPDATE plays = plays + 1',
            ['ownerType' => $ownerType, 'ownerId' => $ownerId]
        );
    }

    // How many times each of these things was played, the ones never played being absent
    /**
     * @param int[] $ownerIds
     *
     * @return array<int, int> owner id => plays
     */
    public function totals(string $ownerType, array $ownerIds): array
    {
        if ([] === $ownerIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.ownerId AS ownerId', 't.plays AS plays')
            ->andWhere('t.ownerType = :ownerType')
            ->andWhere('t.ownerId IN (:ownerIds)')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerIds', $ownerIds)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_column(array_map(static fn (array $row): array => ['ownerId' => (int) $row['ownerId'], 'plays' => (int) $row['plays']], $rows), 'plays', 'ownerId');
    }
}
