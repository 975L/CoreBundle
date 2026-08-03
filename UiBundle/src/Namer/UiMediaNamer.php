<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Namer;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Entity\Media;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class UiMediaNamer implements NamerInterface
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function name($entity, PropertyMapping $mapping): string
    {
        if (!$entity instanceof VichMediaNamableInterface) {
            throw new \RuntimeException(sprintf('Entity "%s" must implement VichMediaNamableInterface.', get_class($entity)));
        }

        // Read off the mapping rather than off the entity: which property holds the file is the mapping's own business, and no interface here declares a getter for it
        $file = $mapping->getFile($entity);
        if (!$file instanceof File) {
            throw new \RuntimeException(sprintf('Entity "%s" carries no uploaded file to name.', $entity::class));
        }

        $filePath = $file->getPathname();
        if (!$this->filesystem->exists($filePath)) {
            throw new \RuntimeException('File not found: ' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8'));
        }

        // Singleton site-wide graphics (favicon, apple-touch-icon, og-image, logo) need a fixed, predictable filename at the root of public/ - no uniqid (would break the well-known URL)
        if ($entity instanceof Media && $entity->isSingletonRole()) {
            // Roles with a fixed icon spec (favicon, apple-touch-icon) always end up in that exact format, whatever the uploaded file was - see VichImageResizeListener, which converts it after upload. Other singleton roles (og-image, logo) are resized and forced to webp just like in-content block images (see VichImageResizeListener::processImage), so the extension must match that
            $spec = $entity->getFixedIconSpec();
            $extension = null !== $spec ? $spec['format'] : $this->determineExtension($file);

            return $entity->getVichMediaPath() . '.' . $extension;
        }

        $extension = $this->determineExtension($file);
        $basePath = $entity instanceof Media ? $this->resolveBasePath($entity) : $entity->getVichMediaPath();

        return $basePath . '-' . uniqid() . '.' . $extension;
    }

    // An admin-typed Media::$name (e.g. "Rapport annuel") always wins over the auto "block-{kind}-{id}" path, slugified so the stored/physical filename stays readable and URL-safe - falls back to getVichMediaPath() when no name was given
    private function resolveBasePath(Media $entity): string
    {
        $name = trim((string) $entity->getName());
        if ('' === $name) {
            return $entity->getVichMediaPath();
        }

        $slug = strtolower($this->slugger->slug($name)->toString());
        $dir = dirname($entity->getVichMediaPath());

        return ('.' !== $dir ? $dir . '/' : '') . $slug;
    }

    private function determineExtension(File $file, bool $convertToWebp = true): string
    {
        $mimeType = $file->getMimeType();
        $extension = $file->getExtension();

        if ('image/jpeg' === $mimeType || 'image/jpg' === $mimeType) {
            $extension = 'jpg';
        } elseif ('image/png' === $mimeType) {
            $extension = 'png';
        } elseif ('image/gif' === $mimeType) {
            $extension = 'gif';
        } elseif ('image/webp' === $mimeType) {
            $extension = 'webp';
        } elseif ('image/vnd.microsoft.icon' === $mimeType || 'image/x-icon' === $mimeType) {
            $extension = 'ico';
        }

        if ($convertToWebp && in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            return 'webp';
        }

        if ('' !== $extension) {
            return $extension;
        }

        // A temporary upload has no extension of its own, so the name the visitor's browser sent is where it is read from - and the mime type is all that is left for anything else
        return $file instanceof UploadedFile ? $file->getClientOriginalExtension() : (string) $file->guessExtension();
    }
}
