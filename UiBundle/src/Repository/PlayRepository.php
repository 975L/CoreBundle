<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Play;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Play>
 */
class PlayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Play::class);
    }

    // How many times a holder played each thing, under the keys the favorites use
    /** @return array<string, int> "book:12" => 3 */
    public function countsByHolder(string $holder): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.ownerType AS ownerType', 'p.ownerId AS ownerId', 'COUNT(p.id) AS plays')
            ->andWhere('p.holder = :holder')
            ->setParameter('holder', $holder)
            ->groupBy('p.ownerType', 'p.ownerId')
            ->getQuery()
            ->getArrayResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['ownerType'] . ':' . $row['ownerId']] = (int) $row['plays'];
        }

        return $counts;
    }

    // Hands what a device played over to the account its holder signed in to. Nothing to reconcile, unlike the favorites: plays add up
    public function moveHolder(string $from, string $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->update()
            ->set('p.holder', ':to')
            ->andWhere('p.holder = :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute()
        ;
    }
}
