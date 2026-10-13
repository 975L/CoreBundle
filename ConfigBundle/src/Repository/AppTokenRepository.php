<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AppToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppToken>
 */
class AppTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppToken::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?AppToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function findOneByCodeHash(string $codeHash): ?AppToken
    {
        return $this->findOneBy(['codeHash' => $codeHash]);
    }

    // Every token of an account at once, for one that is anonymized rather than deleted
    public function deleteOfUser(UserInterface $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute()
        ;
    }

    // The codes nobody came back for: rows that never became a token
    public function deleteExpiredCodes(\DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.tokenHash IS NULL')
            ->andWhere('t.codeExpiration < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute()
        ;
    }
}
