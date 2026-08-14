<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\UiBundle\Management\BlockDataExporter;

// Serializes what the urls with no entity behind them say of themselves (see UrlMetadata) into the shape ContentExporter/UrlMetadataImportProvider expect, for the "export sync all" dashboard shortcut (see SyncAllExporter).
// Carries the share image with it, unlike RedirectExportProvider which has no upload to carry: a row's ogImage is a Media of its own, and a sync dropping it would leave prod sharing the site's default picture where the site chose another - the very thing PageExportProvider had to be fixed for
class UrlMetadataExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly BlockDataExporter $blockDataExporter,
        private readonly UrlMetadataRepository $urlMetadataRepository,
    ) {
    }

    public function getKind(): string
    {
        return UrlMetadataImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->urlMetadataRepository->findAll());
    }

    /**
     * @param iterable<\c975L\ConfigBundle\Entity\UrlMetadata> $urlMetadatas
     *
     * @return array{items: list<array<string, mixed>>, files: array<string, string>}
     */
    public function serialize(iterable $urlMetadatas): array
    {
        $files = [];
        $items = [];
        foreach ($urlMetadatas as $urlMetadata) {
            $ogImage = $urlMetadata->getOgImage();

            $items[] = [
                // The natural key, and the only column that cannot be empty - the import matches on it rather than on an id, which means nothing on the other environment
                'path' => $urlMetadata->getPath(),
                'title' => $urlMetadata->getTitle(),
                'summarySocialNetwork' => $urlMetadata->getSummarySocialNetwork(),
                'ogImage' => null !== $ogImage ? $this->blockDataExporter->exportMedia($ogImage, $files) : null,
            ];
        }

        return ['items' => $items, 'files' => $files];
    }
}
