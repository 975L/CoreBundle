<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use c975L\UiBundle\Service\MediaTranslator;
use c975L\UiBundle\Service\TranslationCopier;
use c975L\UiBundle\Validator\FixedIconFormat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Shared Block/Media rebuild for every Sync import carrying a Block collection (Page, Menu) - mirrors BlockDataExporter on the way in
class BlockDataImporter
{
    // Every scalar Media field an export carries, with the value to fall back on when the archive predates that field - keeps buildMedia() a plain mapping instead of a chain of thirteen "?? default"
    private const array MEDIA_DEFAULTS = [
        'role' => null,
        'name' => null,
        'alt' => null,
        'label' => null,
        'width' => null,
        'height' => null,
        'cssClasses' => null,
        'above' => false,
        'credits' => null,
        'rightsReserved' => false,
        'position' => 0,
        'url' => null,
        'description' => null,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FormBlockDependencyRegistry $formBlockDependencyRegistry,
        private readonly ValidatorInterface $validator,
        // Optional so a construction by hand (a test) needs no copier, and then writes no translations
        private readonly ?TranslationCopier $translationCopier = null,
        // Where the files a media shows in another language are put back, optional for the same reason
        #[Autowire(param: 'kernel.project_dir')]
        private readonly ?string $projectDir = null,
    ) {
    }

    // @return Block[]
    public function buildBlocks(array $blocksData, ?string $filesDir): array
    {
        $blocks = [];
        foreach ($blocksData as $blockData) {
            $blocks[] = $this->buildBlock($blockData, $filesDir);
        }

        return $blocks;
    }

    // A container kind's (eg. flex_columns) slots are themselves full Blocks - own kind/data/medias/slots (see Block::getSlots()/BlockDataExporter::exportBlockData()) - recursed into here so a block nested in a container round-trips like a top-level one
    private function buildBlock(array $blockData, ?string $filesDir): Block
    {
        $this->formBlockDependencyRegistry->ensureDependenciesExist($blockData);

        $block = new Block()
            ->setKind($blockData['kind'])
            ->setPosition($blockData['position'])
            ->setData($blockData['data'] ?? [])
            ->setAnimation($blockData['animation'] ?? null)
            // Absent from every archive written before the flag existed, and read as "visible" there - the same thing the column's own default says
            ->setHidden($blockData['hidden'] ?? null);

        $this->addMedias($block, $blockData['medias'] ?? [], $filesDir);

        // buildBlock() persists the slot itself, at the end of its own recursion
        foreach ($blockData['slots'] ?? [] as $slotData) {
            $block->addSlot($this->buildBlock($slotData, $filesDir));
        }

        $this->em->persist($block);
        $this->importTranslations(Translation::OWNER_BLOCK, $block, $blockData);

        return $block;
    }

    private function addMedias(Block $block, array $mediasData, ?string $filesDir): void
    {
        foreach ($mediasData as $mediaData) {
            $media = $this->buildMedia($mediaData, $filesDir);
            $this->em->persist($media);
            $block->addMedia($media);
        }
    }

