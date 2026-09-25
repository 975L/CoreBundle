<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Repository\TranslationRepository;
use PHPUnit\Framework\TestCase;

class BlockDataExporterTest extends TestCase
{
    public function testExportBlocksReturnsEmptyArrayForNoBlocks(): void
    {
        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportBlocks([], $files);

        $this->assertSame([], $data);
        $this->assertSame([], $files);
    }

    public function testExportBlocksSerializesKindPositionDataAndAnimation(): void
    {
        $block = new Block()->setKind('text')->setPosition(2)->setData(['content' => 'hello'])->setAnimation('fade-in');

        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportBlocks([$block], $files);

        $this->assertSame([[
            'kind' => 'text',
            'position' => 2,
            'data' => ['content' => 'hello'],
            'animation' => 'fade-in',
            'hidden' => false,
            'medias' => [],
            'slots' => [],
        ]], $data);
    }

    // What a block and its nested slot say in the other languages travel with them, the importing side knowing neither by id
    public function testExportBlocksCarriesTheTranslationsOfEachBlock(): void
    {
        $slot = new Block()->setKind('text')->setPosition(0);
        new \ReflectionProperty(Block::class, 'id')->setValue($slot, 8);
        $block = new Block()->setKind('flex_columns')->setPosition(0);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 7);
        $block->addSlot($slot);

        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findByOwner')->willReturnCallback(static fn (string $ownerType, int $ownerId): array => 8 === $ownerId
            ? ['en' => ['content' => '<div>Hello</div>']]
            : []);

        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir(), $repository)->exportBlocks([$block], $files);

        $this->assertArrayNotHasKey('translations', $data[0]);
        $this->assertSame(['en' => ['content' => '<div>Hello</div>']], $data[0]['slots'][0]['translations']);
    }

    // A block set aside travels with the archive as it was left: an export taken mid-redesign restores the page in the very state it was exported from, hidden blocks included
    public function testABlockSetAsideIsExportedAsHidden(): void
    {
        $block = new Block()->setKind('text')->setPosition(0)->setHidden(true);

        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportBlocks([$block], $files);

        $this->assertTrue($data[0]['hidden']);
    }

    public function testExportBlocksRecursesIntoNestedContainerSlotsTwoLevelsDeep(): void
    {
        $innermost = new Block()->setKind('text')->setPosition(0)->setData(['content' => 'deep']);
        $middle = new Block()->setKind('flex_columns')->setPosition(0)->setData([]);
        $middle->addSlot($innermost);
        $outer = new Block()->setKind('flex_columns')->setPosition(0)->setData([]);
        $outer->addSlot($middle);

        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportBlocks([$outer], $files);

        $middleSlots = $data[0]['slots'];
        $this->assertCount(1, $middleSlots);
        $this->assertSame('flex_columns', $middleSlots[0]['kind']);

        $innerSlots = $middleSlots[0]['slots'];
        $this->assertCount(1, $innerSlots);
        $this->assertSame('text', $innerSlots[0]['kind']);
        $this->assertSame(['content' => 'deep'], $innerSlots[0]['data']);
        $this->assertSame([], $innerSlots[0]['slots']);
    }

    public function testExportMediaReturnsNullWhenFilenameIsNull(): void
    {
        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportMedia(new Media(), $files);

        $this->assertNull($data);
        $this->assertSame([], $files);
    }

    public function testExportMediaReturnsNullWhenFileDoesNotExistOnDisk(): void
    {
        $media = new Media()->setFilename('uploads/missing.jpg');

        $files = [];
        $data = new BlockDataExporter(sys_get_temp_dir())->exportMedia($media, $files);

        $this->assertNull($data);
        $this->assertSame([], $files);
    }

    public function testExportMediaRegistersTheFileAndReturnsItsMetadata(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/photo.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-image-bytes');

        $media = new Media()
            ->setFilename($filename)
            ->setRole('illustration')
            ->setName('rapport-annuel')
            ->setAlt('A photo')
            ->setPosition(0);

        $files = [];
        $data = new BlockDataExporter($projectDir)->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertSame('illustration', $data['role']);
        $this->assertSame('rapport-annuel', $data['name']);
        $this->assertSame('A photo', $data['alt']);
        $this->assertSame('photo.jpg', $data['originalFilename']);
        $this->assertNull($data['thumbnail']);
        $this->assertCount(1, $files);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($files)[0]);
        $this->assertSame(array_key_first($files), $data['file']);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testExportMediaRegistersAPdfsCompanionWebpThumbnailWhenItExistsOnDisk(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/rapport.pdf';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-pdf-bytes');
        file_put_contents($projectDir . '/public/uploads/rapport.webp', 'fake-webp-bytes');

        $media = new Media()->setFilename($filename);

        $files = [];
        $data = new BlockDataExporter($projectDir)->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertNotNull($data['thumbnail']);
        $this->assertCount(2, $files);
        $this->assertSame($projectDir . '/public/uploads/rapport.webp', $files[$data['thumbnail']]);

        unlink($projectDir . '/public/' . $filename);
        unlink($projectDir . '/public/uploads/rapport.webp');
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testExportMediaLeavesThumbnailNullWhenThePdfHasNoCompanionWebpYet(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/rapport.pdf';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-pdf-bytes');

        $media = new Media()->setFilename($filename);

        $files = [];
        $data = new BlockDataExporter($projectDir)->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertNull($data['thumbnail']);
        $this->assertCount(1, $files);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    // A media's picture in another language is a file of its own on the disk, carried in the archive beside the translation naming it
    public function testExportMediaCarriesTheFileItShowsInAnotherLanguage(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/medias', 0777, true);
        file_put_contents($projectDir . '/public/medias/hero.webp', 'fake-fr-bytes');
        file_put_contents($projectDir . '/public/medias/hero-en.webp', 'fake-en-bytes');
        $media = new Media()->setFilename('medias/hero.webp');
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 12);
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findByOwner')->willReturn(['en' => ['filename' => 'medias/hero-en.webp']]);

        $files = [];
        $data = new BlockDataExporter($projectDir, $repository)->exportMedia($media, $files);

        $this->assertIsArray($data);
        $this->assertSame($projectDir . '/public/medias/hero-en.webp', $files[$data['translatedFiles']['en']]);

        unlink($projectDir . '/public/medias/hero.webp');
        unlink($projectDir . '/public/medias/hero-en.webp');
        rmdir($projectDir . '/public/medias');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    // A language's file gone from the disk leaves no path behind, the texts of that language still going and a language left with nothing going altogether
    public function testExportMediaDropsTheFileOfALanguageMissingFromTheDisk(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/medias', 0777, true);
        file_put_contents($projectDir . '/public/medias/hero.webp', 'fake-fr-bytes');
        $media = new Media()->setFilename('medias/hero.webp');
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 12);
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findByOwner')->willReturn(['en' => ['label' => 'Shop', 'filename' => 'medias/hero-en.webp'], 'es' => ['filename' => 'medias/hero-es.webp']]);

        $files = [];
        $data = new BlockDataExporter($projectDir, $repository)->exportMedia($media, $files);

        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('translatedFiles', $data);
        $this->assertSame(['en' => ['label' => 'Shop']], $data['translations']);

        unlink($projectDir . '/public/medias/hero.webp');
        rmdir($projectDir . '/public/medias');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }
}
