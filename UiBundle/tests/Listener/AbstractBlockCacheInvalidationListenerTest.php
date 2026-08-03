<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Listener\AbstractBlockCacheInvalidationListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class AbstractBlockCacheInvalidationListenerTest extends TestCase
{
    // Adding, editing and removing all reach the subclass's invalidate() - the whole point of sharing the wiring
    public function testEveryLifecycleEventDelegatesToInvalidate(): void
    {
        $listener = new RecordingBlockCacheInvalidationListener();
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $added = new \stdClass();
        $edited = new \stdClass();
        $removed = new \stdClass();

        $listener->postPersist(new PostPersistEventArgs($added, $entityManager));
        $listener->postUpdate(new PostUpdateEventArgs($edited, $entityManager));
        $listener->preRemove(new PreRemoveEventArgs($removed, $entityManager));

        $this->assertSame([$added, $edited, $removed], $listener->invalidated);
    }
}

class RecordingBlockCacheInvalidationListener extends AbstractBlockCacheInvalidationListener
{
    public array $invalidated = [];

    protected function invalidate(object $entity): void
    {
        $this->invalidated[] = $entity;
    }
}
