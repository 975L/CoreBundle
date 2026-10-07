<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

// Takes a broken link off the list once a redirect answers for it - whether written by hand from that very list or by a satellite bundle marking a removed page as gone (see GalleryBundle). The url no longer 404s, so the row would only list something already dealt with
#[AsDoctrineListener(event: Events::postPersist)]
class NotFoundRedirectListener
{
    public function __construct(private readonly NotFoundRepository $notFoundRepository)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Redirect || null === $entity->getFromPath()) {
            return;
        }

        $this->notFoundRepository->deleteByPath($entity->getFromPath());
    }
}
