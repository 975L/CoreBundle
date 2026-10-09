<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Listener\SharedBlockCacheInvalidationListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SharedBlockCacheInvalidationListenerTest extends TestCase
{
    // A pointer cached before its shared block existed is emptied through the slug's own tag
    public function testCreatingASharedBlockEmptiesItsSlugsTag(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['shared_block_contact-banner']);

        $listener = new SharedBlockCacheInvalidationListener($cache);
        $listener->postPersist(new PostPersistEventArgs(new SharedBlock()->setSlug('contact-banner'), $this->createStub(EntityManagerInterface::class)));
    }

    public function testASharedBlockWithoutSlugEmptiesNothing(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = new SharedBlockCacheInvalidationListener($cache);
        $listener->postPersist(new PostPersistEventArgs(new SharedBlock(), $this->createStub(EntityManagerInterface::class)));
    }

    public function testAnotherEntityEmptiesNothing(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = new SharedBlockCacheInvalidationListener($cache);
        $listener->postPersist(new PostPersistEventArgs(new Block(), $this->createStub(EntityManagerInterface::class)));
    }
}
