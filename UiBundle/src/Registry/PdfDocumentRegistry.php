<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\PdfDocumentSourceInterface;

// Collects the PDF documents the satellite bundles declare, so PdfThumbnailHealthCheckProvider reports on the whole site rather than on this bundle's own library alone. Empty as long as nothing is declared, the check then reading its own medias and nothing more
class PdfDocumentRegistry
{
    /**
     * @var list<PdfDocumentSourceInterface>
     */
    private array $providers = [];

    // Called once per provider by PdfDocumentSourcePass
    public function addProvider(PdfDocumentSourceInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Read at run time rather than at registration: a source queries its own table, which an installed bundle has no business touching while the container is being built
    /** @return list<array{filename: string, label: string, editUrl: ?string}> */
    public function getDocuments(): array
    {
        $documents = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getPdfDocuments() as $document) {
                // A source with nothing to say for a row - a fixture, a media whose file never landed - is skipped rather than reported under an empty path
                if ('' !== $document['filename']) {
                    $documents[] = $document;
                }
            }
        }

        return $documents;
    }
}
