<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\EventSubscriber\RedirectSubscriber;
use c975L\ConfigBundle\Listener\CacheTagListener;
use c975L\ConfigBundle\Service\UrlMetadataResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CacheTagListenerTest extends TestCase
{
    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    private function row(): UrlMetadata
    {
        return new UrlMetadata()->setPath('/animaux');
    }

    // An edited title must reach the cached map and every fragment printing it
    public function testEditingARowInvalidatesTheTagOnFlush(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([UrlMetadataResolver::CACHE_TAG]);

        $listener = new CacheTagListener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->row(), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    // A new row changes the map itself: the path it describes now has an id
    public function testPersistedAndRemovedRowsAlsoInvalidate(): void
    {
        foreach (['postPersist', 'postRemove'] as $event) {
            $cache = $this->createMock(TagAwareCacheInterface::class);
            $cache->expects($this->once())->method('invalidateTags');

            $listener = new CacheTagListener($cache);
            $args = 'postPersist' === $event
                ? new PostPersistEventArgs($this->row(), $this->entityManager())
                : new PostRemoveEventArgs($this->row(), $this->entityManager());
            $listener->{$event}($args);
            $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        }
    }

    // UrlMetadataSynchronizer writes many rows in one flush - one invalidation, not one per row
    public function testManyRowsInOneFlushInvalidateOnlyOnce(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags');

        $listener = new CacheTagListener($cache);
        $listener->postPersist(new PostPersistEventArgs($this->row(), $this->entityManager()));
        $listener->postUpdate(new PostUpdateEventArgs($this->row(), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    public function testOtherEntitiesAreIgnored(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = new CacheTagListener($cache);
        $listener->postUpdate(new PostUpdateEventArgs(new Config()->setSlug('site-name'), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    // A redirect written or removed empties the rows RedirectSubscriber reads on every request, and the url descriptions stay
    public function testARedirectEmptiesTheRedirectsTagAlone(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([RedirectSubscriber::CACHE_TAG]);

        $listener = new CacheTagListener($cache);
        $listener->postPersist(new PostPersistEventArgs(new Redirect()->setFromPath('/old'), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }
}
