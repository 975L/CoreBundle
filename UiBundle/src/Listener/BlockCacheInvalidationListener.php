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
use c975L\UiBundle\Entity\Translation;
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

        $block = match (true) {
            $entity instanceof Block => $entity,
            $entity instanceof Media => $this->resolveMediaBlock($entity, $em),
            // A block's translation is a row of another table, so nothing touches the block itself and its render in that language would go on being served as it stands
            $entity instanceof Translation => $this->resolveTranslatedBlock($entity, $em),
            default => null,
        };

        if (null === $block) {
            return;
        }

        $tags = $this->tagsUpTheChain($block, $em);
        if ([] !== $tags) {
            $this->cache->invalidateTags($tags);
        }
    }

    // The block this translation dresses, when there is one: the translations of the other bundles - a page's own - do not go through the blocks cache
    private function resolveTranslatedBlock(Translation $translation, EntityManagerInterface $em): ?Block
    {
        return Translation::OWNER_BLOCK === $translation->getOwnerType()
            ? $em->getRepository(Block::class)->find($translation->getOwnerId())
            : null;
    }

    /**
     * The block's own tag, plus one per container above it: a container's cached html holds its slots' verbatim (see BlockCacheTagResolver), so a slot that changed leaves every container up the chain holding stale output.
     * Adding or removing a slot is what makes this necessary rather than merely tidy - the new row's id was never a tag of its container's entry, so nothing else would ever reach it.
     *
     * @return string[]
     */
    private function tagsUpTheChain(Block $block, EntityManagerInterface $em): array
    {
        $tags = [];

        // Guarded rather than trusted: a container and one of its own slots pointing back at each other would otherwise spin here forever
        $seen = [];
        for ($current = $block; null !== $current; $current = $this->resolveParentBlock($current, $em)) {
            $id = $current->getId();
            if (null === $id || isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            $tags[] = 'block_' . $id;
        }

        return $tags;
    }

    // Block::removeSlot() nulls the owning side in PHP as soon as a slot is dropped from the form's collection, same as removeMedia() below - the change set is what still holds the container it was taken out of (see resolveOwner())
    private function resolveParentBlock(Block $block, EntityManagerInterface $em): ?Block
    {
        return $this->resolveOwner($block, 'parentBlock', $block->getParentBlock(), $em);
    }

    // Block::removeMedia() nulls the owning side in PHP as soon as a Media is dropped from the form's collection - well before flush() runs - so by the time this listener fires, $media->getBlock() is already null (see resolveOwner())
    private function resolveMediaBlock(Media $media, EntityManagerInterface $em): ?Block
    {
        return $this->resolveOwner($media, 'block', $media->getBlock(), $em);
    }

    // The block an entity was attached to before the flush deleting it. The change set comes first: computeChangeSets() overwrites the pre-flush snapshot with the null the collection removal wrote, so getOriginalEntityData() gives back that null by the time preRemove fires, where the change set kept the pair and its old value is the block whose cached html is now stale
    private function resolveOwner(object $entity, string $field, ?Block $current, EntityManagerInterface $em): ?Block
    {
        if (null !== $current) {
            return $current;
        }

        $unitOfWork = $em->getUnitOfWork();
        $owner = $unitOfWork->getEntityChangeSet($entity)[$field][0]
            ?? ($unitOfWork->getOriginalEntityData($entity)[$field] ?? null);

        return $owner instanceof Block ? $owner : null;
    }
}
