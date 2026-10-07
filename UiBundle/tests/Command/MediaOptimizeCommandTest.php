<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\MediaOptimizeCommand;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichImageResizeListener;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Mapping\PropertyMappingFactoryInterface;

class MediaOptimizeCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-optimize-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/medias');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    public function testExecuteRewritesTheFilesTheListenerOptimizes(): void
    {
        file_put_contents($this->projectDir . '/public/medias/photo.webp', str_repeat('x', 2048));
        $listener = $this->createMock(VichImageResizeListener::class);
        $listener->expects($this->once())->method('optimizeStoredImage')->with($this->isInstanceOf(Media::class), $this->projectDir . '/public/medias/photo.webp', true)->willReturn(true);
        $invalidator = $this->createMock(BlockCacheInvalidator::class);
        $invalidator->expects($this->once())->method('invalidateAll');
        $entityManager = $this->createEntityManager([new Media()->setFilename('medias/photo.webp')]);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester($entityManager, $listener, $invalidator);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('medias/photo.webp', $tester->getDisplay());
        $this->assertStringContainsString('1 image(s) réécrite(s) : 2 Ko -> 2 Ko', $tester->getDisplay());
    }

    // Lists what would be rewritten, and writes, flushes and invalidates nothing
    public function testExecuteInDryRunTouchesNothing(): void
    {
        file_put_contents($this->projectDir . '/public/medias/photo.webp', 'x');
        $listener = $this->createMock(VichImageResizeListener::class);
        $listener->expects($this->once())->method('optimizeStoredImage')->with($this->anything(), $this->anything(), false)->willReturn(true);
        $invalidator = $this->createMock(BlockCacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidateAll');
        $entityManager = $this->createEntityManager([new Media()->setFilename('medias/photo.webp')]);
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->createTester($entityManager, $listener, $invalidator);
        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('1 image(s) à réécrire', $tester->getDisplay());
    }

    // A row without a filename or whose file is gone is never handed to the listener
    public function testExecuteSkipsARowWithoutAFileOnDisk(): void
    {
        $listener = $this->createMock(VichImageResizeListener::class);
        $listener->expects($this->never())->method('optimizeStoredImage');
        $invalidator = $this->createMock(BlockCacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidateAll');

        $entityManager = $this->createEntityManager([new Media(), new Media()->setFilename('medias/gone.webp')]);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester($entityManager, $listener, $invalidator);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('déjà optimisées', $tester->getDisplay());
    }

    // One unreadable file is reported and the run goes on with the next
    public function testExecuteWarnsAboutAFileThatFailsAndGoesOn(): void
    {
        file_put_contents($this->projectDir . '/public/medias/broken.webp', 'x');
        file_put_contents($this->projectDir . '/public/medias/photo.webp', 'x');
        $listener = $this->createStub(VichImageResizeListener::class);
        $listener->method('optimizeStoredImage')->willReturnCallback(static function (object $entity, string $path): bool {
            if (str_ends_with($path, 'broken.webp')) {
                throw new \RuntimeException('Unreadable');
            }

            return true;
        });
        $entityManager = $this->createEntityManager([new Media()->setFilename('medias/broken.webp'), new Media()->setFilename('medias/photo.webp')]);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester($entityManager, $listener, $this->createStub(BlockCacheInvalidator::class));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('medias/broken.webp : Unreadable', $tester->getDisplay());
        $this->assertStringContainsString('1 image(s) réécrite(s)', $tester->getDisplay());
    }

    // An entity manager knowing Media alone, whose repository hands back the given rows
    private function createEntityManager(array $medias): EntityManagerInterface
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass(Media::class));
        $metadata->method('getName')->willReturn(Media::class);
        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([$metadata]);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturn($medias);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        $entityManager->method('getRepository')->willReturn($repository);

        return $entityManager;
    }

    private function createTester(EntityManagerInterface $entityManager, VichImageResizeListener $listener, BlockCacheInvalidator $invalidator): CommandTester
    {
        $mappingFactory = $this->createStub(PropertyMappingFactoryInterface::class);
        $mappingFactory->method('fromObject')->willReturn([new PropertyMapping('file', 'filename')]);

        return new CommandTester(new MediaOptimizeCommand($entityManager, $mappingFactory, $listener, $invalidator, $this->projectDir));
    }
}
