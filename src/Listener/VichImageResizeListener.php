<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use SplFileInfo;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Gd\Imagine;
use Imagine\Image\ImageInterface;
use Vich\UploaderBundle\Event\Event;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener
{
    // Formats Imagine\Gd can actually read as a source - notably excludes 'ico', which FIXED_ICON_SPECS can only ever produce (see wrapAsIco()), never consume. A site_graphic export/import roundtrip (see SiteBundle's SiteGraphicExportProvider) re-feeds a role=favicon Media's already-converted .ico file back in as if it were a fresh upload, which would otherwise crash the whole import
    private const READABLE_EXTENSIONS = ['jpg', 'png', 'gif', 'webp'];

    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
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
            $extension = $entity->getFile()->getExtension();

            if ($entity instanceof Media && null !== ($spec = $entity->getFixedIconSpec())) {
                if (in_array($extension, self::READABLE_EXTENSIONS, true)) {
                    $this->processFixedIcon($entity, $absolutePath, $spec);
                }

                return;
            }

            if (in_array($extension, self::READABLE_EXTENSIONS, true)) {
                $this->processImage($entity, $absolutePath);
            }

            return;
        }

        if ($entity instanceof VichPrivateFileInterface) {
            $this->moveFileToPrivate($entity, $filename, $absolutePath);
        }
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
            $entity->setSize((new SplFileInfo($absolutePath))->getSize());
        }
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
            $entity->setSize((new SplFileInfo($absolutePath))->getSize());
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
