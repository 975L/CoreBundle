<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Reads an image file's intrinsic pixel size, used to fill Media::$width/$height (see VichImageResizeListener and MediaDimensionsCommand). Those two numbers are what lets a browser reserve an <img>'s box before the file is downloaded - the "img-responsive" class keeps the image fluid but says nothing about its proportions, so without them the page still shifts as each image arrives
class ImageDimensionsReader
{
    // @return array{width: int, height: int}|null Null when the file is missing, or isn't an image whose size can be measured (pdf, audio, video, or an svg sized only in relative units)
    public function read(string $absolutePath): ?array
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        if ('svg' === strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            return $this->readSvg($absolutePath);
        }

        $size = @getimagesize($absolutePath);

        return false !== $size && $size[0] > 0 && $size[1] > 0 ? ['width' => $size[0], 'height' => $size[1]] : null;
    }

    // An svg carries no binary header for getimagesize() to read - its size lives in the root element's own width/height attributes, falling back to the viewBox when those are absent or given in a unit that describes no pixel count
    private function readSvg(string $absolutePath): ?array
    {
        $useInternalErrors = libxml_use_internal_errors(true);
        $svg = simplexml_load_string((string) file_get_contents($absolutePath), 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        if (false === $svg) {
            return null;
        }

        $width = $this->toPixels((string) ($svg['width'] ?? ''));
        $height = $this->toPixels((string) ($svg['height'] ?? ''));

        if (null !== $width && null !== $height) {
            return ['width' => $width, 'height' => $height];
        }

        // "minX minY width height", separated by whitespace and/or commas
        $viewBox = preg_split('/[\s,]+/', trim((string) ($svg['viewBox'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        if (4 !== count($viewBox)) {
            return null;
        }

        $width = $this->toPixels($viewBox[2]);
        $height = $this->toPixels($viewBox[3]);

        return null !== $width && null !== $height ? ['width' => $width, 'height' => $height] : null;
    }

    // "40" and "40px" state an absolute pixel count; "100%", "3em" or "10pt" depend on the rendering context, so they can't fill an HTML width/height attribute (which only ever means pixels)
    private function toPixels(string $value): ?int
    {
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*$/i', $value, $matches)) {
            return null;
        }

        $pixels = (int) round((float) $matches[1]);

        return $pixels > 0 ? $pixels : null;
    }
}
