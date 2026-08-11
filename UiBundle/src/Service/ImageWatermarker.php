<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\VichWatermarkableInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Model\Watermark;
use c975L\UiBundle\Repository\MediaRepository;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Point;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

// Stamps a site-wide signature into a corner of an uploaded photo, picking between a dark and a light version of it on the luminance of that very corner (see VichWatermarkableInterface). Called by VichImageResizeListener once per upload to resolve the logo, then once per derivative to lay it down.
class ImageWatermarker
{
    // Defaults ported from the shell script this replaces: a 330x210 signature laid 10px inside the corner of a 2400px-wide export, expressed here as proportions of the width so a thumbnail and a high resolution version carry the same logo at the same relative size
    private const float DEFAULT_WIDTH = 13.75;
    private const float DEFAULT_MARGIN = 0.42;

    // Mid-grey on a 0-255 scale, the threshold that script split its two signatures on
    private const float LUMINANCE_THRESHOLD = 128.0;

    // Points read per side when averaging a corner: 16x16 is 256 readings whatever the upload's size, where the script it comes from averaged every pixel of the region - the two only ever disagree on a corner already sitting on the threshold, which either signature reads equally poorly on
    private const int LUMINANCE_SAMPLES = 16;

    // Rec. 709 luma coefficients, what ImageMagick's "-colorspace Gray" applies - the script measured its corners through it, and a corner has to fall on the same side of the threshold here as it did there
    private const float LUMA_RED = 0.2126;
    private const float LUMA_GREEN = 0.7152;
    private const float LUMA_BLUE = 0.0722;

    /**
     * @var array<string, ImageInterface|null> the two signatures, once each per request - see logo()
     */
    private array $logos = [];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    // The logo to stamp on every derivative of this upload, or null when there is nothing to stamp: an entity asking for none, or a site that has uploaded neither signature
    public function prepare(VichWatermarkableInterface $entity, ImageInterface $source): ?Watermark
    {
        if (!$entity->wantsWatermark()) {
            return null;
        }

        $position = $this->position($entity->getWatermarkPosition());

        // A width of zero is no logo at all and can only be a typo, where a margin of zero is a signature laid flush against the edge - a choice, and one the script this replaces could express
        $width = $this->percentage('ui-watermark-width', self::DEFAULT_WIDTH, false);
        $margin = $this->percentage('ui-watermark-margin', self::DEFAULT_MARGIN, true);

        $onLight = $this->logo(Media::ROLE_WATERMARK_ON_LIGHT);
        $onDark = $this->logo(Media::ROLE_WATERMARK_ON_DARK);

        // A site that uploaded only one of the two gets it on every photo, readable or not: guessing a second signature from the first is not this service's call, and refusing to stamp anything would silently drop a watermark the admin did ask for
        $logo = match (true) {
            null === $onLight => $onDark,
            null === $onDark => $onLight,
            // Measured on the source rather than on each derivative, so the whole set carries one signature (see Watermark). Downscaling averages pixels, so the corner of a derivative holds the same average as the corner of the source it came from
            default => $this->isDarkCorner($source, $position, $width, $margin, $this->ratio($onLight)) ? $onDark : $onLight,
        };

        return null === $logo ? null : new Watermark($logo, $position, $width, $margin);
    }

    // Lays the resolved logo into the image's corner, in place. Sized off this image's own width, so the same Watermark serves a 2048px export and a 600px thumbnail
    public function stamp(ImageInterface $image, Watermark $watermark): void
    {
        $size = $image->getSize();
        $logoWidth = (int) round($size->getWidth() * $watermark->width / 100);
        $logoHeight = (int) round($logoWidth * $this->ratio($watermark->logo));
        $margin = (int) round($size->getWidth() * $watermark->margin / 100);

        // A logo that would come out under a pixel, or one that no longer fits inside the image once its margins are counted, is not stamped at all rather than squeezed into something unreadable
        if ($logoWidth < 1 || $logoHeight < 1) {
            return;
        }

        if ($logoWidth + 2 * $margin > $size->getWidth() || $logoHeight + 2 * $margin > $size->getHeight()) {
            return;
        }

        $image->paste(
            $watermark->logo->copy()->resize(new Box($logoWidth, $logoHeight)),
            $this->point($watermark->position, $size->getWidth(), $size->getHeight(), $logoWidth, $logoHeight, $margin)
        );
    }

    // True when the corner the logo is about to occupy is darker than mid-grey, and so calls for the light signature
    private function isDarkCorner(ImageInterface $image, string $position, float $width, float $margin, float $logoRatio): bool
    {
        $size = $image->getSize();
        $sampleWidth = (int) round($size->getWidth() * $width / 100);
        $sampleHeight = (int) round($sampleWidth * $logoRatio);
        $offset = (int) round($size->getWidth() * $margin / 100);

        // A sample that would not fit is read over the whole image instead: an average taken from a rectangle clamped back inside the frame would describe a region the logo never lands on
        $fits = $sampleWidth >= 1
            && $sampleHeight >= 1
            && $sampleWidth + 2 * $offset <= $size->getWidth()
            && $sampleHeight + 2 * $offset <= $size->getHeight();

        $origin = $fits
            ? $this->point($position, $size->getWidth(), $size->getHeight(), $sampleWidth, $sampleHeight, $offset)
            : new Point(0, 0);

        if (!$fits) {
            $sampleWidth = $size->getWidth();
            $sampleHeight = $size->getHeight();
        }

        return $this->averageLuminance($image, $origin, $sampleWidth, $sampleHeight) < self::LUMINANCE_THRESHOLD;
    }

