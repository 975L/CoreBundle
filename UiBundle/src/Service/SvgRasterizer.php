<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Turns an uploaded SVG into a PNG the GD-based icon pipeline can consume (see VichImageResizeListener), GD having no SVG decoder of its own. Optional by design: without ext-imagick every method here answers "not an SVG I can handle", and the SVG is simply refused at validation time (see FixedIconFormatValidator) instead of being stored as a silently broken icon
class SvgRasterizer
{
    // Rendered well above any target size (48px favicon, 114px apple-touch-icon), the icon pipeline then downscaling it with its own filtering - rasterizing straight at the target size leaves ImageMagick's scaler alone in charge, and it is visibly rougher at icon sizes
    private const RENDER_SIZE = 512;

    // The density one SVG user unit is one pixel at - the rendering density is scaled from it so the output lands on RENDER_SIZE whatever size the document declares
    private const BASE_DENSITY = 96;

    // Assumed for a document declaring neither a width nor a viewBox: small enough that the rendering density stays generous, the final thumbnail capping the result at RENDER_SIZE anyway
    private const DEFAULT_WIDTH = 100;

    // ImageMagick reads SVG through librsvg when that delegate is installed, and through its own (far more limited) internal renderer otherwise - either way the extension has to expose the format at all
    public static function isSupported(): bool
    {
        return extension_loaded('imagick') && [] !== \Imagick::queryFormats('SVG');
    }

    // Replaces the file by its PNG rendering, transparency kept. Answers false - leaving the file untouched - for anything that is not an SVG this can actually rasterize, so callers can hand it any upload and keep their existing handling for the rest
    public function rasterizeInPlace(string $absolutePath): bool
    {
        $rendered = $this->rasterize($absolutePath);

        return null !== $rendered && false !== file_put_contents($absolutePath, $rendered);
    }

    // Asked before the upload is accepted at all (see FixedIconFormatValidator): an SVG this can't render has to be refused at the door, since by the time the file is stored it already carries the icon role's own .ico/.png name, and a failure there would leave SVG markup under it - a file no browser can read. The rendering it throws away is then done again on the stored file (see VichImageResizeListener), a second pass deliberately kept: the two run on two different paths, and caching one across them would put state into this service to save half a second on an upload done once per site
    public function canRasterize(string $absolutePath): bool
    {
        return null !== $this->rasterize($absolutePath);
    }

    // Null for anything that is not an SVG this can turn into pixels - callers hand it any upload and keep their own handling for the rest
    private function rasterize(string $absolutePath): ?string
    {
        // Long enough a slice to clear an xml declaration, a doctype and an editor's comment header before the root tag - a document whose root tag doesn't even fit in there is left to the caller's own handling rather than rendered on a guess
        $head = (string) file_get_contents($absolutePath, false, null, 0, 8192);

        if (!self::isSupported() || !preg_match('/<svg\b[^>]*>/i', $head, $rootTag)) {
            return null;
        }

        try {
            return $this->render($absolutePath, $this->declaredWidth($rootTag[0]));
        } catch (\Throwable) {
            // A malformed or unsupported SVG (ImageMagick's internal renderer chokes on a good deal of real-world markup) must not take the whole upload down
            return null;
        }
    }

    // An SVG renders at its declared size times density/BASE_DENSITY, so the density is what is set to land on RENDER_SIZE - anything else (rendering at the target size, or upscaling a default-size rendering) hands the icon pipeline a blurry source
    private function render(string $absolutePath, ?float $declaredWidth): string
    {
        $width = $declaredWidth ?? self::DEFAULT_WIDTH;
        $density = (int) ceil(self::BASE_DENSITY * self::RENDER_SIZE / $width);

        $imagick = new \Imagick();
        $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
        $imagick->setResolution($density, $density);

        // ImageMagick flatly refuses to read an SVG declaring neither a size nor a viewBox ("must specify image size"), so it is handed the very width the density was just computed from
        if (null === $declaredWidth) {
            $imagick->setSize((int) $width, (int) $width);
        }

        $imagick->readImage($absolutePath);

        // png32 rather than png: an icon is cropped square right after, and a palette PNG would lose the alpha channel the .ico wrapper needs
        $imagick->setImageFormat('png32');
        $imagick->thumbnailImage(self::RENDER_SIZE, self::RENDER_SIZE, true);
        $blob = $imagick->getImageBlob();
        $imagick->clear();

        return $blob;
    }

    // Read straight from the root tag rather than from a first Imagick rendering, which cost about as much as the real one for this single number. Only the root tag: any child element carries a width of its own, and matching one of those would size the whole rendering on a shape inside the drawing. The lookbehind rules out the root tag's own `stroke-width`, which a plain word boundary happily matched - sizing the rendering on a line thickness instead. A relative width ("100%") is no size at all and falls through to the viewBox, itself absent from a fair share of hand-written SVGs - null then says "nothing declared", which the rendering has to handle explicitly
    private function declaredWidth(string $rootTag): ?float
    {
        if (preg_match('/(?<![-\w])width\s*=\s*["\']([\d.]+)(?:px)?["\']/', $rootTag, $matches) && $matches[1] > 0) {
            return (float) $matches[1];
        }

        if (preg_match('/\bviewBox\s*=\s*["\'][\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)/', $rootTag, $matches) && $matches[1] > 0) {
            return (float) $matches[1];
        }

        return null;
    }
}
