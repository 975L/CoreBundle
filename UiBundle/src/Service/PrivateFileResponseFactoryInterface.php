<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface PrivateFileResponseFactoryInterface
{
    /**
     * Builds a downloadable BinaryFileResponse (attachment disposition) for a private/protected file already resolved to an absolute path, null if the file is missing.
     *
     * @param string $absoluteFilePath absolute path on disk, outside the public directory
     * @param string $downloadFilename the name the browser saves the file under
     */
    public function createDownloadResponse(string $absoluteFilePath, string $downloadFilename): ?BinaryFileResponse;
}
