<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\UrlMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\UrlMetadata>
 */
class UrlMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrlMetadata::class);
    }

    public function findOneByPath(string $path): ?UrlMetadata
    {
        return $this->findOneBy(['path' => $path]);
    }

    // Every row at once, keyed by the path it describes - what UrlMetadataSynchronizer compares the declared urls against. These rows are counted in dozens (one per listing, per filtered listing, per tool page), never in thousands: an url with an entity behind it is described by that entity and has no row here
    /**
     * @return array<string, UrlMetadata>
     */
    public function findAllIndexedByPath(): array
    {
        $indexed = [];
        foreach ($this->findBy([], ['path' => 'ASC']) as $urlMetadata) {
            $indexed[(string) $urlMetadata->getPath()] = $urlMetadata;
        }

        return $indexed;
    }

    // The ids alone, keyed by path - what UrlMetadataResolver keeps in the cache pool: scalars survive being serialized, an entity with a lazy ogImage would come back as a detached ghost
    /**
     * @return array<string, int>
     */
    public function findIdsIndexedByPath(): array
    {
        $indexed = [];
        foreach ($this->createQueryBuilder('u')->select('u.id', 'u.path')->getQuery()->getArrayResult() as $row) {
            $indexed[(string) $row['path']] = (int) $row['id'];
        }

        return $indexed;
    }
}
