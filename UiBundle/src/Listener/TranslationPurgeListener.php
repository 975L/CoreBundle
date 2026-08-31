<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a block's translations away with the block: they name their owner rather than pointing at it (see Translation), so no foreign key takes them along
// Left behind, a removed block's translated title would stay in the table for good, and a new block landing on the same id would inherit it
#[AsDoctrineListener(event: Events::postRemove)]
class TranslationPurgeListener
{
    public function __construct(private readonly TranslationRepository $repository)
    {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Block || null === $entity->getId()) {
            return;
        }

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner(Translation::OWNER_BLOCK, $entity->getId());
    }
}
