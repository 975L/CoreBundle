<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\SharedBlockRepository;

// Serializes the shared blocks into the shape SharedBlockImportProvider expects, for the "export sync all" shortcut: a page export only carries its pointers' slugs, so without this one a site rebuilt from an export showed none of them
class SharedBlockExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly SharedBlockRepository $sharedBlockRepository,
        private readonly BlockDataExporter $blockDataExporter,
    ) {
    }

    public function getKind(): string
    {
        return SharedBlockImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->sharedBlockRepository->findAll());
    }

    // The slug travels with the name, being what the pointers of the exported pages name
    /** @param iterable<SharedBlock> $sharedBlocks */
    public function serialize(iterable $sharedBlocks): array
    {
        $files = [];
        $items = [];
        foreach ($sharedBlocks as $sharedBlock) {
            $items[] = [
                'name' => $sharedBlock->getName(),
                'slug' => $sharedBlock->getSlug(),
                'blocks' => $this->blockDataExporter->exportBlocks($sharedBlock->getBlocks(), $files),
            ];
        }

        return ['items' => $items, 'files' => $files];
    }
}
