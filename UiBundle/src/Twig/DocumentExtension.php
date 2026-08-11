<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DocumentExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('document_thumbnail_path', $this->getThumbnailPath(...)),
        ];
    }

    // Reuses VichPdfThumbnailListener::toWebpPath() so this only ever looks at the path that listener actually writes to. Null when no thumbnail exists (no filename at all, Ghostscript missing on the server, generation not finished for some other reason, or a fixture/placeholder media with no sidecar file at all) - the caller falls back to a plain placeholder instead of a broken <img>.
    public function getThumbnailPath(Media $media): ?string
    {
        $filename = (string) $media->getFilename();
        if ('' === $filename) {
            return null;
        }

        $webpFilename = VichPdfThumbnailListener::toWebpPath($filename);

        return file_exists($this->projectDir . '/public/' . $webpFilename) ? $webpFilename : null;
    }
}
