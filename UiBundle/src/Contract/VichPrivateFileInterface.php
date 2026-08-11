<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Marks an entity whose uploaded file lives outside public/, served only through a controller (see PrivateFileResponseFactoryInterface) rather than by the web server.
interface VichPrivateFileInterface
{
    /**
     * @return string directory relative to the project root (e.g. "private/books"), never starting with "public"
     */
    public function getPrivateDirectory(): string;
}
