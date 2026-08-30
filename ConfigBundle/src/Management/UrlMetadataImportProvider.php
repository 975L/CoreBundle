<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_url_metadata" content export (see UrlMetadataExportProvider) - matches by path, UrlMetadata's own unique constraint, never by an id that means nothing on the other environment
class UrlMetadataImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_url_metadata';

    public function __construct(
        private readonly BlockDataImporter $blockDataImporter,
        private readonly EntityManagerInterface $em,
        private readonly UrlMetadataRepository $urlMetadataRepository,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    // The path is the row's whole identity: without one there is nothing to match, nothing to look up at render time, and the unique constraint would reject it anyway
    private function pathOf(array $item): ?string
    {
        $path = $item['path'] ?? null;

        return null !== $path && '' !== trim((string) $path) ? (string) $path : null;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $path = $this->pathOf($item);
            if (null === $path) {
                continue;
            }

            $urlMetadata = $this->urlMetadataRepository->findOneByPath('/' . trim($path, '/'));
            $isNew = null === $urlMetadata;
            $urlMetadata ??= new UrlMetadata();

            $urlMetadata
                ->setPath($path)
                ->setTitle($item['title'] ?? null)
                ->setSummarySocialNetwork($item['summarySocialNetwork'] ?? null);

            $this->replaceOgImage($urlMetadata, $item['ogImage'] ?? null, $filesDir);

            $this->em->persist($urlMetadata);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // The ogImage is exclusively owned by this row (see UrlMetadata::$ogImage's cascade) and no listener orphan-removes it, so it is dropped by hand before a replacement is built - the same handling a Page's own gets (see SiteBundle's PageImportProvider)
    private function replaceOgImage(UrlMetadata $urlMetadata, ?array $ogImageData, ?string $filesDir): void
    {
        $existing = $urlMetadata->getOgImage();
        if (null !== $existing) {
            $urlMetadata->setOgImage(null);
            $this->em->remove($existing);
        }

        if (null === $ogImageData) {
            return;
        }

        $ogImage = $this->blockDataImporter->buildMedia($ogImageData, $filesDir);
        $this->em->persist($ogImage);
        $urlMetadata->setOgImage($ogImage);
    }
}
