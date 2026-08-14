<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Management\UrlMetadataExportProvider;
use c975L\ConfigBundle\Management\UrlMetadataImportProvider;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\BlockDataExporter;
use PHPUnit\Framework\TestCase;

class UrlMetadataExportProviderTest extends TestCase
{
    private function createProvider(UrlMetadataRepository $repository, ?BlockDataExporter $blockDataExporter = null): UrlMetadataExportProvider
    {
        return new UrlMetadataExportProvider($blockDataExporter ?? $this->createStub(BlockDataExporter::class), $repository);
    }

    private function createRepository(array $urlMetadatas): UrlMetadataRepository
    {
        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findAll')->willReturn($urlMetadatas);

        return $repository;
    }

    public function testGetKindMatchesUrlMetadataImportProvider(): void
    {
        $provider = $this->createProvider($this->createStub(UrlMetadataRepository::class));

        $this->assertSame(UrlMetadataImportProvider::KIND, $provider->getKind());
    }

    public function testExportAllSerializesEveryRowFromTheRepository(): void
    {
        $urlMetadata = new UrlMetadata()
            ->setPath('/animaux')
            ->setTitle('Animaux')
            ->setSummarySocialNetwork('Les douze compagnons des castes de Thryndor.');

        $data = $this->createProvider($this->createRepository([$urlMetadata]))->exportAll();

        $this->assertSame([[
            'path' => '/animaux',
            'title' => 'Animaux',
            'summarySocialNetwork' => 'Les douze compagnons des castes de Thryndor.',
            'ogImage' => null,
        ]], $data['items']);
        $this->assertSame([], $data['files']);
    }

    // A row filled in as the site is written carries the nulls it still has, which the import reads back as-is
    public function testExportAllKeepsAnEmptyTitleAndSummaryAsNull(): void
    {
        $data = $this->createProvider($this->createRepository([new UrlMetadata()->setPath('/animaux')]))->exportAll();

        $this->assertNull($data['items'][0]['title']);
        $this->assertNull($data['items'][0]['summarySocialNetwork']);
    }

    // The share image travels with the export: a sync dropping it would leave prod sharing the site's default picture where the site chose another
    public function testExportAllCarriesTheShareImageAndItsFile(): void
    {
        $ogImage = new Media()->setName('animaux.webp');
        $urlMetadata = new UrlMetadata()->setPath('/animaux')->setOgImage($ogImage);

        $blockDataExporter = $this->createMock(BlockDataExporter::class);
        $blockDataExporter->expects($this->once())
            ->method('exportMedia')
            ->with($ogImage, $this->anything())
            ->willReturnCallback(static function (Media $media, array &$files): array {
                $files['media/animaux.webp'] = '/var/www/media/animaux.webp';

                return ['name' => $media->getName()];
            });

        $data = $this->createProvider($this->createRepository([$urlMetadata]), $blockDataExporter)->exportAll();

        $this->assertSame(['name' => 'animaux.webp'], $data['items'][0]['ogImage']);
        $this->assertSame(['media/animaux.webp' => '/var/www/media/animaux.webp'], $data['files']);
    }

    public function testExportAllOfAnEmptyTableIsAnEmptyExport(): void
    {
        $data = $this->createProvider($this->createRepository([]))->exportAll();

        $this->assertSame([], $data['items']);
        $this->assertSame([], $data['files']);
    }
}
