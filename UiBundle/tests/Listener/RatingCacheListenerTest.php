<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Rating;
use c975L\UiBundle\Listener\RatingCacheListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RatingCacheListenerTest extends TestCase
{
    private function rating(int $ownerId): Rating
    {
        return new Rating()->setOwnerType('shop_product')->setOwnerId($ownerId)->setVoter('v')->setValue(4);
    }

    // A vote empties the fragments showing that owner's average, and the grids showing its type, once the row is written
    public function testAVoteEmptiesItsOwnersTagOnFlush(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['ui_rating_shop_product_12', 'ui_rating_shop_product']);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $listener = new RatingCacheListener($cache);
        $listener->postPersist(new PostPersistEventArgs($this->rating(12), $entityManager));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
    }

    // A vote taken back is a removal, which changes the average just as much
    public function testAVoteTakenBackAlsoEmptiesIt(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([RatingCacheListener::cacheTag('shop_product', 3), RatingCacheListener::typeCacheTag('shop_product')]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $listener = new RatingCacheListener($cache);
        $listener->preRemove(new PreRemoveEventArgs($this->rating(3), $entityManager));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
    }
}
