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
 * Opts an uploaded image into being proportionally resized on upload (see VichImageResizeListener), the resized file replacing the entity's own stored one
 */
interface VichImageResizableInterface
{
    /**
     * @return int the exact width in pixels the stored file is resized to, height following the original ratio - applied unconditionally, so a narrower original is upscaled rather than left alone
     */
    public function getImageWidth(): int;
}
