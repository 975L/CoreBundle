<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Storage;

use Metadata\AdvancedMetadataFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Mapping\PropertyMappingResolverInterface;
use Vich\UploaderBundle\Metadata\MetadataReader;

class NestedFileSystemStorageTest extends TestCase
{
    private string $uploadDestination;
    private string $sourceDir;

    protected function setUp(): void
    {
        $this->uploadDestination = sys_get_temp_dir() . '/nested-storage-test-dest-' . uniqid();
        $this->sourceDir = sys_get_temp_dir() . '/nested-storage-test-src-' . uniqid();
        mkdir($this->uploadDestination, 0777, true);
        mkdir($this->sourceDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->uploadDestination);
        $this->removeDirectory($this->sourceDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    // Building a real PropertyMappingFactory is cheap (its dependencies are never actually invoked by doUpload/doRemove/doResolvePath) and avoids doubling final Vich classes
    private function createStorage(): NestedFileSystemStorageTestSubject
    {
        $metadataReader = new MetadataReader($this->createStub(AdvancedMetadataFactoryInterface::class));
        $factory = new PropertyMappingFactory($metadataReader, $this->createStub(PropertyMappingResolverInterface::class));

        return new NestedFileSystemStorageTestSubject($factory);
    }

    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->uploadDestination, 'uri_prefix' => '']);

        return $mapping;
    }

    // A name containing no "/" is a flat filename: uploaded directly into $dir (or the upload destination root)
    public function testDoUploadPlacesFlatNameDirectlyInDirectory(): void
    {
        $sourcePath = $this->sourceDir . '/source.txt';
        file_put_contents($sourcePath, 'content');

        $storage = $this->createStorage();
        $result = $storage->publicDoUpload($this->createMapping(), new File($sourcePath), null, 'flat.txt');

        $this->assertFileExists($this->uploadDestination . '/flat.txt');
        $this->assertSame('content', file_get_contents($this->uploadDestination . '/flat.txt'));
        // A trailing "/" is harmlessly duplicated when $dir is null and the name is flat (nestedDir resolves to null), since it's concatenated as an empty path segment - still resolves to the same real file
        $this->assertSame(basename($this->uploadDestination . '/flat.txt'), basename($result->getPathname()));
    }

    // A name containing a nested subdirectory (as returned by UiMediaNamer, e.g. "medias/site/block-x") must be uploaded into that nested subdirectory, which is created on demand
    public function testDoUploadCreatesNestedSubdirectoryFromName(): void
    {
        $sourcePath = $this->sourceDir . '/source.txt';
        file_put_contents($sourcePath, 'nested-content');

        $storage = $this->createStorage();
        $storage->publicDoUpload($this->createMapping(), new File($sourcePath), null, 'medias/site/block-1-abc.txt');

        $this->assertFileExists($this->uploadDestination . '/medias/site/block-1-abc.txt');
        $this->assertSame('nested-content', file_get_contents($this->uploadDestination . '/medias/site/block-1-abc.txt'));
    }

    // Windows-style backslashes in the name must be normalized to "/" before splitting subdir from basename
    public function testDoUploadNormalizesBackslashesInName(): void
    {
        $sourcePath = $this->sourceDir . '/source.txt';
        file_put_contents($sourcePath, 'content');

        $storage = $this->createStorage();
        $storage->publicDoUpload($this->createMapping(), new File($sourcePath), null, 'medias\\site\\file.txt');

        $this->assertFileExists($this->uploadDestination . '/medias/site/file.txt');
    }

    // A non-null $dir (from the directory namer) is combined with the name's own subdirectory
    public function testDoUploadCombinesExplicitDirWithNameSubdirectory(): void
    {
        $sourcePath = $this->sourceDir . '/source.txt';
        file_put_contents($sourcePath, 'content');

        $storage = $this->createStorage();
        $storage->publicDoUpload($this->createMapping(), new File($sourcePath), 'base', 'sub/file.txt');

        $this->assertFileExists($this->uploadDestination . '/base/sub/file.txt');
    }

    // A plain (non-uploaded) File is copied rather than moved, leaving the source file untouched
    public function testDoUploadCopiesPlainFileInsteadOfMoving(): void
    {
        $sourcePath = $this->sourceDir . '/source.txt';
        file_put_contents($sourcePath, 'copied-content');

        $storage = $this->createStorage();
        $storage->publicDoUpload($this->createMapping(), new File($sourcePath), null, 'copy.txt');

        $this->assertFileExists($sourcePath, 'Source file must remain since File::move() was not used');
        $this->assertFileExists($this->uploadDestination . '/copy.txt');
    }

    public function testDoRemoveDeletesTheNestedFile(): void
    {
        $nestedPath = $this->uploadDestination . '/medias/site';
        mkdir($nestedPath, 0777, true);
        file_put_contents($nestedPath . '/file.txt', 'content');

        $storage = $this->createStorage();
        $result = $storage->publicDoRemove($this->createMapping(), null, 'medias/site/file.txt');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($nestedPath . '/file.txt');
    }

    public function testDoRemoveThrowsWhenFileDoesNotExist(): void
    {
        $storage = $this->createStorage();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot remove file');
        $storage->publicDoRemove($this->createMapping(), null, 'missing.txt');
    }

    public function testDoResolvePathReturnsAbsolutePathByDefault(): void
    {
        $storage = $this->createStorage();

        $this->assertSame(
            $this->uploadDestination . \DIRECTORY_SEPARATOR . 'sub' . \DIRECTORY_SEPARATOR . 'file.txt',
            $storage->publicDoResolvePath($this->createMapping(), 'sub', 'file.txt')
        );
    }

    public function testDoResolvePathReturnsRelativePathWhenRequested(): void
    {
        $storage = $this->createStorage();

        $this->assertSame(
            'sub' . \DIRECTORY_SEPARATOR . 'file.txt',
            $storage->publicDoResolvePath($this->createMapping(), 'sub', 'file.txt', true)
        );
    }

    public function testDoResolvePathWithoutDirReturnsBareName(): void
    {
        $storage = $this->createStorage();

        $this->assertSame(
            $this->uploadDestination . \DIRECTORY_SEPARATOR . 'file.txt',
            $storage->publicDoResolvePath($this->createMapping(), null, 'file.txt')
        );
    }
}
