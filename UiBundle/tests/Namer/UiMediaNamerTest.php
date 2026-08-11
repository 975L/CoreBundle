<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Namer;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Namer\UiMediaNamer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class UiMediaNamerTest extends TestCase
{
    private string $sandboxDir;

    // Sandboxes each test behind its own throwaway directory holding the "uploaded" file
    protected function setUp(): void
    {
        $this->sandboxDir = sys_get_temp_dir() . '/ui-media-namer-test-' . uniqid();
        mkdir($this->sandboxDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sandboxDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->sandboxDir);
    }

    // Writes real bytes to disk so File::getMimeType()/getExtension() reflect a genuine file, not a stub
    private function createFile(string $filename, string $content): File
    {
        $path = $this->sandboxDir . '/' . $filename;
        file_put_contents($path, $content);

        return new File($path);
    }

    private function pngContent(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    // Minimal PropertyMapping wired to read/write the "file" property through getFile()/setFile()
    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->sandboxDir, 'uri_prefix' => '']);

        return $mapping;
    }

    public function testThrowsWhenEntityDoesNotImplementVichMediaNamableInterface(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must implement VichMediaNamableInterface');
        $namer->name(new \stdClass(), $this->createMapping());
    }

    public function testThrowsWhenFileDoesNotExistOnDisk(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setFile(new File($this->sandboxDir . '/missing.png', false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $namer->name($media, $this->createMapping());
    }

    // Favicon has a fixed spec (48x48 .ico) - the extension always comes from the spec, never from the upload
    public function testFaviconUsesFixedSpecFormatRegardlessOfUploadedMimeType(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setRole(Media::ROLE_FAVICON);
        $media->setFile($this->createFile('upload.png', $this->pngContent()));

        $this->assertSame('favicon.ico', $namer->name($media, $this->createMapping()));
    }

    // Apple touch icon has a fixed spec (114x114 .png)
    public function testAppleTouchIconUsesFixedSpecFormat(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setRole(Media::ROLE_APPLE_TOUCH_ICON);
        $media->setFile($this->createFile('upload.png', $this->pngContent()));

        $this->assertSame('apple-touch-icon.png', $namer->name($media, $this->createMapping()));
    }

    // og-image is a singleton role but has no fixed spec: extension is determined from the upload, and raster formats get converted to webp
    public function testOgImageSingletonConvertsRasterUploadToWebp(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setRole(Media::ROLE_OG_IMAGE);
        $media->setFile($this->createFile('upload.png', $this->pngContent()));

        $this->assertSame('og-image.webp', $namer->name($media, $this->createMapping()));
    }

    // Non-singleton medias (block content) get a unique suffix appended, and raster uploads convert to webp
    public function testBlockMediaAppendsUniqueSuffixAndConvertsToWebp(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $block = new Block();
        $block->setKind('article');

        $media = new Media();
        $media->setBlock($block);
        $media->setFile($this->createFile('upload.jpg', "\xFF\xD8\xFF\xE0" . str_repeat("\0", 20)));

        $name = $namer->name($media, $this->createMapping());

        $this->assertMatchesRegularExpression('#^medias/site/block-article-[a-z0-9]+-[a-z0-9]+\.webp$#', $name);
    }

    // An admin-typed name takes over the block-kind path entirely (kept dir, dropped kind/id), slugified for a readable, URL-safe filename
    public function testNameOverridesBlockPathWithSlug(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $block = new Block();
        $block->setKind('document_download');

        $media = new Media();
        $media->setBlock($block);
        $media->setName('Rapport annuel');
        $media->setFile($this->createFile('upload.pdf', '%PDF-1.4' . str_repeat("\0", 20)));

        $name = $namer->name($media, $this->createMapping());

        $this->assertMatchesRegularExpression('#^medias/site/rapport-annuel-[a-z0-9]+\.pdf$#', $name);
    }

    // A blank/whitespace-only name is treated as not set - falls back to the usual block-kind path
    public function testBlankNameFallsBackToBlockPath(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $block = new Block();
        $block->setKind('document_download');

        $media = new Media();
        $media->setBlock($block);
        $media->setName('   ');
        $media->setFile($this->createFile('upload.pdf', '%PDF-1.4' . str_repeat("\0", 20)));

        $name = $namer->name($media, $this->createMapping());

        $this->assertMatchesRegularExpression('#^medias/site/block-document_download-[a-z0-9]+-[a-z0-9]+\.pdf$#', $name);
    }

    // SVG uploads are never converted to webp - kept as-is since Imagine/GD cannot rasterize them
    public function testSvgUploadKeepsItsOwnExtensionInsteadOfConvertingToWebp(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setRole(Media::ROLE_LOGO);
        $media->setFile($this->createFile(
            'upload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'
        ));

        $this->assertSame('logo.svg', $namer->name($media, $this->createMapping()));
    }

    // Sanity check that VichMediaNamableInterface implementors other than Media skip the singleton branch entirely and always go through the generic "append uniqid" path
    public function testGenericVichMediaNamableEntitySkipsSingletonHandling(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $entity = new readonly class ($this->createFile('upload.gif', 'GIF89a' . str_repeat("\0", 20))) implements VichMediaNamableInterface {
            public function __construct(private File $file)
            {
            }

            public function getFile(): File
            {
                return $this->file;
            }

            public function getVichMediaPath(): string
            {
                return 'custom/path';
            }
        };

        $name = $namer->name($entity, $this->createMapping());

        $this->assertMatchesRegularExpression('#^custom/path-[a-z0-9]+\.webp$#', $name);
    }
}
