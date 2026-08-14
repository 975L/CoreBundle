<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\Redirect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\Redirect>
 */
class RedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirect::class);
    }

    public function findOneByFromPath(string $fromPath): ?Redirect
    {
        return $this->findOneBy(['fromPath' => $fromPath]);
    }

    // The row matching this path exactly plus every "/prefix/*" row, in a single query - RedirectSubscriber runs on every request, so this must not cost more than the one lookup it replaces. Which of them actually applies is decided there (see RedirectSubscriber::resolve()): "*" is a convention of this bundle, not a SQL wildcard, and no two engines write a prefix comparison the same way
    /**
     * @return Redirect[]
     */
    public function findCandidatesForPath(string $path): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.fromPath = :path OR r.fromPath LIKE :wildcard')
            ->setParameter('path', $path)
            ->setParameter('wildcard', '%*')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Redirect[]
     */
    public function findByToUrl(string $toUrl): array
    {
        return $this->findBy(['toUrl' => $toUrl]);
    }

    // Every row sitting below a path - what sweeps the rows an url tree leaves behind once the tree itself is gone, a single wildcard row covering it from then on (see GalleryBundle's GalleryUrlRedirector, which deletes a whole category that way)
    // The LIKE wildcards are escaped rather than trusted: a "_" in the prefix would match any character in its place, and these rows are removed, not merely read
    /**
     * @return Redirect[]
     */
    public function findByFromPathPrefix(string $prefix): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.fromPath LIKE :prefix')
            ->setParameter('prefix', addcslashes($prefix, '%_') . '%')
            ->getQuery()
            ->getResult()
        ;
    }
}
