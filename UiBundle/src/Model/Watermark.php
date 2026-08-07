<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

use Imagine\Image\ImageInterface;

// A logo resolved for one upload and reused for each of that upload's derivatives (see ImageWatermarker::prepare/stamp). Resolving it once is what keeps a photo's thumbnail carrying the same signature as its high resolution version: measured again per derivative, a corner sitting right on the light/dark threshold could tip either way and stamp two different logos on the same photo.
// Both sizes are percentages of the derivative's own width, never pixels: a logo laid at a fixed pixel size would take up half of a thumbnail and a corner of a 2048px export.
final class Watermark
{
    public function __construct(
        public readonly ImageInterface $logo,
        public readonly string $position,
        public readonly float $width,
        public readonly float $margin,
    ) {
    }
}
