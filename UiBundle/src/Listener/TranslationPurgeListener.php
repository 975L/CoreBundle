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
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a row's translations away with the row: they name their owner rather than pointing at it (see Translation), so no foreign key takes them along
// Left behind, a removed block's translated title would stay in the table for good, and a new block landing on the same id would inherit it
// Form fields and outputs are removed the same way, without ever being deleted by hand: taken out of their form's collection, Doctrine's orphanRemoval deletes them, which is a removal like any other and fires this
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class TranslationPurgeListener
{
    // The rows being removed and what their translations are filed under, noted while each still has its id
    /** @var \WeakMap<object, array{string, int}> */
    private \WeakMap $pending;

    public function __construct(private readonly TranslationRepository $repository)
    {
        $this->pending = new \WeakMap();
    }

    // Doctrine hands a removed row's id back to null before postRemove is dispatched, so the id is read here while it still exists
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        $ownerType = match (true) {
            $entity instanceof Block => Translation::OWNER_BLOCK,
            $entity instanceof FormField => Translation::OWNER_FORM_FIELD,
            $entity instanceof FormOutput => Translation::OWNER_FORM_OUTPUT,
            // A media taken out of its block's collection is orphan-removed the same way a form field is, and its caption has to go with it
            $entity instanceof Media => Translation::OWNER_MEDIA,
            default => null,
        };

        if (null === $ownerType || null === $entity->getId()) {
            return;
        }

        $this->pending[$entity] = [$ownerType, $entity->getId()];
    }

    // Purged once the row is gone and inside the flush's own transaction, so a removal the database refuses keeps its translations
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!isset($this->pending[$entity])) {
            return;
        }

        [$ownerType, $id] = $this->pending[$entity];
        unset($this->pending[$entity]);

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner($ownerType, $id);
    }
}
