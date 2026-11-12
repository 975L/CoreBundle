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
use c975L\ConfigBundle\Listener\ConfigCacheListener;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;

class ConfigCacheListenerTest extends TestCase
{
    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    private function flush(ConfigCacheListener $listener): void
    {
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    // A saved setting empties the configuration's cache once its flush goes through, and only once for a whole group saved together
    public function testASavedConfigEmptiesTheCacheOnceOnFlush(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->once())->method('invalidateCache');

        $listener = new ConfigCacheListener($configService);
        $listener->postUpdate(new PostUpdateEventArgs(new Config(), $this->entityManager()));
        $listener->postUpdate(new PostUpdateEventArgs(new Config(), $this->entityManager()));
        $this->flush($listener);
        $this->flush($listener);
    }

    // A setting added or removed changes the configuration as much as one edited
    public function testAPersistedOrRemovedConfigAlsoEmptiesTheCache(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->exactly(2))->method('invalidateCache');

        $listener = new ConfigCacheListener($configService);
        $listener->postPersist(new PostPersistEventArgs(new Config(), $this->entityManager()));
        $this->flush($listener);
        $listener->postRemove(new PostRemoveEventArgs(new Config(), $this->entityManager()));
        $this->flush($listener);
    }

    // A visitor's flush writing anything else never empties it
    public function testOtherEntitiesLeaveTheCacheAlone(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->never())->method('invalidateCache');

        $listener = new ConfigCacheListener($configService);
        $listener->postUpdate(new PostUpdateEventArgs(new Redirect(), $this->entityManager()));
        $this->flush($listener);
    }

    // Ahead of every bundle's own postFlush, which empties its block tags at the default priority: no block can be cached again from the old value after they are emptied
    public function testItsPostFlushRunsAheadOfTheDefaultPriority(): void
    {
        $priorities = [];
        foreach (new \ReflectionClass(ConfigCacheListener::class)->getAttributes(AsDoctrineListener::class) as $attribute) {
            $listener = $attribute->newInstance();
            $priorities[$listener->event] = $listener->priority;
        }

        $this->assertGreaterThan(0, $priorities[Events::postFlush]);
        $this->assertSame(ConfigCacheListener::POST_FLUSH_PRIORITY, $priorities[Events::postFlush]);
    }
}
