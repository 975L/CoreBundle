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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

// Empties the configuration's own cache once a flush writing a Config goes through, ahead of every other postFlush listener: a bundle emptying the tag of a block that prints a setting (shop-currency) would otherwise leave a request in between to cache that block again from the old value
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush, priority: self::POST_FLUSH_PRIORITY)]
class ConfigCacheListener
{
    // Above the default 0 every other bundle's postFlush listener runs at
    public const int POST_FLUSH_PRIORITY = 100;

    // Raised by the per-entity events, so a back-office group saved at once empties the cache once
    private bool $stale = false;

    public function __construct(private readonly ConfigServiceInterface $configService)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    // Lowered before the call, another listener's postFlush flushing again bringing us straight back here
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->stale) {
            return;
        }

        $this->stale = false;
        $this->configService->invalidateCache();
    }

    private function mark(object $entity): void
    {
        if ($entity instanceof Config) {
            $this->stale = true;
        }
    }
}
