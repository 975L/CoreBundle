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

    // Initializes the nested blocks of a whole tree, and their medias, in one query per level of depth - what every owner of blocks (a Page, a Menu) calls right after reading them
    // A container's slots are a lazy collection, so a render walking the tree (the templates themselves, BlockCacheTagResolver, MenuExtension) reads one query per block otherwise, the leaves included: a collection nobody joined is queried to be found empty. Joining one level in the owner's own query only moves the problem one step down, a slot's slots being lazy in their turn
    /** @param iterable<Block> $blocks */
    public function preloadSlots(iterable $blocks): void
    {
        $ids = [];
        foreach ($blocks as $block) {
            $ids[] = $block->getId();
        }

        // Ends on the level that has no slots at all, its own collections having just been initialized empty by the query that looked for them. Blocks already visited are dropped on the way, so a row somehow made its own ancestor loops nowhere
        $visited = [];
        while ([] !== $ids) {
            $ids = array_values(array_diff(array_unique($ids), $visited));
            if ([] === $ids) {
                return;
            }

            $visited = array_merge($visited, $ids);

            // Fetch-joined on rows the entity manager already holds: this is what marks their slots initialized, hydrating the next level with its medias at the same time
            $level = $this->createQueryBuilder('b')
                ->select('b, s, sm')
                ->leftJoin('b.slots', 's')
                ->leftJoin('s.medias', 'sm')
                ->andWhere('b.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult()
            ;

            $ids = [];
            foreach ($level as $block) {
                foreach ($block->getSlots() as $slot) {
                    $ids[] = $slot->getId();
                }
            }
        }
    }
}
