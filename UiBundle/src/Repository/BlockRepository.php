<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Block;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Block>
 */
class BlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Block::class);
    }

    // First block of a given kind, wherever it lives (attached to a page or not) - used by consumers that render a single well-known block outside the page-content flow (e.g. SiteBundle's footer "social links"), without needing a dedicated singleton entity/table for it
    public function findOneByKind(string $kind): ?Block
    {
        return $this->findOneBy(['kind' => $kind]);
    }

    /**
     * Every block of a given kind, whatever entity owns them - what a screen listing one kind site-wide works on (see LegalModelController), no owner needed and therefore no dependency on the bundle providing one.
     *
     * @return Block[]
     */
    public function findByKind(string $kind): array
    {
        return $this->findBy(['kind' => $kind], ['id' => 'ASC']);
    }
}
