<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigTranslator;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

// Takes a setting's translations away with the setting, the way SiteBundle's PageTranslationPurgeListener does for a page
// Translations name their owner rather than pointing at it (see Translation), so no foreign key takes them along: a setting a bundle stopped declaring - which "c975l:config:prune" is there to remove - would leave its translated sentence in the table, and the next setting landing on that id would inherit it
// A site declaring one language never gets here with anything to delete, having stored none
#[AsDoctrineListener(event: Events::postRemove)]
class ConfigTranslationPurgeListener
{
    public function __construct(private readonly TranslationRepository $repository)
    {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Config || null === $entity->getId()) {
            return;
        }

        // A DQL delete rather than a remove(): a flush is already running, and nothing here needs hydrating
        $this->repository->deleteByOwner(ConfigTranslator::OWNER, $entity->getId());
    }
}
