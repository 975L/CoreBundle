<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\AiSearchAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiSearchAnswer>
 */
class AiSearchAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiSearchAnswer::class);
    }

    public function findOneByQuestionHash(string $questionHash): ?AiSearchAnswer
    {
        return $this->findOneBy(['questionHash' => $questionHash]);
    }

    // Questions nobody asked again since the date, returning how many went
    public function deleteNotAskedSince(\DateTimeImmutable $date): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->where('a.updatedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
