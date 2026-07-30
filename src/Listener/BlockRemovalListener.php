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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

// Removing a Block from a HasBlocksInterface collection only deletes the join row (ManyToMany), so the orphaned Block (and cascade to its Medias/files) must be removed explicitly here
#[AsDoctrineListener(event: Events::preFlush)]
class BlockRemovalListener
{
    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();

        foreach ($em->getUnitOfWork()->getIdentityMap() as $className => $entities) {
            if (!is_a($className, HasBlocksInterface::class, true)) {
                continue;
            }

            foreach ($entities as $entity) {
                foreach ($entity->popPendingBlockRemovals() as $block) {
                    $em->remove($block);
                }
            }
        }
    }
}
