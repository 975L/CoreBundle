<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Storage\PrivateDirectory;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Event\Event;

// After VichImageResizeListener (priority 0), which takes a document reserved to members out of public/ first: the PDF is then read where it ended up, and its thumbnail written next to it instead of being left behind under public/
#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload', priority: -10)]
class VichPdfThumbnailListener
{
    private const int THUMBNAIL_WIDTH = 400;

    private const int MAX_RESOLUTION = 300;

    private const int FALLBACK_RESOLUTION = 72;

    // About 100 MB once decoded by GD, well under a web memory_limit of a few hundred MB
    private const int MAX_PIXELS = 25_000_000;

    private readonly Filesystem $filesystem;

    // Single source of truth for the pdf -> webp naming convention this listener writes to - DocumentExtension reads it back through this same method, so the two never drift apart. Case-insensitive and anchored, a scan uploaded as ".PDF" otherwise getting its thumbnail written over itself
    public static function toWebpPath(string $pdfPath): string
    {
        return (string) preg_replace('/\.pdf$/i', '.webp', $pdfPath);
    }

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function onPostUpload(Event $event): void
    {
        $entity = $event->getObject();

        // No thumbnail for a private download (e.g. ShopBundle's paid files), which has no preview to show. A document reserved to members does get one, kept next to it outside public/ and only ever handed to who may open the document (see MediaController::thumbnail())
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
        $pdfPath = $this->parameterBag->get('kernel.project_dir') . '/' . (PrivateDirectory::resolve($entity) ?? 'public') . '/' . $filename;

        if (!$this->filesystem->exists($pdfPath)) {
            return;
        }

        // An imported thumbnail is reused as-is, Ghostscript not even being available on every host
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

    // The resolution the first page is rendered at: twice the thumbnail's width, never above 300 dpi nor above MAX_PIXELS. A fixed 300 dpi turned a poster-sized page (a family tree of 90 x 170 cm) into a 200 Mpx PNG that GD could not hold in memory, the fatal error taking the whole page save down with it
    public static function resolution(float $pageWidth, float $pageHeight, int $width): int
    {
        $resolution = min(
            self::MAX_RESOLUTION,
            72 * $width * 2 / $pageWidth,
            72 * sqrt(self::MAX_PIXELS / ($pageWidth * $pageHeight))
        );

        return max(1, (int) floor($resolution));
    }

    // Page size of the first page in points, read by pdfinfo (poppler-utils) - null when it is missing or unreadable
    /** @return array{float, float}|null */
    private function pageSize(string $pdfPath): ?array
    {
        exec(sprintf('pdfinfo -f 1 -l 1 %s 2>/dev/null', escapeshellarg($pdfPath)), $output, $returnVar);

        if (0 !== $returnVar || 1 !== preg_match('/size:\s+([\d.]+) x ([\d.]+) pts/', implode("\n", $output), $matches) || 0.0 === (float) $matches[1] || 0.0 === (float) $matches[2]) {
            return null;
        }

        return [(float) $matches[1], (float) $matches[2]];
    }

    // exec() takes $output before $returnVar, so the one that is read costs the one that is not
    private function generateThumbnail(string $pdfPath, int $width): void
    {
        // exec() is disabled on some hosts: no thumbnail rather than a crash
        if (!function_exists('exec')) {
            return;
        }

        $webpPath = self::toWebpPath($pdfPath);
        $tmpPng = sys_get_temp_dir() . '/' . uniqid() . '.png';

        // Without pdfinfo the page size is unknown: 72 dpi keeps even an A0 page around 8 Mpx
        $pageSize = $this->pageSize($pdfPath);
        $resolution = null !== $pageSize ? self::resolution($pageSize[0], $pageSize[1], $width) : self::FALLBACK_RESOLUTION;

        try {
            // Converts the PDF's first page to PNG through Ghostscript
            $cmd = sprintf(
                'gs -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>/dev/null',
                $resolution,
                escapeshellarg($tmpPng),
                escapeshellarg($pdfPath)
            );
            // $output is only there to reach $returnVar, exec() taking the two by reference in that order
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
