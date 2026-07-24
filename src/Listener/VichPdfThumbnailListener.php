<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Vich\UploaderBundle\Event\Event;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichPdfThumbnailListener
{
    private const THUMBNAIL_WIDTH = 400;

    private Filesystem $filesystem;

    // Single source of truth for the pdf -> webp naming convention this listener writes to - DocumentExtension::getThumbnailPath() reads it back through this same method, so the two never drift apart
    public static function toWebpPath(string $pdfPath): string
    {
        return str_replace('.pdf', '.webp', $pdfPath);
    }

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function onPostUpload(Event $event): void
    {
        $entity = $event->getObject();

        // Pas de thumbnail pour les fichiers privés (simple lien de téléchargement, ex. ShopBundle)
        if ($entity instanceof VichPrivateFileInterface) {
            return;
        }

        if (!method_exists($entity, 'getFile') || null === $entity->getFile()) {
            return;
        }

        if ('pdf' !== strtolower($entity->getFile()->getExtension())) {
            return;
        }

        $mapping = $event->getMapping();
        $filename = $mapping->getFileName($entity);
        $pdfPath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;

        if (!$this->filesystem->exists($pdfPath)) {
            return;
        }

        // A Sync import already carries this PDF's thumbnail in its archive (see BlockDataImporter) - reuse it
        // as-is instead of re-running Ghostscript, which may not even be available on this host (see below)
        if ($entity instanceof Media && null !== $entity->getImportedThumbnailPath()) {
            if ($this->filesystem->exists($entity->getImportedThumbnailPath())) {
                $this->filesystem->copy($entity->getImportedThumbnailPath(), self::toWebpPath($pdfPath), true);
            }

            return;
        }

        $width = $entity instanceof VichImageResizableInterface
            ? $entity->getImageWidth()
            : self::THUMBNAIL_WIDTH;

        $this->generateThumbnail($pdfPath, $width);
    }

    private function generateThumbnail(string $pdfPath, int $width): void
    {
        // exec() est désactivé sur certains hébergements mutualisés (ex. Infomaniak) : pas de thumbnail plutôt qu'un crash
        if (!function_exists('exec')) {
            return;
        }

        $webpPath = self::toWebpPath($pdfPath);
        $tmpPng = sys_get_temp_dir() . '/' . uniqid() . '.png';

        try {
            // Convertit la 1ère page du PDF en PNG via Ghostscript
            $cmd = sprintf(
                'gs -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>/dev/null',
                escapeshellarg($tmpPng),
                escapeshellarg($pdfPath)
            );
            exec($cmd, $output, $returnVar);

            if (0 !== $returnVar || !file_exists($tmpPng)) {
                return;
            }

            $imagine = new Imagine();
            $image = $imagine->open($tmpPng);
            $size = $image->getSize();
            $height = (int) ($size->getHeight() * $width / $size->getWidth());

            $image
                ->resize(new Box($width, $height))
                ->save($webpPath, [
                    'format' => 'webp',
                    'webp_quality' => 85,
                ]);
        } finally {
            if (file_exists($tmpPng)) {
                unlink($tmpPng);
            }
        }
    }
}
