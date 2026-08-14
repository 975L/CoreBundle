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

    // Every row at once, keyed by the path it describes - what UrlMetadataResolver loads on the first lookup of a request. These rows are counted in dozens (one per listing, per filtered listing, per tool page), never in thousands: an url with an entity behind it is described by that entity and has no row here
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
}