    // The mean luminance of a region on a 0-255 scale, read off a fixed grid of points spread across it.
    // Sampled rather than measured exactly, and above all without copying anything: every Imagine operation that would hand back the region as an image of its own (copy, crop, resize) allocates a full truecolor buffer and flood-fills it (see Imagine's Gd\Image::createImage), which on a 24-megapixel upload costs more than the resizing this whole pipeline exists to do - all for a single number. A few hundred points settle which side of mid-grey a corner falls on just as well
    private function averageLuminance(ImageInterface $image, Point $origin, int $width, int $height): float
    {
        $columns = min(self::LUMINANCE_SAMPLES, $width);
        $rows = min(self::LUMINANCE_SAMPLES, $height);
        $total = 0.0;

        for ($column = 0; $column < $columns; ++$column) {
            for ($row = 0; $row < $rows; ++$row) {
                // The middle of each cell of the grid, so the edges of the region weigh no more than its inside
                $color = $image->getColorAt(new Point(
                    $origin->getX() + (int) (($column + 0.5) * $width / $columns),
                    $origin->getY() + (int) (($row + 0.5) * $height / $rows)
                ));

                $total += self::LUMA_RED * $color->getValue(ColorInterface::COLOR_RED)
                    + self::LUMA_GREEN * $color->getValue(ColorInterface::COLOR_GREEN)
                    + self::LUMA_BLUE * $color->getValue(ColorInterface::COLOR_BLUE);
            }
        }

        return $total / ($columns * $rows);
    }

    // Where the logo's top-left corner lands, the margin being counted from whichever edges its corner touches
    private function point(string $position, int $imageWidth, int $imageHeight, int $logoWidth, int $logoHeight, int $margin): Point
    {
        $right = $imageWidth - $logoWidth - $margin;
        $bottom = $imageHeight - $logoHeight - $margin;

        return match ($position) {
            VichWatermarkableInterface::POSITION_TOP_LEFT => new Point($margin, $margin),
            VichWatermarkableInterface::POSITION_TOP_RIGHT => new Point($right, $margin),
            VichWatermarkableInterface::POSITION_BOTTOM_LEFT => new Point($margin, $bottom),
            default => new Point($right, $bottom),
        };
    }

    // The entity's own corner when it asked for one, the site-wide setting otherwise - anything off the list falls back to the bottom right, a config value being admin-typed text
    private function position(?string $requested): string
    {
        if (null !== $requested && in_array($requested, VichWatermarkableInterface::POSITIONS, true)) {
            return $requested;
        }

        $configured = $this->configService->hasParameter('ui-watermark-position')
            ? (string) $this->configService->get('ui-watermark-position')
            : '';

        return in_array($configured, VichWatermarkableInterface::POSITIONS, true)
            ? $configured
            : VichWatermarkableInterface::POSITION_BOTTOM_RIGHT;
    }

    // A proportion of the image's width, read off an admin-typed config value: anything that is not a number, or a negative one, leaves the default in place
    private function percentage(string $key, float $default, bool $zeroAllowed): float
    {
        if (!$this->configService->hasParameter($key)) {
            return $default;
        }

        $value = $this->configService->get($key);
        if (!is_numeric($value)) {
            return $default;
        }

        $percentage = (float) $value;

        return $percentage > 0 || ($zeroAllowed && 0.0 === $percentage) ? $percentage : $default;
    }

    // Kept for the whole request: a batch upload calls prepare() once per photo, and the two signatures it looks up are the same two files every time - fifty photos used to mean a hundred queries and a hundred image opens stamp() pastes a copy() of what it finds here rather than the instance itself, so nothing a photo does to its logo reaches the next one
    private function logo(string $role): ?ImageInterface
    {
        if (!array_key_exists($role, $this->logos)) {
            $this->logos[$role] = $this->loadLogo($role);
        }

        return $this->logos[$role];
    }

    // Null for a role never uploaded, and for a row whose file has since gone missing from disk - a watermark is not worth crashing an upload over
    private function loadLogo(string $role): ?ImageInterface
    {
        $filename = $this->mediaRepository->findOneByRole($role)?->getFilename();
        if (null === $filename) {
            return null;
        }

        $path = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        return new Imagine()->open($path);
    }

    // Height over width, so a logo keeps its proportions whatever width it is laid at
    private function ratio(ImageInterface $logo): float
    {
        $size = $logo->getSize();

        return $size->getHeight() / $size->getWidth();
    }
}
