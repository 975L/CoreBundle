<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Guards the auto-detected pixel size of an image against the forms that expose it (MediaUploadType for a Block's uploads, MediaCrudController for the media library): both leave the admin free to type a value, but a submission with both fields blank means "unknown", not "erase" - without this, saving a form whose inputs happened to be empty writes null over what VichImageResizeListener had recorded, and the size is lost until MediaDimensionsCommand is run again
class MediaDimensionsFiller
{
    public function __construct(
        private readonly ImageDimensionsReader $imageDimensionsReader,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // A value typed by the admin always wins, including a width alone (height left blank to keep the ratio): that's a deliberate pair, so a single filled field is enough to leave the media untouched
    public function fillIfBlank(Media $media): void
    {
        if ('' !== (string) $media->getWidth() || '' !== (string) $media->getHeight()) {
            return;
        }

        // The file of a brand new upload isn't stored yet at this point (Vich moves it on flush), so this reads the media's previous file, if any - VichImageResizeListener overwrites both values with the newly stored one's right after
        $dimensions = $this->imageDimensionsReader->read($this->projectDir . '/public/' . $media->getFilename());
        if (null === $dimensions) {
            return;
        }

        $media->setWidth((string) $dimensions['width']);
        $media->setHeight((string) $dimensions['height']);
    }
}
