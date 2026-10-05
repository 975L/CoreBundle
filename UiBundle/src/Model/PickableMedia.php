<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// One file a PickableMediaProviderInterface offers to be reused elsewhere: web paths from the site root, no leading "/"
final class PickableMedia
{
    public function __construct(
        public readonly string $path,
        public readonly string $title,
        public readonly string $mimeType,
        public readonly ?string $thumbnailPath = null,
    ) {
    }
}
