<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// How a QR code is drawn, in plain values so a caller never depends on the drawing library - and part of the cache key (see QrCodeGenerator), two codes drawn differently being two entries
final class QrCodeOptions
{
    public const string FORMAT_PNG = 'png';
    public const string FORMAT_SVG = 'svg';

    /**
     * @param string      $format        "png" or "svg" - an SVG draws its label as text, in the font's family when the viewer has it
     * @param string      $color         hexadecimal, 3 or 6 characters, with or without "#"
     * @param string|null $logoPath      absolute path of an image painted in the middle - the error correction level is raised to "high" with it, so the covered modules can still be read
     * @param string|null $labelFontPath absolute path of a TrueType font, Open Sans when null
     */
    public function __construct(
        public readonly string $format = self::FORMAT_PNG,
        public readonly int $size = 250,
        public readonly int $margin = 10,
        public readonly string $color = '000',
        public readonly string $backgroundColor = 'fff',
        public readonly ?string $logoPath = null,
        public readonly ?int $logoWidth = null,
        public readonly ?string $label = null,
        public readonly ?string $labelFontPath = null,
        public readonly int $labelFontSize = 16,
    ) {
    }
}
