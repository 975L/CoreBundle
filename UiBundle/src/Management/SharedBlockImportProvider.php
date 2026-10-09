<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\SharedBlockRepository;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_shared_block" content export (see SharedBlockExportProvider) - matches by slug, the key the pointers of the imported pages name. Blocks have no natural key of their own, so the whole run is replaced, as MenuImportProvider does for a menu
class SharedBlockImportProvider implements ImportProviderInterface
{
    public const string KIND = 'site_shared_block';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SharedBlockRepository $sharedBlockRepository,
        private readonly BlockDataImporter $blockDataImporter,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $isNew = $this->importItem($item, $filesDir);
            if (null !== $isNew) {
                $isNew ? $created++ : $updated++;
            }
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // One shared block written from its exported item: true when created, false when replaced, null for an item without slug, which no pointer could ever name
    /** @param array<string, mixed> $item */
    private function importItem(array $item, ?string $filesDir): ?bool
    {
        $slug = (string) ($item['slug'] ?? '');
        if ('' === $slug) {
            return null;
        }

        $sharedBlock = $this->sharedBlockRepository->findOneBySlug($slug);
        $isNew = null === $sharedBlock;
        $sharedBlock ??= new SharedBlock()->setSlug($slug);
        $sharedBlock->setName((string) ($item['name'] ?? $slug));

        foreach ($sharedBlock->getBlocks()->toArray() as $existingBlock) {
            $sharedBlock->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($item['blocks'] ?? [], $filesDir) as $block) {
            $sharedBlock->addBlock($block);
        }

        $this->em->persist($sharedBlock);

        return $isNew;
    }
}
