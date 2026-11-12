<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Repository;

use c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// One method per case of the bulk query rule, read by CodeRulesTestCaseTest
class ItemRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // Bad: a comment saying the cache is invalidated invalidates nothing
    public function purge(): int
    {
        return (int) $this->entityManager->createQueryBuilder()->delete(Item::class, 'i')->getQuery()->execute();
    }

    // Bad: plain SQL bypasses the listeners as well
    public function purgeRaw(): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement('DELETE FROM item WHERE stock = 0');
    }

    public function rename(string $name): int
    {
        $updated = (int) $this->entityManager->createQueryBuilder()->update(Item::class, 'i')->set('i.name', ':name')->setParameter('name', $name)->getQuery()->execute();
        $this->cache->invalidateTags(['item']);

        return $updated;
    }

    // Not a bulk query: an entity removed goes through the listeners
    public function remove(Item $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }
}
