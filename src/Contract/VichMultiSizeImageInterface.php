<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Opts an uploaded image into two extra derivatives generated alongside the entity's own stored file (see VichImageResizeListener::processMultiSizeDerivatives): a square outbound-cropped thumbnail for grid displays, and a proportionally-resized highres version for zoom. getImageWidth() (from VichImageResizableInterface) still governs the entity's own stored ("medium") file.
 */
interface VichMultiSizeImageInterface extends VichImageResizableInterface
{
    /**
     * @return int the side in pixels of the square "-thumb.webp" derivative, outbound-cropped so it always fills the square
     */
    public function getThumbnailSize(): int;

    /**
     * @return int the width in pixels of the "-highres.webp" derivative, capped at the original's own width so it is never upscaled
     */
    public function getHighresWidth(): int;
}
