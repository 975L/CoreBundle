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
use c975L\UiBundle\Registry\BlockRegistry;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postLoad)]
class BlockLabelListener
{
    public function __construct(private readonly BlockRegistry $registry)
    {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Block || null === $entity->getKind() || !$this->registry->has($entity->getKind())) {
            return;
        }

        $entity->setLabel($this->registry->getLabel($entity->getKind()));
    }
}
