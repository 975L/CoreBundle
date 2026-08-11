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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Listener\LegalPlaceholderCacheListener;
use c975L\UiBundle\Service\LegalModelCacheTagProvider;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class LegalPlaceholderCacheListenerTest extends TestCase
{
    private function listener(TagAwareCacheInterface $cache): LegalPlaceholderCacheListener
    {
        return new LegalPlaceholderCacheListener(
            $cache,
            new LegalModelPlaceholders($this->createStub(ConfigServiceInterface::class))
        );
    }

    private function config(string $slug): Config
    {
        return new Config()->setSlug($slug)->setValue('whatever');
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    // The whole point: a legal_model's cached render has the contact email baked into it, and no Block ever changed
    public function testEditingAPlaceholderConfigInvalidatesTheLegalTagOnFlush(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')
            ->with([LegalModelCacheTagProvider::CACHE_TAG]);

        $listener = $this->listener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->config('site-contact-email'), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    public function testPersistedAndRemovedConfigsAlsoInvalidate(): void
    {
        foreach (['postPersist', 'postRemove'] as $event) {
            $cache = $this->createMock(TagAwareCacheInterface::class);
            $cache->expects($this->once())->method('invalidateTags');

            $listener = $this->listener($cache);
            $args = 'postPersist' === $event
                ? new PostPersistEventArgs($this->config('site-name'), $this->entityManager())
                : new PostRemoveEventArgs($this->config('site-name'), $this->entityManager());
            $listener->{$event}($args);
            $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        }
    }

    // The back-office saves the whole config group in one flush - one invalidation, not one per row
    public function testTheWholeGroupSavedAtOnceInvalidatesOnlyOnce(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags');

        $listener = $this->listener($cache);
        foreach (['site-name', 'site-owner', 'site-contact-email'] as $slug) {
            $listener->postUpdate(new PostUpdateEventArgs($this->config($slug), $this->entityManager()));
        }
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    // A config the legal models are not allowed to print, and any other entity, must cost nothing
    public function testUnrelatedConfigsAndEntitiesLeaveTheCacheAlone(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = $this->listener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->config('theme-color-primary'), $this->entityManager()));
        $listener->postUpdate(new PostUpdateEventArgs(new Block(), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }

    // The flag is consumed, so a later unrelated flush does not invalidate a second time
    public function testTheStaleFlagIsResetAfterTheFlush(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags');

        $listener = $this->listener($cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->config('site-dpo'), $this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
    }
}
