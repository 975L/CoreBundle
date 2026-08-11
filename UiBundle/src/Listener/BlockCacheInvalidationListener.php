<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Twig\MediaExtension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Fires for any Block/Media flushed through the EntityManager, regardless of which bundle or app code triggered the change (PageCrudController, an importer, another HasBlocksInterface owner in BookBundle...) - see BlockExtension::renderBlock() for what gets invalidated here postPersist matters as much as postUpdate: attaching a brand new Media to an already-cached Block (e.g. adding a slide to an existing Slider) is an INSERT, not an UPDATE - postUpdate never fires for it, so without this the block's cached render silently kept excluding it
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class BlockCacheInvalidationListener
{
    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidate($args->getObject(), $args->getObjectManager());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject(), $args->getObjectManager());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject(), $args->getObjectManager());
    }

    private function invalidate(object $entity, EntityManagerInterface $em): void
    {
        // Singleton-role Media (logo, favicon...) is never attached to a Block, so it needs its own tag - see MediaExtension::preloadSingletonRoles()
        if ($entity instanceof Media && $entity->isSingletonRole()) {
            $this->cache->invalidateTags([MediaExtension::MEDIA_SINGLETONS_CACHE_TAG]);
        }

        $blockId = match (true) {
            $entity instanceof Block => $entity->getId(),
            $entity instanceof Media => $this->resolveMediaBlockId($entity, $em),
            default => null,
        };

        if (null !== $blockId) {
            $this->cache->invalidateTags(['block_' . $blockId]);
        }
    }

    // Block::removeMedia() nulls the owning side in PHP as soon as a Media is dropped from the form's collection - well before flush() runs - so by the time this listener fires, $media->getBlock() is already null. Doctrine's pre-flush snapshot still holds the original reference, since application code mutating a property doesn't touch it.
    private function resolveMediaBlockId(Media $media, EntityManagerInterface $em): ?int
    {
        $block = $media->getBlock()
            ?? ($em->getUnitOfWork()->getOriginalEntityData($media)['block'] ?? null);

        return $block?->getId();
    }
}
