<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Twig\OwnedBlocksExtension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Empties an owner's run of blocks (see OwnedBlocksExtension) when it changes: a block added, removed or moved only writes the join table, which neither the owner nor the Block reports - the owner's own collection being scheduled for update is the one trace of it. The owner saved empties it too. Every block edited in place already reaches the entry through its own "block_{id}" tag
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class OwnedBlocksCacheListener
{
    /** @var array<string, true> */
    private array $tags = [];

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $this->mark($collection->getOwner());
        }

        foreach ([...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            $this->mark($entity);
        }
    }

    // After the rows are written, never before, once per flush
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->tags) {
            return;
        }

        $tags = array_keys($this->tags);
        $this->tags = [];
        $this->cache->invalidateTags($tags);
    }

    private function mark(?object $entity): void
    {
        if ($entity instanceof HasBlocksInterface) {
            $this->tags[OwnedBlocksExtension::ownerTag($entity)] = true;
        }
    }
}
