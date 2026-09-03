<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

// Generic file cleanup for any bundle's media entity - each satellite bundle (Shop, Crowdfunding...) only needs its own Media hierarchy to implement VichMediaNamableInterface, no per-entity listener of its own. Only needed for the private-file case below - Vich's own delete_on_remove/delete_on_update already clean up any file still under its original public mapping destination, which a private one is not. Priority 100 on preUpdate runs this before Vich's own "clean" listener (priority 50, see VichUploaderExtension::registerListeners) has a chance to erase the old filename, so the mapping here still reads the file being replaced. The actual deletion is deferred to postFlush so a failed flush never removes a file its row still points at. #[AsDoctrineListener] only reads the class-level attribute (TARGET_CLASS) - Doctrine then calls whichever method matches each tagged event's name, hence one attribute per event here rather than per method.
#[AsDoctrineListener(event: Events::preUpdate, priority: 100)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class MediaFileRemoveListener
{
    /** @var string[] */
    private array $pendingRemovals = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly PropertyMappingFactory $propertyMappingFactory,
    ) {
    }

    // The file a new upload replaces, private only: a public one is still where the mapping says, so Vich's own delete_on_update removes it
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof VichPrivateFileInterface) {
            return;
        }

        $mapping = $this->propertyMappingFactory->fromField($entity, 'file');

        // Only when a new file is actually being uploaded - a plain metadata edit keeps the file the entity already holds
        if (null === $mapping || !$mapping->getFile($entity) instanceof File) {
            return;
        }

        $this->queue($entity, $mapping->getFileName($entity));
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof VichMediaNamableInterface) {
            return;
        }

        // Reads the field actually configured as fileNameProperty on the entity's own mapping (e.g. "name" for VichMediaTrait users, "filename" for UiBundle's own Media/GalleryPhoto) instead of assuming a fixed getName()/getFilename() accessor, which differs per entity
        $this->queue($entity, $this->propertyMappingFactory->fromField($entity, 'file')?->getFileName($entity));
    }

    private function queue(object $entity, ?string $name): void
    {
        if (null === $name || '' === $name) {
            return;
        }

        // A private file (e.g. a paid download) was moved out of public/ into its own directory by VichImageResizeListener::moveFileToPrivate() - it must be looked up there, not under public/
        $directory = $entity instanceof VichPrivateFileInterface ? $entity->getPrivateDirectory() : 'public';

        $this->pendingRemovals[] = $this->kernel->getProjectDir() . '/' . $directory . '/' . $name;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        foreach ($this->pendingRemovals as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->pendingRemovals = [];
    }
}
