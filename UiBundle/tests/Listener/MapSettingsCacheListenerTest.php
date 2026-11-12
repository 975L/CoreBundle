<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Listener\MapSettingsCacheListener;
use c975L\UiBundle\Service\MapCacheTagProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class MapSettingsCacheListenerTest extends TestCase
{
    // A key added or a provider changed reaches the maps already cached, once per flush however many entries were saved
    public function testAMapEntryInvalidatesTheMapTagOnceOnFlush(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with([MapCacheTagProvider::CACHE_TAG]);

        $listener = new MapSettingsCacheListener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->config('ui-map-google-api-key'), $this->entityManager()));
        $listener->postPersist(new PostPersistEventArgs($this->config('ui-map-provider'), $this->entityManager()));
        $listener->postRemove(new PostRemoveEventArgs($this->config('ui-map-provider'), $this->entityManager()));
        $listener->postFlush();
        $listener->postFlush();
    }

    // Any other setting, or any other entity, leaves the maps cached
    public function testAnythingElseInvalidatesNothing(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = new MapSettingsCacheListener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->config('site-name'), $this->entityManager()));
        $listener->postUpdate(new PostUpdateEventArgs(new Block()->setKind('map'), $this->entityManager()));
        $listener->postFlush();
    }

    private function config(string $slug): Config
    {
        return new Config()->setSlug($slug)->setValue('whatever');
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }
}
