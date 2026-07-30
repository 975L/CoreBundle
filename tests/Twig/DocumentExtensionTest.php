<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Twig\DocumentExtension;
use PHPUnit\Framework\TestCase;

class DocumentExtensionTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so the thumbnail existence check can be exercised safely with real filesystem reads (same pattern as StylesheetExtensionTest)
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/document-extension-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function createMedia(string $filename): Media
    {
        $media = new Media();
        $media->setFilename($filename);

        return $media;
    }

    public function testGetThumbnailPathReplacesExtensionNotAppendsIt(): void
    {
        // VichPdfThumbnailListener writes via str_replace('.pdf', '.webp', ...) - "document.pdf" becomes "document.webp", never "document.pdf.webp"
        touch($this->projectDir . '/public/document.webp');

        $extension = new DocumentExtension($this->projectDir);

        $this->assertSame('document.webp', $extension->getThumbnailPath($this->createMedia('document.pdf')));
    }

    public function testGetThumbnailPathReturnsNullWhenNoThumbnailWasGenerated(): void
    {
        // Ghostscript missing on the server, a fixture/placeholder media with no sidecar file, generation not finished - none of these should ever look like a real thumbnail
        $extension = new DocumentExtension($this->projectDir);

        $this->assertNull($extension->getThumbnailPath($this->createMedia('document.pdf')));
    }

    public function testGetFunctionsRegistersDocumentThumbnailPathFunction(): void
    {
        $extension = new DocumentExtension($this->projectDir);
        $names = array_map(fn ($f) => $f->getName(), $extension->getFunctions());

        $this->assertSame(['document_thumbnail_path'], $names);
    }
}
