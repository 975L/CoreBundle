<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Namer;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Namer\UiMediaNamer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $this->expectExceptionMessageIsOrContains('must implement VichMediaNamableInterface');
        $namer->name(new \stdClass(), $this->createMapping());
    }

    public function testThrowsWhenFileDoesNotExistOnDisk(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setFile(new File($this->sandboxDir . '/missing.png', false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('File not found');
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

    // An SVG og-image is rasterized after upload (see VichImageResizeListener), so it is named webp too - unlike a logo, which stays SVG
    public function testOgImageSingletonNamesAnSvgUploadWebp(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media();
        $media->setRole(Media::ROLE_OG_IMAGE);
        $media->setFile($this->createFile(
            'upload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'
        ));

        $this->assertSame('og-image.webp', $namer->name($media, $this->createMapping()));
    }

    // A Page's own og-image carries no role, but it is rasterized just the same (see OgImageType), so its SVG upload is named webp too
    public function testOwnedOgImageNamesAnSvgUploadWebp(): void
    {
        $namer = new UiMediaNamer(new AsciiSlugger());
        $media = new Media()->markAsOgImage();
        $media->setFile($this->createFile(
            'upload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'
        ));

        $this->assertMatchesRegularExpression('#\.webp$#', $namer->name($media, $this->createMapping()));
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
        $entity = new readonly class ($this->createFile('upload.gif', 'GIF89a' . str_repeat("\0", 20))) implements VichMediaNamableInterface, VichImageResizableInterface {
            public function getImageWidth(): int
            {
                return 800;
            }

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

    // An entity the resize pipeline never converts keeps its own extension: a ".webp" name over jpeg bytes is what left multi-megabyte pictures on the books' pages
    public function testAnEntityThatIsNotResizedKeepsItsOwnExtension(): void
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

        $this->assertMatchesRegularExpression('#^custom/path-[a-z0-9]+\.gif$#', $namer->name($entity, $this->createMapping()));
    }

    // Whatever produced it, an extension nobody listed is stored as a harmless name - not kept, and no 500 either
    #[DataProvider('refusedNames')]
    public function testAnExtensionOutsideTheListIsStoredAsBin(string $name): void
    {
        $media = new Media();
        $media->setFile(new UploadedFile($this->createFile('upload', '<?php echo 1;')->getPathname(), $name, test: true));
        $media->setName('notes');

        $this->assertStringEndsWith('.bin', new UiMediaNamer(new AsciiSlugger())->name($media, $this->createMapping()));
    }

    /** @return iterable<array{string}> */
    public static function refusedNames(): iterable
    {
        foreach (['x.php', 'x.PHP', 'x.jpg.php', 'x.php5', 'x.phtml', 'x.phar', 'x.html', 'x.htm', 'x.shtml', 'x.xhtml', 'x.xml', 'x.js', 'x.mjs', 'x.gpx', 'x.kml', '.htaccess', 'x.ini', 'x.sh', 'noextension', 'x.'] as $name) {
            yield $name => [$name];
        }
    }

    // The list holds for a file that is no upload as well: an import reads its name off an archive somebody else wrote
    public function testAFileNamedByAnImportGoesThroughTheListToo(): void
    {
        $media = new Media();
        $media->setFile($this->createFile('shell.php', '<?php echo 1;'));
        $media->setName('notes');

        $this->assertStringEndsWith('.bin', new UiMediaNamer(new AsciiSlugger())->name($media, $this->createMapping()));
    }

    // Every kind of file a site legitimately stores keeps its extension, in lower case whatever the client sent
    #[DataProvider('keptNames')]
    public function testALegitimateExtensionIsKept(string $name, string $expected): void
    {
        $media = new Media();
        $media->setFile(new UploadedFile($this->createFile('upload', 'plain text')->getPathname(), $name, test: true));
        $media->setName('notes');

        $this->assertStringEndsWith('.' . $expected, new UiMediaNamer(new AsciiSlugger())->name($media, $this->createMapping()));
    }

    /** @return iterable<array{string, string}> */
    public static function keptNames(): iterable
    {
        yield 'pdf' => ['a.pdf', 'pdf'];
        yield 'capitals' => ['A.PDF', 'pdf'];
        yield 'svg' => ['a.svg', 'svg'];
        yield 'audio' => ['a.mp3', 'mp3'];
        yield 'video' => ['a.mp4', 'mp4'];
        yield 'font' => ['a.woff2', 'woff2'];
        yield 'subtitles' => ['a.vtt', 'vtt'];
        yield 'book' => ['a.epub', 'epub'];
    }

    // Named by nobody, or by a name off the list: the bytes decide, what the validator promises for a phone's picker and what keeps a re-import readable
    #[DataProvider('namedByTheirBytes')]
    public function testAFileOffTheListIsNamedFromItsBytes(string $name): void
    {
        $media = new Media();
        $media->setFile(new UploadedFile($this->createFile('upload', "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n")->getPathname(), $name, test: true));
        $media->setName('notes');

        $this->assertStringEndsWith('.pdf', new UiMediaNamer(new AsciiSlugger())->name($media, $this->createMapping()));
    }

    /** @return iterable<array{string}> */
    public static function namedByTheirBytes(): iterable
    {
        yield 'no extension' => ['scan'];
        yield 'off the list' => ['scan.abc'];
    }

    // A file sold from private/, whatever its format: neither a web server nor a browser reads it there, and the customer must not receive a ".bin"
    #[DataProvider('privateNames')]
    public function testAPrivateClassOfFilesKeepsItsOwnExtension(string $name, string $expected): void
    {
        $sold = new class implements VichMediaNamableInterface, VichPrivateFileInterface {
            public ?File $file = null;

            public function getVichMediaPath(): string
            {
                return 'medias/shop/items/x';
            }

            public function getFile(): ?File
            {
                return $this->file;
            }

            public function getPrivateDirectory(): string
            {
                return 'private';
            }
        };
        $sold->file = new UploadedFile($this->createFile('upload', 'content')->getPathname(), $name, test: true);

        $this->assertStringEndsWith('.' . $expected, new UiMediaNamer(new AsciiSlugger())->name($sold, $this->createMapping()));
    }

    /** @return iterable<array{string, string}> */
    public static function privateNames(): iterable
    {
        yield 'comic book' => ['x.cbz', 'cbz'];
        yield 'kindle' => ['x.AZW3', 'azw3'];
        // Nothing a file name could make of the path it is joined to
        yield 'not an extension' => ['x.a b', 'txt'];
    }

    // Members-only is a checkbox: unticked, that Media moves to public/, so the list holds for it as for any other
    public function testAMembersOnlyMediaStaysOnTheList(): void
    {
        $media = new Media();
        $media->setFile(new UploadedFile($this->createFile('upload', '<?php echo 1;')->getPathname(), 'x.php', test: true));
        $media->setName('notes');
        $media->setMembersOnly(true);

        $this->assertStringEndsWith('.bin', new UiMediaNamer(new AsciiSlugger())->name($media, $this->createMapping()));
    }

    // A poster downloaded by VideoPosterImporter is a temporary file with no name at all: its bytes name it, here a png stored under its own extension by an entity Vich does not convert
    public function testAFileWithNoNameIsNamedFromItsBytes(): void
    {
        $font = new class implements VichMediaNamableInterface {
            public function getVichMediaPath(): string
            {
                return 'medias/poster';
            }

            public function getFile(): ?File
            {
                return $this->file;
            }

            public ?File $file = null;
        };
        $font->file = $this->createFile('ui_poster_abc', $this->pngContent());

        $this->assertStringEndsWith('.png', new UiMediaNamer(new AsciiSlugger())->name($font, $this->createMapping()));
    }
}
