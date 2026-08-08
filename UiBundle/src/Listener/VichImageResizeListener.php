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
use c975L\UiBundle\Contract\VichOriginalKeepableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Contract\VichWatermarkableInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Model\Watermark;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\ImageWatermarker;
use c975L\UiBundle\Service\SvgRasterizer;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Point;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener
{
    // Image types Imagine\Gd can actually read as a source, decided on the file's own content rather than on its name: a fixed icon role's stored file always carries the role's target extension whatever was uploaded (see UiMediaNamer), so an .ico name says nothing about what is inside it. A real .ico is the one thing that never gets in - it is what FIXED_ICON_SPECS produces (see wrapAsIco()), never what it consumes, and a site_graphic export/import roundtrip (see SiteBundle's SiteGraphicExportProvider) re-feeds a role=favicon Media's already-converted file back in as if it were a fresh upload, which would otherwise crash the whole import
    private const READABLE_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    // What a kept original may be named, keyed by the mime type read off the file's own bytes. An allow-list rather than a conversion table: the stored file's extension is forced to webp (see UiMediaNamer::determineExtension), so the original's cannot be reused from it and has to be decided here - and the one other source, the name the browser sent, is client input that would end up as a path written to disk
    private const ORIGINAL_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/tiff' => 'tif',
    ];

    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ImageDimensionsReader $imageDimensionsReader,
        private readonly SvgRasterizer $svgRasterizer,
        private readonly ImageWatermarker $imageWatermarker,
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

        // Each upload gets the request's own budget over again, instead of a batch of twenty sharing a single one: the work below is bounded per file - around two seconds for a 24-megapixel photo, kept there by deriving every size from one resampling (see processMultiSizeDerivatives) - where a batch is bounded only by how many files an admin picked. Vich fires this once per entity, which is exactly the unit of work to re-arm the clock on
        $this->renewTimeLimit();

        // This listener fires once per Vich field, not once per entity, and the two branches below answer for the entity as a whole - so an entity carrying a second file next to its image (a gallery media and its self-hosted video, say) would have that file kept aside, measured, and handed to a resizer that cannot read it
        // The file's own bytes decide, not the name: the stored extension is forced (see UiMediaNamer), and an SVG bound for an icon role is an image here too, which is what lets it go on through rasterizeInPlace() below
        $isImage = $this->isImage($absolutePath);

        // Before anything below touches the file: processImage() overwrites it in place with its own downscaled webp conversion, and rasterizeInPlace() does the same to an SVG, so this is the one moment the upload still exists as it was sent
        if ($isImage && $entity instanceof VichOriginalKeepableInterface) {
            $this->keepOriginal($entity, $filename, $absolutePath);
        }

        if ($isImage && $entity instanceof VichImageResizableInterface) {
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

    // Re-arms the clock the request is already running against, rather than lifting it: a single image that ran away still stops, and the ceiling an admin set stays the ceiling - only what it is counted against changes, from the whole batch to one file of it
    // Guarded on function_exists() because a host can put set_time_limit() on disable_functions, where function_exists() answers false and calling it would be a fatal of its own. A limit of 0 is left alone: that is the command line, on no clock at all, and setting 0 back would be lifting a limit rather than renewing one
    private function renewTimeLimit(): void
    {
        if (!\function_exists('set_time_limit')) {
            return;
        }

        $limit = (int) \ini_get('max_execution_time');
        if ($limit > 0) {
            set_time_limit($limit);
        }
    }

    // Reads the type off the file's magic bytes (see READABLE_IMAGE_TYPES) - the error suppression covers everything getimagesize() can't measure at all, from a real .ico to the pdf/video/audio a Media can just as well hold
    // Whether the image pipeline has any business with this file at all - read off the file's own content, an upload's declared type being client input and the stored name carrying a forced extension
    // Broader than isReadable() below, and asked earlier: this one covers every image, including the SVG no rasterizer may end up handling and the .ico an import re-feeds, both of which belong to the pipeline even though GD cannot decode them
    private function isImage(string $absolutePath): bool
    {
        $mimeType = (new File($absolutePath))->getMimeType();

        return null !== $mimeType && str_starts_with($mimeType, 'image/');
    }

    private function isReadable(string $absolutePath): bool
    {
        $size = @getimagesize($absolutePath);

        return false !== $size && in_array($size[2], self::READABLE_IMAGE_TYPES, true);
    }

    private function processImage(VichImageResizableInterface $entity, string $absolutePath): void
    {
        $imagine = new Imagine();
        $media = $imagine->open($absolutePath);

        // Before any size is measured off it: a photo shot upright comes out of GD lying on its side, and every width/height below would then describe the wrong one of its two dimensions
        $this->autoOrient($media, $absolutePath);

        // Resolved once from the untouched original and reused for each derivative below, so a photo's thumbnail never ends up carrying the other signature than its highres (see ImageWatermarker::prepare)
        $watermark = $entity instanceof VichWatermarkableInterface
            ? $this->imageWatermarker->prepare($entity, $media)
            : null;

        // The sibling derivatives first, and the largest of them comes back to be cut down into the stored file below rather than the upload being resampled a third time. A 24-megapixel photo costs 4.5 seconds to resample three times over and 2 seconds to resample once and work from the 2048-pixel result, for derivatives of the very same dimensions - which is how a batch of twenty photos went from timing out to fitting in a request
        $carriesWatermark = false;
        if ($entity instanceof VichMultiSizeImageInterface) {
            $media = $this->processMultiSizeDerivatives($entity, $media, $absolutePath, $watermark);
            $carriesWatermark = true;
        }

        // Capped at what the source actually holds, exactly as the highres derivative caps itself: enlarging an image invents no detail, and a source smaller than the target used to be blown up into a softer and heavier file - one that a VichMultiSizeImageInterface entity then served as its "medium" while its "highres" stayed at the original's size, the two resolutions inverted
        // For such an entity the source is now its own highres derivative, so a "medium" declared wider than the "highres" is capped to it rather than served bigger, which is that same inversion said the other way round
        $size = $media->getSize();
        $width = min($entity->getImageWidth(), $size->getWidth());
        $height = (int) ($size->getHeight() * $width / $size->getWidth());

        $media->resize(new Box($width, $height));

        // Only an entity with no derivatives of its own is stamped here: a multi-size one was cut from a highres already signed, and laying a second logo on it would double the first
        if (null !== $watermark && !$carriesWatermark) {
            $this->imageWatermarker->stamp($media, $watermark);
        }

        $media->save($absolutePath, [
            'format' => 'webp',
            'webp_quality' => 90,
        ]);

        if (method_exists($entity, 'setSize')) {
            $entity->setSize((new \SplFileInfo($absolutePath))->getSize());
        }
    }

    // Applies the orientation a camera recorded next to the pixels rather than in them: GD decodes a jpeg exactly as stored and ignores that tag entirely, so a photo shot upright is served on its side - and its watermark stamped in a corner nobody asked for.
    // Settled here once and for all rather than at display time: everything this pipeline writes is webp, a format it saves without any EXIF at all, so no downstream reader can find a tag to rotate by a second time. A file whose pixels were already turned by the operating system carries a tag reset to 1 and passes through untouched. The kept original is copied before this runs (see onPostUpload) and keeps both its pixels and its tag, so it stays re-processable.
    private function autoOrient(ImageInterface $image, string $absolutePath): void
    {
        // ext-exif is a suggestion, not a requirement - and only jpeg/tiff carry the tag at all, the suppression covering every format that has no EXIF segment to read
        if (!\function_exists('exif_read_data')) {
            return;
        }

        $exif = @exif_read_data($absolutePath);
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // The eight values of the EXIF tag, as ImageMagick's own "-auto-orient" resolves them - the script this watermark was ported from ran every photo through it, and a corner has to end up on the same side here as it did there. Imagine counts its rotations clockwise, like ImageMagick
        match ($orientation) {
            2 => $image->flipHorizontally(),
            3 => $image->rotate(180),
            4 => $image->flipVertically(),
            5 => $image->flipVertically()->rotate(90),
            6 => $image->rotate(90),
            7 => $image->flipHorizontally()->rotate(90),
            8 => $image->rotate(270),
            default => $image,
        };
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

    // Sibling files next to the entity's own stored (medium) image: a thumbnail for grid displays, and a proportionally-resized highres version for zoom - both derived from a copy() of the original so the shared $media instance stays untouched for the medium resize that follows in processImage(). Filenames match what VichMultiSizeImageInterface consumers derive themselves from their own stored filename (see e.g. GalleryBundle's GalleryMedia::getThumbnailFilename()/getHighresFilename()).
    // The thumbnail holds the whole image, fitted inside the target box (INSET) rather than cropped square to fill it (OUTBOUND): what a grid displays is a display decision, and a cropped file can't be uncropped - a square tile is one "object-fit: cover" away from an inset thumbnail, where the reverse needs the pixels back. Its file is therefore only square for a square original, getThumbnailSize() capping its longest side.
    private function processMultiSizeDerivatives(VichMultiSizeImageInterface $entity, ImageInterface $original, string $absolutePath, ?Watermark $watermark): ImageInterface
    {
        $base = preg_replace('/\.[^.]+$/', '', $absolutePath);
        $originalSize = $original->getSize();

        $highresWidth = min($entity->getHighresWidth(), $originalSize->getWidth());
        $highresHeight = (int) ($originalSize->getHeight() * $highresWidth / $originalSize->getWidth());

        // Resized in place rather than onto a copy: the upload is never needed at its own size again, every smaller size being cut from this one, and copying a 24-megapixel image costs a full allocation and flood-fill (see Imagine's Gd\Image::createImage)
        $highres = $original->resize(new Box($highresWidth, $highresHeight));

        // Signed once, here, with every smaller size then cut from the result - the signature comes down with the pixels and keeps the same share of the width in a 600-pixel thumbnail as in the 2048-pixel export, which is how the shell script this replaces produced its own sizes. One stamping per upload rather than one per derivative, and no size can end up carrying a different signature than its siblings
        if (null !== $watermark) {
            $this->imageWatermarker->stamp($highres, $watermark);
        }

        $highres->save($base . '-highres.webp', ['format' => 'webp', 'webp_quality' => 90]);

        $thumbnailSize = $entity->getThumbnailSize();
        $highres->copy()
            ->thumbnail(new Box($thumbnailSize, $thumbnailSize), ImageInterface::THUMBNAIL_INSET)
            ->save($base . '-thumb.webp', ['format' => 'webp', 'webp_quality' => 90]);

        return $highres;
    }

    // Crops/resizes to the exact target size (fixed icon roles never keep the uploaded aspect ratio) and converts to the target format - .ico has no native GD/Imagine writer, so it's hand-wrapped around a raw bitmap
    private function processFixedIcon(VichImageResizableInterface $entity, string $absolutePath, array $spec): void
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
                // Read by component rather than through the RGB class' own getters: those are not on the interface getColorAt() answers, and the ICO payload only ever needs these three values
                $color = $image->getColorAt(new Point($x, $y));
                $alpha = (int) round($color->getAlpha() * 255 / 100);
                $pixels .= pack(
                    'C4',
                    $color->getValue(ColorInterface::COLOR_BLUE),
                    $color->getValue(ColorInterface::COLOR_GREEN),
                    $color->getValue(ColorInterface::COLOR_RED),
                    $alpha
                );
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

    // Copied, not moved: the derivatives generated below are what the site serves, the original only being kept so it can be re-processed later without a re-upload
    private function keepOriginal(VichOriginalKeepableInterface $entity, string $filename, string $publicPath): void
    {
        $directory = $entity->getOriginalDirectory();
        if (null === $directory) {
            return;
        }

        // A type not on the list is not kept at all rather than copied under a guessed extension - an upload whose original cannot be named safely is one this has no business writing anywhere
        $extension = self::ORIGINAL_EXTENSIONS[(string) (new File($publicPath))->getMimeType()] ?? null;
        if (null === $extension) {
            return;
        }

        // Same base name as the stored file, so a media's four files (medium, thumbnail, high resolution, original) read as one set - only the extension differs, this being the only one of them that is not a webp
        $originalFilename = preg_replace('/\.[^.\/]+$/', '-original.' . $extension, $filename);
        $originalPath = $this->parameterBag->get('kernel.project_dir') . '/' . $directory . '/' . $originalFilename;

        $this->filesystem->mkdir(\dirname($originalPath), 0755);
        $this->filesystem->copy($publicPath, $originalPath, true);

        $entity->setOriginalFilename($originalFilename);
    }

    private function moveFileToPrivate(VichPrivateFileInterface $entity, string $filename, string $publicPath): void
    {
        $privatePath = $this->parameterBag->get('kernel.project_dir') . '/' . $entity->getPrivateDirectory() . '/' . $filename;

        $this->filesystem->mkdir(dirname($privatePath), 0755);
        $this->filesystem->copy($publicPath, $privatePath, true);
        $this->filesystem->remove($publicPath);
    }
}