    // Rebuilds a Media from its exported metadata, its file read straight from the extracted zip archive (see ContentImportController) and run through Vich's normal upload pipeline via ReplacingFile (a plain File is silently ignored by Vich's UploadHandler, see PageCrudController::cloneMedia()), so filename/size/mimeType/thumbnails all get regenerated here rather than trusting the exporting environment's values. Public: also used directly for a standalone Media not attached to any Block (eg. Page::$ogImage)
    public function buildMedia(array $mediaData, ?string $filesDir): Media
    {
        $values = [];
        foreach (self::MEDIA_DEFAULTS as $key => $default) {
            $values[$key] = $mediaData[$key] ?? $default;
        }

        $media = new Media()
            ->setRole($values['role'])
            ->setName($values['name'])
            ->setAlt($values['alt'])
            ->setLabel($values['label'])
            ->setWidth($values['width'])
            ->setHeight($values['height'])
            ->setCssClasses($values['cssClasses'])
            ->setAbove($values['above'])
            ->setCredits($values['credits'])
            ->setRightsReserved($values['rightsReserved'])
            ->setPosition($values['position'])
            ->setUrl($values['url'])
            ->setDescription($values['description']);

        if (null !== $filesDir && isset($mediaData['file'])) {
            $media->setFile(new ReplacingFile($filesDir . '/' . $mediaData['file'], true, true, true));
        }

        // Read by VichPdfThumbnailListener on flush, so it reuses this thumbnail instead of Ghostscript
        if (null !== $filesDir && isset($mediaData['thumbnail'])) {
            $media->setImportedThumbnailPath($filesDir . '/' . $mediaData['thumbnail']);
        }

        $mediaData = $this->withoutForeignTranslatedFiles($mediaData);
        $this->importTranslations(Translation::OWNER_MEDIA, $media, $mediaData);
        $this->importTranslatedFiles($mediaData, $filesDir);

        return $media;
    }

    // Drops a language's file not named after the media's own the way MediaTranslator::stageFile names it, before its translation reaches a row MediaTranslationFileListener would one day remove it by: an archive is never trusted to name index.php
    private function withoutForeignTranslatedFiles(array $mediaData): array
    {
        if (!isset($mediaData['translations']) || !is_array($mediaData['translations'])) {
            return $mediaData;
        }

        $mediaFilename = is_string($mediaData['originalFilename'] ?? null) ? $mediaData['originalFilename'] : '';
        foreach ($mediaData['translations'] as $locale => $fields) {
            if (is_array($fields) && array_key_exists(MediaTranslator::FILE, $fields) && !MediaTranslator::isTranslatedFilePath($fields[MediaTranslator::FILE], $mediaFilename)) {
                unset($mediaData['translations'][$locale][MediaTranslator::FILE]);
            }
        }

        return $mediaData;
    }

    // Puts the files a media shows in another language back where their translations say, beside the site's other medias: the row carries the path, the archive the bytes (see BlockDataExporter::exportMedia)
    private function importTranslatedFiles(array $mediaData, ?string $filesDir): void
    {
        if (null === $filesDir || null === $this->projectDir || !isset($mediaData['translatedFiles']) || !is_array($mediaData['translatedFiles'])) {
            return;
        }

        foreach ($mediaData['translatedFiles'] as $locale => $archivePath) {
            $path = $mediaData['translations'][$locale][MediaTranslator::FILE] ?? null;
            $source = $filesDir . '/' . $archivePath;

            // The path was vetted by withoutForeignTranslatedFiles, the archive's own name is not
            if (null === $path || !MediaTranslator::isPublicPath($archivePath) || !is_file($source)) {
                continue;
            }

            $target = $this->projectDir . '/public/' . $path;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0o755, true);
            }
            copy($source, $target);
        }
    }

    // Hands the archive's translations to TranslationCopier, which writes them once the flush has given the row its id. An archive written before they were exported says nothing about them, and the row keeps what it has. Public: also used for the entity owning the Blocks (eg. a Page's title)
    public function importTranslations(string $ownerType, object $row, array $data): void
    {
        if (null !== $this->translationCopier && isset($data['translations']) && is_array($data['translations'])) {
            $this->translationCopier->carry($ownerType, $row, $data['translations']);
        }
    }

    // The share image a Page or a UrlMetadata owns alone, marked before OgImageType's own FixedIconFormat check runs on it - null when the conversion can't handle the file, the caller then keeping the image it already has rather than one stored as SVG markup under a .webp name
    public function buildOgImage(array $mediaData, ?string $filesDir): ?Media
    {
        $ogImage = $this->buildMedia($mediaData, $filesDir)->markAsOgImage();

        return 0 === count($this->validator->validate($ogImage, new FixedIconFormat())) ? $ogImage : null;
    }
}
