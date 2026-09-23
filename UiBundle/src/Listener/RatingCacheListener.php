<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Lets a fragment printing an owner's rating - the widget's average, a rich result's aggregateRating - be cached: tagged with cacheTag(), it is emptied by any vote on that owner, a review's score included (see RatingService::store()). The voter's own score is never in it, read from the browser
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class RatingCacheListener
{
    /** @var array<string, true> */
    private array $tags = [];

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    // The tag a fragment showing this owner's rating carries - "ui_rating_cache_tag()" in a template
    public static function cacheTag(string $ownerType, int $ownerId): string
    {
        return 'ui_rating_' . $ownerType . '_' . $ownerId;
    }

    // The tag a fragment showing the ratings of several owners of a type carries - a grid of cards, whose owners are only known once it is drawn - "ui_rating_type_cache_tag()" in a template
    public static function typeCacheTag(string $ownerType): string
    {
        return 'ui_rating_' . $ownerType;
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->mark($args->getObject());
    }

    // After the rows are written, once per flush
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
        if ($entity instanceof Rating) {
            $this->tags[self::cacheTag($entity->getOwnerType(), $entity->getOwnerId())] = true;
            $this->tags[self::typeCacheTag($entity->getOwnerType())] = true;
        }
    }
}
