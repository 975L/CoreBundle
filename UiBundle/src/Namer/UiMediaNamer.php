<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Namer;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Validator\AllowedUploadExtension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;
use Vich\UploaderBundle\Naming\NamerInterface;

class UiMediaNamer implements NamerInterface
{
    // What a file gets named when its extension is on no list: harmless under public/, where a server runs a ".php" and a browser opens an ".html", and answering no 500 to whatever got this far
    private const string FALLBACK_EXTENSION = 'bin';

    // The common images, named off their bytes whatever the file says it is
    private const array IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/vnd.microsoft.icon' => 'ico',
        'image/x-icon' => 'ico',
    ];

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {
        $this->filesystem = new Filesystem();
    }

    // Vich widens the subject to object|array to cover its array mappings, which this bundle never declares - anything but a namable entity is refused here rather than named at random
    public function name(object | array $object, PropertyMappingInterface $mapping): string
    {
        if (!$object instanceof VichMediaNamableInterface) {
            throw new \RuntimeException(sprintf('Entity "%s" must implement VichMediaNamableInterface.', get_debug_type($object)));
        }

        // Read off the mapping rather than off the entity: which property holds the file is the mapping's own business, and no interface here declares a getter for it
        $file = $mapping->getFile($object);
        if (!$file instanceof File) {
            throw new \RuntimeException(sprintf('Entity "%s" carries no uploaded file to name.', $object::class));
        }

        $filePath = $file->getPathname();
        if (!$this->filesystem->exists($filePath)) {
            throw new \RuntimeException('File not found: ' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8'));
        }

        // Singleton site-wide graphics (favicon, apple-touch-icon, og-image, logo) need a fixed, predictable filename at the root of public/ - no uniqid (would break the well-known URL)
        if ($object instanceof Media && $object->isSingletonRole()) {
            // Roles with a fixed icon spec (favicon, apple-touch-icon) always end up in that exact format, whatever the uploaded file was - see VichImageResizeListener, which converts it after upload. Other singleton roles (og-image, logo) are resized and forced to webp just like in-content block images (see VichImageResizeListener::processImage), so the extension must match that. The og-image is webp even for an SVG upload, rasterized before that same conversion - a logo SVG stays SVG
            $spec = $object->getFixedIconSpec();
            $extension = match (true) {
                null !== $spec => $spec['format'],
                $object->isOgImage() => 'webp',
                default => $this->determineExtension($file),
            };

            return $object->getVichMediaPath() . '.' . $extension;
        }

        // A Page's or a UrlMetadata's own og-image is rasterized like the site-wide one, so an SVG upload is named webp here too. ".webp" is only promised to an entity VichImageResizeListener converts: anything else would keep its jpeg or png bytes under a name that lies about them
        $extension = $object instanceof Media && $object->isOgImage() ? 'webp' : $this->determineExtension($file, $object instanceof VichImageResizableInterface, $object instanceof VichPrivateFileInterface);
        $basePath = $object instanceof Media ? $this->resolveBasePath($object) : $object->getVichMediaPath();

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

    // Whatever goes under public/ comes out of AllowedUploadExtension's list, read off the bytes for the common images and otherwise off a name nobody here wrote: the form's constraint is one door, an import or another bundle's form reach this namer without it. A class of files that never leaves private/ (a paid .cbz, a .7z) keeps its own extension, which neither a web server nor a browser reads there: not so a members-only Media, which unticking moves to public/
    private function determineExtension(File $file, bool $convertToWebp = true, bool $private = false): string
    {
        $extension = self::IMAGE_EXTENSIONS[(string) $file->getMimeType()] ?? strtolower($file->getExtension());

        if ($convertToWebp && in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            return 'webp';
        }

        // A temporary upload has no extension of its own, so the name the visitor's browser sent is where it is read from
        if ('' === $extension && $file instanceof UploadedFile) {
            $extension = strtolower($file->getClientOriginalExtension());
        }

        if (\in_array($extension, AllowedUploadExtension::EXTENSIONS, true) || ($private && 1 === preg_match('/^[a-z0-9]{1,10}$/', $extension))) {
            return $extension;
        }

        // No name to go by, or one off the list: the bytes decide, which still name a script or a page as such
        $guessed = strtolower((string) $file->guessExtension());

        return \in_array($guessed, AllowedUploadExtension::EXTENSIONS, true) ? $guessed : self::FALLBACK_EXTENSION;
    }
}
