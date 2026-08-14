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
use c975L\ConfigBundle\Management\UrlMetadataImportProvider;
use c975L\ConfigBundle\Repository\UrlMetadataRepository;
use c975L\UiBundle\Management\BlockDataImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UrlMetadataImportProviderTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    private function createProvider(?UrlMetadata $existing = null): UrlMetadataImportProvider
    {
        $this->persisted = [];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $repository = $this->createStub(UrlMetadataRepository::class);
        $repository->method('findOneByPath')->willReturn($existing);

        return new UrlMetadataImportProvider($this->createStub(BlockDataImporter::class), $em, $repository);
    }

    public function testSupportsImportOnlyMatchesSiteUrlMetadataKind(): void
    {
        $provider = $this->createProvider();

        $this->assertTrue($provider->supportsImport('site_url_metadata'));
        $this->assertFalse($provider->supportsImport('site_redirect'));
        $this->assertFalse($provider->supportsImport('site_page'));
    }

    public function testImportCreatesARow(): void
    {
        $provider = $this->createProvider();

        $result = $provider->import([[
            'path' => '/animaux',
            'title' => 'Animaux',
            'summarySocialNetwork' => 'Les douze compagnons des castes de Thryndor.',
            'ogImage' => null,
        ]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame('/animaux', $this->persisted[0]->getPath());
        $this->assertSame('Animaux', $this->persisted[0]->getTitle());
        $this->assertSame('Les douze compagnons des castes de Thryndor.', $this->persisted[0]->getSummarySocialNetwork());
    }

    // What a sync is for: the sentence written on one environment replaces the one on the other, the row being matched on its path
    public function testImportOverwritesAnExistingRow(): void
    {
        $existing = new UrlMetadata()->setPath('/animaux')->setTitle('Ancien')->setSummarySocialNetwork('Ancienne phrase.');

        $result = $this->createProvider($existing)->import([[
            'path' => '/animaux',
            'title' => 'Animaux',
            'summarySocialNetwork' => 'Les douze compagnons des castes de Thryndor.',
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Animaux', $existing->getTitle());
        $this->assertSame('Les douze compagnons des castes de Thryndor.', $existing->getSummarySocialNetwork());
    }

    // A row is nothing without its path: it could neither be matched on the way in nor looked up at render time, and the unique constraint would reject it
    public function testARowWithoutAPathIsSkipped(): void
    {
        $result = $this->createProvider()->import([
            ['path' => '', 'title' => 'Sans chemin'],
            ['title' => 'Pas de clé du tout'],
            ['path' => '/animaux', 'title' => 'Animaux'],
        ]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertCount(1, $this->persisted);
    }

    // An export written by hand, or one predating a column, imports as the row it describes rather than taking the whole sync down
    public function testATitleAndASummaryAreOptional(): void
    {
        $result = $this->createProvider()->import([['path' => '/animaux']]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertNull($this->persisted[0]->getTitle());
        $this->assertNull($this->persisted[0]->getSummarySocialNetwork());
    }

    // The path is matched in the form it is stored in, so a payload carrying the trailing slash still finds its row instead of creating a second one the unique constraint would refuse
    public function testAPathIsNormalisedBeforeBeingMatched(): void
    {
        $existing = new UrlMetadata()->setPath('/animaux');

        $result = $this->createProvider($existing)->import([['path' => 'animaux/', 'title' => 'Animaux']]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('/animaux', $existing->getPath());
    }
}
