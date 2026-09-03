<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Contract\VichMediaNamableInterface;
use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Listener\MediaFileRemoveListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Mapping\PropertyMappingResolver;
use Vich\UploaderBundle\Metadata\Driver\AttributeDriver;
use Vich\UploaderBundle\Metadata\Driver\AttributeReader;
use Vich\UploaderBundle\Metadata\MetadataReader;

class MediaFileRemoveListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-file-remove-listener-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
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

    private function createListener(): MediaFileRemoveListener
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->projectDir);

        // Real Vich metadata stack (attribute reader/driver/factory), reading the fixtures' actual #[Vich\UploadableField] attributes below, exactly as the real app container does - this is what lets the test catch a getFileName()/getName() mismatch instead of hiding it behind a stub
        $metadataReader = new MetadataReader(
            new MetadataFactory(new AttributeDriver(new AttributeReader(), []), \Metadata\ClassHierarchyMetadata::class, false)
        );
        $resolver = new PropertyMappingResolver([], [], ['test' => []]);

        return new MediaFileRemoveListener($kernel, new PropertyMappingFactory($metadataReader, $resolver));
    }

    private function createEventArgs(object $entity): PreRemoveEventArgs
    {
        return new PreRemoveEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    private function createUpdateEventArgs(object $entity): PreUpdateEventArgs
    {
        $changeSet = [];

        return new PreUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class), $changeSet);
    }

    private function createPostFlushEventArgs(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    public function testPreRemoveDeletesTheUnderlyingFileWhenFileNamePropertyIsName(): void
    {
        mkdir($this->projectDir . '/public/medias/site', 0777, true);
        file_put_contents($this->projectDir . '/public/medias/site/logo.webp', 'content');

        // As ShopBundle's/CrowdfundingBundle's real media entities are, via VichMediaTrait
        $entity = new MediaFileRemoveListenerTestNameFixture('medias/site/logo.webp');

        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileDoesNotExist($this->projectDir . '/public/medias/site/logo.webp');
    }

    public function testPreRemoveDeletesTheUnderlyingFileWhenFileNamePropertyIsFilename(): void
    {
        mkdir($this->projectDir . '/public/medias/site', 0777, true);
        file_put_contents($this->projectDir . '/public/medias/site/cover.webp', 'content');

        // As UiBundle's own Media/GalleryBundle's GalleryPhoto are - getName(), when it exists at all on these, is unrelated to the Vich-managed filename
        $entity = new MediaFileRemoveListenerTestFilenameFixture('medias/site/cover.webp');

        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileDoesNotExist($this->projectDir . '/public/medias/site/cover.webp');
    }

    public function testPreRemoveIgnoresEntitiesNotImplementingVichMediaNamableInterface(): void
    {
        mkdir($this->projectDir . '/public/medias', 0777, true);
        file_put_contents($this->projectDir . '/public/medias/untouched.webp', 'content');

        $entity = new \stdClass();

        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileExists($this->projectDir . '/public/medias/untouched.webp');
    }

    public function testPreRemoveDoesNothingWhenNameIsNull(): void
    {
        $entity = new MediaFileRemoveListenerTestNameFixture(null);

        // Would throw if it tried to build a path from a null name
        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->addToAssertionCount(1);
    }

    public function testPreRemoveDoesNothingWhenFileDoesNotExist(): void
    {
        $entity = new MediaFileRemoveListenerTestNameFixture('medias/site/missing.webp');

        // Would throw on unlink() if it didn't check file_exists() first
        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->addToAssertionCount(1);
    }

    // A private file (e.g. a paid download) was moved out of public/ by VichImageResizeListener::moveFileToPrivate() - it must be looked up under its own getPrivateDirectory(), not public/
    public function testPreRemoveDeletesTheFileFromItsPrivateDirectoryWhenEntityIsPrivate(): void
    {
        mkdir($this->projectDir . '/private/downloads', 0777, true);
        file_put_contents($this->projectDir . '/private/downloads/invoice-abc123.pdf', 'content');

        $entity = new MediaFileRemoveListenerTestPrivateFixture('invoice-abc123.pdf');

        $listener = $this->createListener();
        $listener->preRemove($this->createEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileDoesNotExist($this->projectDir . '/private/downloads/invoice-abc123.pdf');
    }

    // The whole point of deferring to postFlush: a flush that throws leaves the file where the row still points at it
    public function testNothingIsDeletedUntilTheFlushHasGoneThrough(): void
    {
        mkdir($this->projectDir . '/private/downloads', 0777, true);
        file_put_contents($this->projectDir . '/private/downloads/invoice-abc123.pdf', 'content');

        $entity = new MediaFileRemoveListenerTestPrivateFixture('invoice-abc123.pdf');

        $this->createListener()->preRemove($this->createEventArgs($entity));

        $this->assertFileExists($this->projectDir . '/private/downloads/invoice-abc123.pdf');
    }

    // Vich's own delete_on_update looks for the replaced file under the mapping's public destination, where a private one is not - it stayed on disk, paid downloads piling up, until this listener took the update path too
    public function testPreUpdateDeletesTheReplacedPrivateFile(): void
    {
        mkdir($this->projectDir . '/private/downloads', 0777, true);
        file_put_contents($this->projectDir . '/private/downloads/invoice-abc123.pdf', 'content');
        file_put_contents($this->projectDir . '/new-upload.pdf', 'content');

        // The name still reads as the file being replaced, Vich's own upload listener not having run yet at this priority
        $entity = new MediaFileRemoveListenerTestPrivateFixture('invoice-abc123.pdf');
        $entity->setFile(new File($this->projectDir . '/new-upload.pdf'));

        $listener = $this->createListener();
        $listener->preUpdate($this->createUpdateEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileDoesNotExist($this->projectDir . '/private/downloads/invoice-abc123.pdf');
    }

    // Editing a title, a position or an alternative text carries no upload, and the file the entity already holds has to survive it
    public function testPreUpdateKeepsTheFileWhenNoNewOneIsBeingUploaded(): void
    {
        mkdir($this->projectDir . '/private/downloads', 0777, true);
        file_put_contents($this->projectDir . '/private/downloads/invoice-abc123.pdf', 'content');

        $entity = new MediaFileRemoveListenerTestPrivateFixture('invoice-abc123.pdf');

        $listener = $this->createListener();
        $listener->preUpdate($this->createUpdateEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileExists($this->projectDir . '/private/downloads/invoice-abc123.pdf');
    }

    // A stored file still sitting where the mapping says is Vich's own business (delete_on_update), and removing it here would race its own clean listener
    public function testPreUpdateLeavesAPublicFileToVich(): void
    {
        mkdir($this->projectDir . '/public/medias/site', 0777, true);
        file_put_contents($this->projectDir . '/public/medias/site/logo.webp', 'content');

        $entity = new MediaFileRemoveListenerTestNameFixture('medias/site/logo.webp');

        $listener = $this->createListener();
        $listener->preUpdate($this->createUpdateEventArgs($entity));
        $listener->postFlush($this->createPostFlushEventArgs());

        $this->assertFileExists($this->projectDir . '/public/medias/site/logo.webp');
    }
}

// fileNameProperty: 'name' - as ShopBundle's/CrowdfundingBundle's real media entities are, via VichMediaTrait
#[Vich\Uploadable]
class MediaFileRemoveListenerTestNameFixture implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'test', fileNameProperty: 'name')]
    protected ?File $file = null;

    public function __construct(private readonly ?string $name)
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/site/fixture';
    }
}

// fileNameProperty: 'filename' - as UiBundle's own Media/GalleryBundle's GalleryPhoto are
#[Vich\Uploadable]
class MediaFileRemoveListenerTestFilenameFixture implements VichMediaNamableInterface
{
    #[Vich\UploadableField(mapping: 'test', fileNameProperty: 'filename')]
    protected ?File $file = null;

    public function __construct(private readonly ?string $filename)
    {
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/site/fixture';
    }
}

// Same shape as MediaFileRemoveListenerTestNameFixture, additionally implementing VichPrivateFileInterface - as a satellite bundle's paid-download media entity would
#[Vich\Uploadable]
class MediaFileRemoveListenerTestPrivateFixture implements VichMediaNamableInterface, VichPrivateFileInterface
{
    #[Vich\UploadableField(mapping: 'test', fileNameProperty: 'name')]
    protected ?File $file = null;

    public function __construct(private readonly ?string $name)
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    // The pending upload the update path reads through the Vich mapping, as the real entities expose it (see VichMediaTrait)
    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getVichMediaPath(): string
    {
        return 'medias/site/fixture';
    }

    public function getPrivateDirectory(): string
    {
        return 'private/downloads';
    }
}
