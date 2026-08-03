<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\SvgRasterizer;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Event\Event;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener
{
    // Image types Imagine\Gd can actually read as a source, decided on the file's own content rather than on its name: a fixed icon role's stored file always carries the role's target extension whatever was uploaded (see UiMediaNamer), so an .ico name says nothing about what is inside it. A real .ico is the one thing that never gets in - it is what FIXED_ICON_SPECS produces (see wrapAsIco()), never what it consumes, and a site_graphic export/import roundtrip (see SiteBundle's SiteGraphicExportProvider) re-feeds a role=favicon Media's already-converted file back in as if it were a fresh upload, which would otherwise crash the whole import
    private const READABLE_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ImageDimensionsReader $imageDimensionsReader,
        private readonly SvgRasterizer $svgRasterizer,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function onPostUpload(Event $event): void
    {
        $entity = $event->getObject();
        $mapping = $event->getMapping();
        $filename = $mapping->getFileName($entity);
        $absolutePath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;

        if (!$this->filesystem->exists($absolutePath)) {
            return;
        }

        if ($entity instanceof VichImageResizableInterface) {
            $spec = $entity instanceof Media ? $entity->getFixedIconSpec() : null;

            // An icon role uploaded as SVG is rasterized in place first, and goes on through the very same pipeline as any raster upload. The stored file carries the role's own extension by then (see UiMediaNamer), whatever was uploaded, so only its content can tell - which is exactly what rasterizeInPlace() looks at, leaving the file untouched for everything that is not an SVG it can handle
            if (null !== $spec) {
                $this->svgRasterizer->rasterizeInPlace($absolutePath);
            }

            // A file GD can't decode (an svg no rasterizer could handle, or the already-converted .ico a content_import roundtrip re-feeds as a fresh upload) is left exactly as uploaded - only its dimensions are recorded below
            if ($this->isReadable($absolutePath)) {
                if (null !== $spec) {
                    $this->processFixedIcon($entity, $absolutePath, $spec);
                } else {
                    $this->processImage($entity, $absolutePath);
                }
            }

            $this->storeDimensions($entity, $absolutePath);

            return;
        }

        if ($entity instanceof VichPrivateFileInterface) {
            $this->moveFileToPrivate($entity, $filename, $absolutePath);
        }
    }

    // Reads the type off the file's magic bytes (see READABLE_IMAGE_TYPES) - the error suppression covers everything getimagesize() can't measure at all, from a real .ico to the pdf/video/audio a Media can just as well hold
    private function isReadable(string $absolutePath): bool
    {
        $size = @getimagesize($absolutePath);

        return false !== $size && in_array($size[2], self::READABLE_IMAGE_TYPES, true);
    }

    private function processImage(VichImageResizableInterface $entity, string $absolutePath): void
    {
        $imagine = new Imagine();
        $media = $imagine->open($absolutePath);

        // Derivatives are generated from the untouched original first - the entity's own stored file (below) is a downscale of it, and deriving the highres version from that instead would upscale a already-shrunk image
        if ($entity instanceof VichMultiSizeImageInterface) {
            $this->processMultiSizeDerivatives($entity, $media, $absolutePath);
        }

        $width = $entity->getImageWidth();
        $size = $media->getSize();
        $height = (int) ($size->getHeight() * $width / $size->getWidth());

        $media
            ->resize(new Box($width, $height))
            ->save($absolutePath, [
                'format' => 'webp',
                'webp_quality' => 90,
            ]);

        if (method_exists($entity, 'setSize')) {
            $entity->setSize((new \SplFileInfo($absolutePath))->getSize());
        }
    }

    // Records the stored file's own pixel size on the entity, read after any resizing/cropping so the numbers describe the file actually served, not the upload. Admin-editable afterwards (see MediaUploadType), hence a plain overwrite here - a new upload makes any previously entered value stale. Guarded by method_exists because the width/height columns belong to Media, not to VichImageResizableInterface, which other bundles' entities also implement
    private function storeDimensions(object $entity, string $absolutePath): void
    {
        if (!method_exists($entity, 'setWidth') || !method_exists($entity, 'setHeight')) {
            return;
        }

        $dimensions = $this->imageDimensionsReader->read($absolutePath);
        if (null === $dimensions) {
            return;
        }

        $entity->setWidth((string) $dimensions['width']);
        $entity->setHeight((string) $dimensions['height']);
    }

    // Sibling files next to the entity's own stored (medium) image: a square outbound-cropped thumbnail for grid displays, and a proportionally-resized highres version for zoom - both derived from a copy() of the original so the shared $media instance stays untouched for the medium resize that follows in processImage(). Filenames match what VichMultiSizeImageInterface consumers derive themselves from their own stored filename (see e.g. GalleryBundle's GalleryPhoto::getThumbnailFilename()/getHighresFilename()).
    private function processMultiSizeDerivatives(VichMultiSizeImageInterface $entity, ImageInterface $original, string $absolutePath): void
    {
        $base = preg_replace('/\.[^.]+$/', '', $absolutePath);
        $originalSize = $original->getSize();

        $highresWidth = min($entity->getHighresWidth(), $originalSize->getWidth());
        $highresHeight = (int) ($originalSize->getHeight() * $highresWidth / $originalSize->getWidth());
        $original->copy()
            ->resize(new Box($highresWidth, $highresHeight))
            ->save($base . '-highres.webp', ['format' => 'webp', 'webp_quality' => 90]);

        $thumbnailSize = $entity->getThumbnailSize();
        $original->copy()
            ->thumbnail(new Box($thumbnailSize, $thumbnailSize), ImageInterface::THUMBNAIL_OUTBOUND)
            ->save($base . '-thumb.webp', ['format' => 'webp', 'webp_quality' => 90]);
    }

    // Crops/resizes to the exact target size (fixed icon roles never keep the uploaded aspect ratio) and converts to the target format - .ico has no native GD/Imagine writer, so it's hand-wrapped around a raw bitmap
    private function processFixedIcon(Media $entity, string $absolutePath, array $spec): void
    {
        $imagine = new Imagine();
        $thumbnail = $imagine->open($absolutePath)->thumbnail(
            new Box($spec['width'], $spec['height']),
            ImageInterface::THUMBNAIL_OUTBOUND
        );

        if ('ico' === $spec['format']) {
            file_put_contents($absolutePath, $this->wrapAsIco($thumbnail, $spec['width'], $spec['height']));
        } else {
            $thumbnail->save($absolutePath, ['format' => $spec['format']]);
        }

        if (method_exists($entity, 'setSize')) {
            $entity->setSize((new \SplFileInfo($absolutePath))->getSize());
        }
    }

    // Wraps a raw 32bpp bitmap in a minimal ICO container. PNG-compressed ICO entries (valid since Windows Vista, and readable by browsers/GIMP) are rejected by gdk-pixbuf ("Compressed icons are not supported"), which breaks thumbnails in Nemo/Nautilus - so the classic uncompressed BITMAPINFOHEADER payload is used instead for universal compatibility
    private function wrapAsIco(ImageInterface $image, int $width, int $height): string
    {
        $dib = $this->buildIcoDib($image, $width, $height);
        $header = pack('vvv', 0, 1, 1);
        $entry = pack('CCCCvvVV', $width, $height, 0, 0, 1, 32, strlen($dib), 6 + 16);

        return $header . $entry . $dib;
    }

    // Builds the BITMAPINFOHEADER + pixel data (BGRA, bottom-up) + AND mask expected inside an ICO entry
    private function buildIcoDib(ImageInterface $image, int $width, int $height): string
    {
        $pixels = '';

        for ($y = $height - 1; $y >= 0; --$y) {
            for ($x = 0; $x < $width; ++$x) {
                $color = $image->getColorAt(new Point($x, $y));
                $alpha = (int) round($color->getAlpha() * 255 / 100);
                $pixels .= pack('C4', $color->getBlue(), $color->getGreen(), $color->getRed(), $alpha);
            }
        }

        // 1 bit per pixel, rows padded to a 4-byte boundary - unused since alpha carries transparency
        $andMask = str_repeat("\0", (int) (4 * ceil($width / 32)) * $height);

        $dibHeader = pack(
            'VVVvvVVVVVV',
            40,
            $width,
            $height * 2,
            1,
            32,
            0,
            strlen($pixels) + strlen($andMask),
            0,
            0,
            0,
            0
        );

        return $dibHeader . $pixels . $andMask;
    }

    private function moveFileToPrivate(VichPrivateFileInterface $entity, string $filename, string $publicPath): void
    {
        $privatePath = $this->parameterBag->get('kernel.project_dir') . '/' . $entity->getPrivateDirectory() . '/' . $filename;

        $this->filesystem->mkdir(dirname($privatePath), 0755);
        $this->filesystem->copy($publicPath, $privatePath, true);
        $this->filesystem->remove($publicPath);
    }
}
