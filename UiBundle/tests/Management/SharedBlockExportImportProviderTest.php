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
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Management\BlockDataImporter;
use c975L\UiBundle\Management\SharedBlockExportProvider;
use c975L\UiBundle\Management\SharedBlockImportProvider;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use c975L\UiBundle\Repository\SharedBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// A page export carries only its pointers' slugs: the shared blocks travel on their own, matched back by that very slug
class SharedBlockExportImportProviderTest extends TestCase
{
    public function testBothSidesShareOneKind(): void
    {
        $export = new SharedBlockExportProvider($this->createStub(SharedBlockRepository::class), new BlockDataExporter(sys_get_temp_dir()));

        $this->assertSame(SharedBlockImportProvider::KIND, $export->getKind());
        $this->assertTrue($this->createImport()->supportsImport('site_shared_block'));
        $this->assertFalse($this->createImport()->supportsImport('site_menu'));
    }

    // The slug travels with the name, being what the pointers of the exported pages name
    public function testTheExportCarriesNameSlugAndBlocks(): void
    {
        $shared = new SharedBlock()->setName('Bandeau contact')->setSlug('bandeau-contact')
            ->addBlock(new Block()->setKind('cta_band')->setPosition(0)->setData(['title' => 'Un projet ?']));

        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findAll')->willReturn([$shared]);

        $data = new SharedBlockExportProvider($repository, new BlockDataExporter(sys_get_temp_dir()))->exportAll();

        $this->assertSame('Bandeau contact', $data['items'][0]['name']);
        $this->assertSame('bandeau-contact', $data['items'][0]['slug']);
        $this->assertSame('cta_band', $data['items'][0]['blocks'][0]['kind']);
        $this->assertSame(['title' => 'Un projet ?'], $data['items'][0]['blocks'][0]['data']);
    }

    // A new slug creates the shared block under that very slug, whatever its name would build
    public function testTheImportCreatesASharedBlockUnderItsExportedSlug(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $result = $this->createImport($em)->import([[
            'name' => 'Bandeau contact',
            'slug' => 'bandeau-contact-2',
            'blocks' => [['kind' => 'cta_band', 'position' => 0, 'data' => ['title' => 'Un projet ?']]],
        ]]);

        $shared = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof SharedBlock))[0];
        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame('bandeau-contact-2', $shared->getSlug());
        $this->assertSame('Bandeau contact', $shared->getName());
        $this->assertSame('cta_band', $shared->getBlocks()->first()->getKind());
    }

    // Blocks have no natural key, so an existing shared block gets its whole run replaced
    public function testTheImportReplacesTheRunOfAnExistingSharedBlock(): void
    {
        $existing = new SharedBlock()->setName('Ancien nom')->setSlug('bandeau-contact')
            ->addBlock(new Block()->setKind('text_hook')->setPosition(0)->setData(['text' => 'Ancien']));

        $result = $this->createImport(null, $existing)->import([[
            'name' => 'Bandeau contact',
            'slug' => 'bandeau-contact',
            'blocks' => [['kind' => 'cta_band', 'position' => 0, 'data' => ['title' => 'Nouveau']]],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('Bandeau contact', $existing->getName());
        $this->assertCount(1, $existing->getBlocks());
        $this->assertSame('cta_band', $existing->getBlocks()->first()->getKind());
    }

    // An item with no slug would create a shared block no pointer can ever name
    public function testAnItemWithoutSlugIsSkipped(): void
    {
        $this->assertSame(['created' => 0, 'updated' => 0], $this->createImport()->import([['name' => 'Sans slug', 'blocks' => []]]));
    }

    private function createImport(?EntityManagerInterface $em = null, ?SharedBlock $existing = null): SharedBlockImportProvider
    {
        $em ??= $this->createStub(EntityManagerInterface::class);
        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findOneBySlug')->willReturn($existing);

        return new SharedBlockImportProvider($em, $repository, new BlockDataImporter($em, $this->createStub(FormBlockDependencyRegistry::class), $this->createStub(ValidatorInterface::class)));
    }
}
