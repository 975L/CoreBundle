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
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Validator\FixedIconFormat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "site_graphic" content export (see SiteGraphicExportProvider) - a singleton role (favicon, apple-touch-icon, og-image, logo) matches by its own role, the one natural key it has. The repeatable "error-image" role has none (several rows share it, forming a pool with nothing else distinguishing them), so re-importing replaces its whole pool instead of piling duplicates on top of whatever already exists
class SiteGraphicImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_graphic';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaRepository $mediaRepository,
        private readonly ValidatorInterface $validator,
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
        $clearedRepeatableRoles = [];

        foreach ($items as $item) {
            $file = null !== $filesDir && isset($item['file']) ? new ReplacingFile($filesDir . '/' . $item['file'], true, true, true) : null;

            // The upload form's own FixedIconFormat check, run on a throwaway row: a file the conversion can't handle would otherwise be stored as markup under an .ico/.png/.webp name, and the row it would replace keeps its current file
            if (null !== $file && !$this->isConvertible($item['role'], $file)) {
                continue;
            }

            [$media, $isNew] = $this->resolveMedia($item['role'], $clearedRepeatableRoles);

            if (null !== $file) {
                $media->setFile($file);
            }

            $this->em->persist($media);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // Validated before the real row is touched, a managed Media given a file being uploaded on flush whatever is decided afterwards
    private function isConvertible(string $role, ReplacingFile $file): bool
    {
        $candidate = new Media()->setRole($role);
        $candidate->setFile($file);

        return 0 === count($this->validator->validate($candidate, new FixedIconFormat()));
    }

    // A singleton role is updated in place, a repeatable one rebuilt, its rows dropped once per role
    // @return array{0: Media, 1: bool} - the media to fill in, and whether it had to be created
    private function resolveMedia(string $role, array &$clearedRepeatableRoles): array
    {
        if (in_array($role, Media::getSingletonRoles(), true)) {
            $media = $this->mediaRepository->findOneByRole($role);

            return [$media ?? new Media()->setRole($role), null === $media];
        }

        if (!isset($clearedRepeatableRoles[$role])) {
            foreach ($this->mediaRepository->findBy(['role' => $role]) as $existing) {
                $this->em->remove($existing);
            }
            $clearedRepeatableRoles[$role] = true;
        }

        return [new Media()->setRole($role), true];
    }
}
