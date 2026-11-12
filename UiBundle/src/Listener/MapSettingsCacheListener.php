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
use c975L\UiBundle\Service\MapCacheTagProvider;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Drops the cached map blocks when the map service or its key changes, so a key added does not leave the published maps saying they cannot be drawn
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class MapSettingsCacheListener
{
    use PostFlushTagInvalidationTrait;

    // Every entry the map providers read, a provider added later bringing its keys under the same prefix
    private const string PREFIX = 'ui-map-';

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->markIfMapConfig($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->markIfMapConfig($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->markIfMapConfig($args->getObject());
    }

    // Only the map's own entries, so saving any other setting costs nothing, invalidated once after the commit however many the back-office saved together
    private function markIfMapConfig(object $entity): void
    {
        if ($entity instanceof Config && str_starts_with((string) $entity->getSlug(), self::PREFIX)) {
            $this->queueTags([MapCacheTagProvider::CACHE_TAG]);
        }
    }

    private function tagCache(): TagAwareCacheInterface
    {
        return $this->cache;
    }
}
