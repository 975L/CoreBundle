<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;

// Attaches placeholder media to an in-memory, never-persisted Block, standing in for whatever real media a kind's own fixture doesn't carry. The bundle ships none of those files itself - only the site actually hosting a showcase needs them, and it declares its own through PlaceholderMediaProviderInterface. Nothing declared means nothing attached: every method below hands back null and the showcase simply renders that kind without media
class BlockFixtureMediaAttacher
{
    // reset() it at the start of every request/loop building several blocks, so the rotation restarts at the same photo each time
    private int $photoCursor = 0;

    // The registry is optional so an app - or a test - building this by hand keeps working
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly ?PlaceholderMediaRegistry $placeholderMedia = null,
    ) {
    }

    public function reset(): void
    {
        $this->photoCursor = 0;
    }

    // $variant lets a specific fixture variant (e.g. slider's "freeflow") ask for a different image count than its kind's own default, see imageCount()
    public function attach(Block $block, string $kind, string $variant = ''): void
    {
        if ('portfolio_grid' === $kind) {
            foreach ($this->placeholderPortfolioProjects() as $project) {
                $block->addMedia($project);
            }

            return;
        }

        // A kind can list several video mimetypes ("video/mp4,video/webm...", see the "video" kind) - that's one accepted upload, not one placeholder each
        $videoAttached = false;

        // Every placeholder below is null as long as the app declares none, the block then simply rendering without that media rather than with a broken one
        foreach ($this->registry->getMediaTypes($kind) as $mediaType) {
            if (str_starts_with($mediaType, 'image/')) {
                $count = $this->imageCount($kind, $variant);
                for ($i = 0; $i < $count; ++$i) {
                    $image = $this->nextPlaceholderImage();
                    if (null === $image) {
                        break;
                    }
                    $block->addMedia($image);
                }
            }

            // Skipped for "freeflow", already busy demonstrating its own layout with more images, see imageCount()
            if ('freeflow' !== $variant && !$videoAttached && str_starts_with($mediaType, 'video/')) {
                $video = $this->placeholderVideo();
                if (null !== $video) {
                    $block->addMedia($video);
                }
                $videoAttached = true;
            }

            if (str_starts_with($mediaType, 'audio/')) {
                $audio = $this->placeholderAudio();
                if (null !== $audio) {
                    $block->addMedia($audio);
                }

                break;
            }

            if ('application/pdf' === $mediaType) {
                $document = $this->placeholderDocument();
                if (null !== $document) {
                    $block->addMedia($document);
                }

                break;
            }
        }
    }

    // "image_compare" is a fixed before/after pair rather than going through the generic media_multi_upload count below
    private function imageCount(string $kind, string $variant): int
    {
        if ('slider' === $kind && 'freeflow' === $variant) {
            return 5;
        }

        if ('image_compare' === $kind) {
            return 2;
        }

        if ('article' === $kind) {
            return 3;
        }

        return $this->registry->allowsMultiUpload($kind) ? 2 : 1;
    }

    // Empty when the app declares no placeholder image, rather than three cards each showing a broken one
    /**
     * @return Media[]
     */
    private function placeholderPortfolioProjects(): array
    {
        // Generic client-project copy, not tied to any real portfolio
        $projects = [
            ['Refonte e-commerce', 'Une boutique en ligne repensée pour la conversion, développée sur mesure avec Symfony.'],
            ['Application SaaS', 'Une plateforme métier sur mesure, de la conception à la mise en production.'],
            ['Site vitrine', 'Un site rapide, accessible et facile à maintenir, sans usine à gaz.'],
        ];

        $medias = [];
        foreach ($projects as $project) {
            $image = $this->nextPlaceholderImage();
            if (null === $image) {
                break;
            }

            $medias[] = $image
                ->setAlt($project[0])
                ->setLabel($project[0])
                ->setDescription($project[1])
                ->setUrl('#');
        }

        return $medias;
    }

    // Public: also used directly by a GalleryShowcaseProviderInterface implementation feeding placeholder images into its own showcase preview - null when the app declares no image, that caller then having nothing to preview either
    public function nextPlaceholderImage(): ?Media
    {
        $images = $this->placeholderMedia?->getImages() ?? [];
        if ([] === $images) {
            return null;
        }

        $filename = $images[$this->photoCursor % count($images)];
        ++$this->photoCursor;

        // mimeType set like every other placeholder below: templates telling media apart by it (e.g. blocks/Video.html.twig picking the cover image out of the block's medias) would otherwise never match a fixture image
        return $this->placeholder($filename, 'image/webp', 'Photo d\'exemple');
    }

    private function placeholderVideo(): ?Media
    {
        return $this->placeholder($this->placeholderMedia?->getVideo(), 'video/mp4', 'Vidéo d\'exemple');
    }

    private function placeholderAudio(): ?Media
    {
        return $this->placeholder($this->placeholderMedia?->getAudio(), 'audio/mpeg', 'Audio d\'exemple');
    }

    private function placeholderDocument(): ?Media
    {
        return $this->placeholder($this->placeholderMedia?->getDocument(), 'application/pdf', 'Document d\'exemple');
    }

    // $default is the mimetype expected for that slot, kept whenever the declared file's extension isn't a known one - an app is free to serve a .webm or an .ogg (see PlaceholderMediaProviderInterface), and a wrong mimetype would have templates sorting a block's medias into the wrong slot
    private function placeholder(?string $filename, string $default, string $alt): ?Media
    {
        if (null === $filename || '' === $filename) {
            return null;
        }

        // Read within the slot's own family rather than from one flat list: .ogg and .webm are container formats naming an audio file as readily as a video one, and only the slot being filled can settle it. A video declared as .ogg tagged "audio/ogg" is picked up by no template at all (blocks/Video.html.twig sorts a block's medias by mimetype), and tagging it "video/mp4" instead would have the browser skip a <source> whose type lies about the file
        $extensions = [
            'image/' => ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'avif' => 'image/avif', 'svg' => 'image/svg+xml'],
            'video/' => ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime'],
            'audio/' => ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'webm' => 'audio/webm', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4'],
            'application/' => ['pdf' => 'application/pdf'],
        ];
        $family = strstr($default, '/', true) . '/';
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        return (new Media())
            ->setFilename($filename)
            ->setMimeType($extensions[$family][$extension] ?? $default)
            ->setAlt($alt);
    }
}
