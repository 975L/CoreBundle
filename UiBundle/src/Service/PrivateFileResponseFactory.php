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
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PrivateFileResponseFactory implements PrivateFileResponseFactoryInterface
{
    public function createDownloadResponse(string $absoluteFilePath, string $downloadFilename): ?BinaryFileResponse
    {
        if (!file_exists($absoluteFilePath)) {
            return null;
        }

        $response = new BinaryFileResponse($absoluteFilePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadFilename);

        return $response;
    }
}
