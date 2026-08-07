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
 * Marks an entity whose untouched upload is copied aside before VichImageResizeListener overwrites it in place with its own downscaled webp conversion.
 *
 * Unlike VichPrivateFileInterface, which moves the stored file out of public/ and leaves nothing behind, this keeps both: the served derivatives stay where they are, the original joins them under a directory of the entity's choosing.
 */
interface VichOriginalKeepableInterface
{
    /**
     * @return string|null directory relative to the project root (e.g. "private"), null to keep no original at all - decided per entity, an upload screen being free to offer the choice
     */
    public function getOriginalDirectory(): ?string;

    /**
     * Called back with the path the original was written to, relative to that directory, so the entity can serve and delete it afterwards - null when nothing was kept.
     */
    public function setOriginalFilename(?string $filename): void;
}
