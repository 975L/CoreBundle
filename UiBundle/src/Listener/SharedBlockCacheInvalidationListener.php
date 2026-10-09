<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Entity\SharedBlock;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// A shared block created after a pointer naming its slug was cached - an import, a slug typed before the content - reaches that pointer through the slug's own tag (see BlockCacheTagResolver). Its later edits need nothing here: OwnedBlocksCacheListener and BlockCacheInvalidationListener already empty its owner tag and its blocks' tags
#[AsDoctrineListener(event: Events::postPersist)]
class SharedBlockCacheInvalidationListener
{
    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof SharedBlock && null !== $entity->getSlug()) {
            $this->cache->invalidateTags(['shared_block_' . $entity->getSlug()]);
        }
    }
}
