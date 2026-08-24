<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Service\LegalModelCacheTagProvider;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Drops the cached legal_model renders when one of the configs they resolve inside themselves changes - BlockCacheInvalidationListener only ever sees Block/Media, so without this an edited contact email would never reach an already published legal notice
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class LegalPlaceholderCacheListener
{
    // The settings a model reads without ever printing, which no placeholder list knows about: a model branching on one of them depends on it exactly as much as on a marker it substitutes, and a cached render that outlives the change says what the site stopped doing
    private const array CONDITION_SLUGS = [
        'site-has-accounts',
    ];

    // The per-entity events only raise this flag: the back-office saves the whole config group at once, which would otherwise invalidate the same tag once per row, inside the transaction
    private bool $stale = false;

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly LegalModelPlaceholders $placeholders,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->markIfPlaceholderConfig($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->markIfPlaceholderConfig($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->markIfPlaceholderConfig($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->stale) {
            return;
        }

        $this->stale = false;
        $this->cache->invalidateTags([LegalModelCacheTagProvider::CACHE_TAG]);
    }

    // Only the slugs the models are actually allowed to print, plus the ones they branch on, so renaming an unrelated config costs nothing
    private function markIfPlaceholderConfig(object $entity): void
    {
        if (!$entity instanceof Config) {
            return;
        }

        if (in_array($entity->getSlug(), [...$this->placeholders->slugs(), ...self::CONDITION_SLUGS], true)) {
            $this->stale = true;
        }
    }
}
