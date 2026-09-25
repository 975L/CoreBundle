<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\ArchiveFileRegistrar;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\MediaTranslator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Shared Block/Media serialization for every Sync export carrying a Block collection (Page, Menu) - keeps the recursive container-slot walk in one place instead of duplicated per entity. Mirrors BlockDataImporter on the way back in
class BlockDataExporter
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        // Optional so a construction by hand (a test) needs no repository, and then carries no translations
        private readonly ?TranslationRepository $translationRepository = null,
    ) {
    }

    // @param iterable<Block> $blocks
    public function exportBlocks(iterable $blocks, array &$files): array
    {
        $data = [];
        foreach ($blocks as $block) {
            $data[] = $this->exportBlockData($block, $files);
        }

        return $data;
    }

    // A container kind's (eg. flex_columns) slots are themselves full Blocks - own kind/data/medias/slots (see Block::getSlots()) - recursed into here so a block nested in a container isn't silently dropped from the export
    private function exportBlockData(Block $block, array &$files): array
    {
        $medias = [];
        foreach ($block->getMedias() as $media) {
            $mediaData = $this->exportMedia($media, $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        $slots = [];
        foreach ($block->getSlots() as $slot) {
            $slots[] = $this->exportBlockData($slot, $files);
        }

        return $this->withTranslations([
            'kind' => $block->getKind(),
            'position' => $block->getPosition(),
            'data' => $block->getData(),
            'animation' => $block->getAnimation(),
            'hidden' => $block->isHidden(),
            'medias' => $medias,
            'slots' => $slots,
        ], Translation::OWNER_BLOCK, $block->getId());
    }

    // Reads the Media's physical file from disk and registers it for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a 'file' reference instead of embedding its bytes - same disk-path convention as PageCrudController::cloneMedia(). Returns null (skipped by the caller) when there is no file or it can't be read, rather than exporting a broken reference. Public: also used directly for a standalone Media not attached to any Block (eg. Page::$ogImage)
    public function exportMedia(Media $media, array &$files): ?array
    {
        $filename = $media->getFilename();
        if (null === $filename) {
            return null;
        }

        $registered = ArchiveFileRegistrar::register($this->projectDir, $filename, $files);
        if (null === $registered) {
            return null;
        }

        // A PDF's .webp thumbnail is a sidecar file, carried along so an import needn't rerun Ghostscript
        $thumbnail = null;
        $webpFilename = VichPdfThumbnailListener::toWebpPath($filename);
        if ($webpFilename !== $filename) {
            $registeredThumbnail = ArchiveFileRegistrar::register($this->projectDir, $webpFilename, $files);
            $thumbnail = $registeredThumbnail['archivePath'] ?? null;
        }

        $data = $this->withTranslations([
            'role' => $media->getRole(),
            'name' => $media->getName(),
            'alt' => $media->getAlt(),
            'label' => $media->getLabel(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'cssClasses' => $media->getCssClasses(),
            'above' => $media->isAbove(),
            'credits' => $media->getCredits(),
            'rightsReserved' => $media->isRightsReserved(),
            'position' => $media->getPosition(),
            'url' => $media->getUrl(),
            'description' => $media->getDescription(),
            'originalFilename' => $registered['originalFilename'],
            'file' => $registered['archivePath'],
            'thumbnail' => $thumbnail,
        ], Translation::OWNER_MEDIA, $media->getId());

        return $this->withTranslatedFiles($data, $files);
    }

    // The files a media shows in other languages travel with it, their translations naming a path the importing side has no bytes for otherwise
    private function withTranslatedFiles(array $data, array &$files): array
    {
        foreach ($data['translations'] ?? [] as $locale => $fields) {
            $translatedFile = $fields[MediaTranslator::FILE] ?? null;
            $registeredFile = null === $translatedFile ? null : ArchiveFileRegistrar::register($this->projectDir, $translatedFile, $files);
            if (null !== $registeredFile) {
                $data['translatedFiles'][$locale] = $registeredFile['archivePath'];
                continue;
            }

            // A path whose file is gone from the disk would land as a broken picture, where the media's own file still shows
            unset($data['translations'][$locale][MediaTranslator::FILE]);
            if ([] === $data['translations'][$locale]) {
                unset($data['translations'][$locale]);
            }
        }

        if ([] === ($data['translations'] ?? null)) {
            unset($data['translations']);
        }

        return $data;
    }

    // Adds what a row says in the site's other languages (locale => field => value), carried in the archive since translations name their owner by an id the importing side will not share. Left out when there are none, the shape of a single-language site's archive unchanged. Public: also used for the entity owning the Blocks (eg. a Page's title)
    public function withTranslations(array $data, string $ownerType, ?int $ownerId): array
    {
        $translations = null === $ownerId || null === $this->translationRepository ? [] : $this->translationRepository->findByOwner($ownerType, $ownerId);
        if ([] !== $translations) {
            $data['translations'] = $translations;
        }

        return $data;
    }
}
