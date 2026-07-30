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

// Attaches placeholder media (static bundle assets under public/images, public/videos, public/audio) to an in-memory, never-persisted Block, standing in for whatever real media a kind's own fixture doesn't carry
class BlockFixtureMediaAttacher
{
    // A small pool rather than one photo per kind: nextPlaceholderImage() rotates through them (see $photoCursor) so consecutive kinds on the same page don't repeat the same photo
    public const PLACEHOLDER_IMAGES = [
        'bundles/c975lui/images/gallery-photo-1.webp',
        'bundles/c975lui/images/gallery-photo-2.webp',
        'bundles/c975lui/images/gallery-photo-3.webp',
        'bundles/c975lui/images/gallery-photo-4.webp',
        'bundles/c975lui/images/gallery-photo-5.webp',
    ];
    public const PLACEHOLDER_VIDEO = 'bundles/c975lui/videos/gallery-video.mp4';
    public const PLACEHOLDER_AUDIO = 'bundles/c975lui/audio/gallery-audio.mp3';
    public const PLACEHOLDER_DOCUMENT = 'bundles/c975lui/documents/gallery-document.pdf';

    // A <video autoplay muted loop> wrapper, used instead of PLACEHOLDER_VIDEO directly so "video_iframe" previews don't autoplay with sound
    public const PLACEHOLDER_VIDEO_EMBED = 'bundles/c975lui/videos/gallery-video-embed.html';

    // reset() it at the start of every request/loop building several blocks, so the rotation restarts at the same photo each time
    private int $photoCursor = 0;

    public function __construct(private readonly BlockRegistry $registry)
    {
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

        foreach ($this->registry->getMediaTypes($kind) as $mediaType) {
            if (str_starts_with($mediaType, 'image/')) {
                $count = $this->imageCount($kind, $variant);
                for ($i = 0; $i < $count; ++$i) {
                    $block->addMedia($this->nextPlaceholderImage());
                }
            }

            // Skipped for "freeflow", already busy demonstrating its own layout with more images, see imageCount()
            if ('freeflow' !== $variant && !$videoAttached && str_starts_with($mediaType, 'video/')) {
                $block->addMedia($this->placeholderVideo());
                $videoAttached = true;
            }

            if (str_starts_with($mediaType, 'audio/')) {
                $block->addMedia($this->placeholderAudio());
                break;
            }

            if ('application/pdf' === $mediaType) {
                $block->addMedia($this->placeholderDocument());
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

    // @return Media[]
    private function placeholderPortfolioProjects(): array
    {
        // Generic client-project copy, not tied to any real portfolio
        $projects = [
            ['Refonte e-commerce', 'Une boutique en ligne repensée pour la conversion, développée sur mesure avec Symfony.'],
            ['Application SaaS', 'Une plateforme métier sur mesure, de la conception à la mise en production.'],
            ['Site vitrine', 'Un site rapide, accessible et facile à maintenir, sans usine à gaz.'],
        ];

        return array_map(
            fn (array $project): Media => $this->nextPlaceholderImage()
                ->setAlt($project[0])
                ->setLabel($project[0])
                ->setDescription($project[1])
                ->setUrl('#'),
            $projects
        );
    }

    // Public: also used directly by a GalleryShowcaseProviderInterface implementation feeding placeholder images into its own showcase preview
    public function nextPlaceholderImage(): Media
    {
        $filename = self::PLACEHOLDER_IMAGES[$this->photoCursor % count(self::PLACEHOLDER_IMAGES)];
        ++$this->photoCursor;

        // mimeType set like every other placeholder below: templates telling media apart by it (e.g. blocks/Video.html.twig picking the cover image out of the block's medias) would otherwise never match a fixture image
        return (new Media())
            ->setFilename($filename)
            ->setMimeType('image/webp')
            ->setAlt('Photo d\'exemple');
    }

    private function placeholderVideo(): Media
    {
        return (new Media())
            ->setFilename(self::PLACEHOLDER_VIDEO)
            ->setMimeType('video/mp4')
            ->setAlt('Vidéo d\'exemple');
    }

    private function placeholderAudio(): Media
    {
        return (new Media())
            ->setFilename(self::PLACEHOLDER_AUDIO)
            ->setMimeType('audio/mpeg')
            ->setAlt('Audio d\'exemple');
    }

    private function placeholderDocument(): Media
    {
        return (new Media())
            ->setFilename(self::PLACEHOLDER_DOCUMENT)
            ->setMimeType('application/pdf')
            ->setAlt('Document d\'exemple');
    }
}
