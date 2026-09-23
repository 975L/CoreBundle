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
use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\EventSubscriber\RedirectSubscriber;
use c975L\ConfigBundle\Service\UrlMetadataResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Empties the tag this bundle caches a table under when one of its rows is written or removed: the url descriptions (UrlMetadataResolver, and every {% cache %} fragment printing them) and the redirects (RedirectSubscriber)
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class CacheTagListener
{
    // Which tag each entity empties
    private const array TAGS = [
        UrlMetadata::class => UrlMetadataResolver::CACHE_TAG,
        Redirect::class => RedirectSubscriber::CACHE_TAG,
    ];

    // The per-entity events only collect the tags: UrlMetadataSynchronizer or an import writes many rows in one flush, which would otherwise invalidate the same tag once per row
    /** @var array<string, true> */
    private array $tags = [];

    public function __construct(private readonly TagAwareCacheInterface $cache)
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

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->tags) {
            return;
        }

        $tags = array_keys($this->tags);
        $this->tags = [];
        $this->cache->invalidateTags($tags);
    }

    private function mark(object $entity): void
    {
        // instanceof rather than a lookup on the class name, a proxy carrying a class of its own
        foreach (self::TAGS as $class => $tag) {
            if ($entity instanceof $class) {
                $this->tags[$tag] = true;
            }
        }
    }
}
