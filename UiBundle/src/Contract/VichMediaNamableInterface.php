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
 * Lets an entity decide the name its uploaded file is stored under, instead of Vich's default (see UiMediaNamer).
 */
interface VichMediaNamableInterface
{
    /**
     * @return string path relative to the upload destination, extension excluded, subdirectories allowed (e.g. "medias/site/block-article-42") - it is both the value stored in the entity's filename column and the file's real location on disk
     */
    public function getVichMediaPath(): string;
}
