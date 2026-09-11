<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Controller\MediaController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

class DocumentExtension
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
    ) {
    }

    // Reuses VichPdfThumbnailListener::toWebpPath() so this only ever looks at the path that listener actually writes to. Null when no thumbnail exists under public/ (no filename at all, Ghostscript missing on the server, generation not finished for some other reason, or a fixture/placeholder media with no sidecar file at all), and for a document reserved to members, whose thumbnail is not there - see getThumbnailUrl(), which templates call
    #[AsTwigFunction('document_thumbnail_path')]
    public function getThumbnailPath(Media $media): ?string
    {
        $filename = (string) $media->getFilename();
        if ('' === $filename || $media->isMembersOnly()) {
            return null;
        }

        $webpFilename = VichPdfThumbnailListener::toWebpPath($filename);

        return file_exists($this->projectDir . '/public/' . $webpFilename) ? $webpFilename : null;
    }

    // Where a document's thumbnail is fetched from: its public address, or MediaController's route for a document reserved to members, which answers a member with the thumbnail and anybody else with a lock. Only the disk is read here, never the visitor - a block calling this is cached for everyone. Null when there is no thumbnail, the caller falling back to a plain placeholder instead of a broken <img>
    #[AsTwigFunction('document_thumbnail_url')]
    public function getThumbnailUrl(Media $media): ?string
    {
        if (!$media->isMembersOnly()) {
            $path = $this->getThumbnailPath($media);

            return null === $path ? null : $this->packages->getUrl($path);
        }

        $filename = (string) $media->getFilename();
        if ('' === $filename || null === $media->getId() || !file_exists($this->projectDir . '/' . Media::MEMBERS_ONLY_DIRECTORY . '/' . VichPdfThumbnailListener::toWebpPath($filename))) {
            return null;
        }

        return $this->urlGenerator->generate(MediaController::THUMBNAIL_ROUTE, ['id' => $media->getId()]);
    }
}
