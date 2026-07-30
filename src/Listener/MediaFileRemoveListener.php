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
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpKernel\KernelInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

// Generic file cleanup for any bundle's media entity - each satellite bundle (Shop, Crowdfunding...) only needs its own Media hierarchy to implement VichMediaNamableInterface, no per-entity listener of its own. Only needed for the private-file case below - Vich's own delete_on_remove already cleans up any file still under its original public mapping destination
#[AsDoctrineListener(event: Events::preRemove)]
class MediaFileRemoveListener
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly PropertyMappingFactory $propertyMappingFactory,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof VichMediaNamableInterface) {
            return;
        }

        // Reads the field actually configured as fileNameProperty on the entity's own mapping (e.g. "name" for VichMediaTrait users, "filename" for UiBundle's own Media/GalleryPhoto) instead of assuming a fixed getName()/getFilename() accessor, which differs per entity
        $name = $this->propertyMappingFactory->fromField($entity, 'file')?->getFileName($entity);
        if (!$name) {
            return;
        }

        // A private file (e.g. a paid download) was moved out of public/ into its own directory by VichImageResizeListener::moveFileToPrivate() - it must be looked up there, not under public/
        $directory = $entity instanceof VichPrivateFileInterface ? $entity->getPrivateDirectory() : 'public';

        $path = $this->kernel->getProjectDir() . '/' . $directory . '/' . $name;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
