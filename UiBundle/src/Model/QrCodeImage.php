<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// A drawn QR code as it is cached - its bytes and their type, nothing tied to the library that drew it, so the entry reads back whatever that library becomes
final class QrCodeImage
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
    ) {
    }

    // The image inlined in a page or a PDF, where a url would cost a request - or, in a PDF, one the renderer may not be allowed to make
    public function getDataUri(): string
    {
        return 'data:' . $this->mimeType . ';base64,' . base64_encode($this->content);
    }
}
