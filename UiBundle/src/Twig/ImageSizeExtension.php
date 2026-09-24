<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\ImageDimensionsReader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

// The pixel size of an image served from public/, for a template drawing an <img> from a bare path with no Media to read it from - a book's cover, a serie's. Without width/height the browser cannot reserve the box, and the page shifts as each image lands
class ImageSizeExtension
{
    public function __construct(
        private readonly ImageDimensionsReader $imageDimensionsReader,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Null for a remote url, a missing file or one with no measurable size: the caller then writes no attribute, as before
    #[AsTwigFunction('ui_image_size')]
    public function imageSize(?string $src): ?array
    {
        $path = (string) parse_url((string) $src, PHP_URL_PATH);
        if ('' === $path || null !== parse_url((string) $src, PHP_URL_HOST) || str_contains($path, '..')) {
            return null;
        }

        return $this->imageDimensionsReader->read($this->projectDir . '/public/' . ltrim($path, '/'));
    }
}
