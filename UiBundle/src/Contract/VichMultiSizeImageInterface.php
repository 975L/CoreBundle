<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Opts an uploaded image into two extra derivatives generated alongside the entity's own stored file (see VichImageResizeListener::processMultiSizeDerivatives): a thumbnail for grid displays and a proportionally-resized highres version for zoom, both holding the whole image. getImageWidth() (from VichImageResizableInterface) still governs the entity's own stored ("medium") file.
interface VichMultiSizeImageInterface extends VichImageResizableInterface
{
    /**
     * @return int the longest side in pixels of the "-thumb.webp" derivative, which keeps the image's own proportions - a square tile is then a "object-fit: cover" away, where a file cropped square could never give the cut pixels back
     */
    public function getThumbnailSize(): int;

    /**
     * @return int the width in pixels of the "-highres.webp" derivative, capped at the original's own width so it is never upscaled
     */
    public function getHighresWidth(): int;
}
