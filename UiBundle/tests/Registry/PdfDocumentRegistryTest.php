<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\PdfDocumentSourceInterface;
use c975L\UiBundle\Registry\PdfDocumentRegistry;
use PHPUnit\Framework\TestCase;

class PdfDocumentRegistryTest extends TestCase
{
    /**
     * @param list<array{filename: string, label: string, editUrl: ?string}> $documents
     */
    private function createSource(array $documents): PdfDocumentSourceInterface
    {
        $source = $this->createStub(PdfDocumentSourceInterface::class);
        $source->method('getPdfDocuments')->willReturn($documents);

        return $source;
    }

    // Nothing declared: PdfThumbnailHealthCheckProvider then reads its own medias and nothing more
    public function testAnEmptyRegistryDeclaresNothing(): void
    {
        $this->assertSame([], new PdfDocumentRegistry()->getDocuments());
    }

    public function testEveryDeclaredDocumentIsHandedOverAsItStands(): void
    {
        $registry = new PdfDocumentRegistry();
        $registry->addProvider($this->createSource([
            ['filename' => 'medias/book/presse.pdf', 'label' => 'Dossier de presse', 'editUrl' => '/management/book/1'],
        ]));

        $this->assertSame(
            [['filename' => 'medias/book/presse.pdf', 'label' => 'Dossier de presse', 'editUrl' => '/management/book/1']],
            $registry->getDocuments()
        );
    }

    // Each source answers for its own table, and the check reports the whole site rather than whichever bundle was registered last
    public function testDocumentsOfEverySourceAreGatheredInRegistrationOrder(): void
    {
        $registry = new PdfDocumentRegistry();
        $registry->addProvider($this->createSource([['filename' => 'a.pdf', 'label' => 'A', 'editUrl' => null]]));
        $registry->addProvider($this->createSource([['filename' => 'b.pdf', 'label' => 'B', 'editUrl' => null]]));

        $this->assertSame(['a.pdf', 'b.pdf'], array_column($registry->getDocuments(), 'filename'));
    }

    // A row naming no file would have the check look at the thumbnail of an empty path, and report a warning nobody can act on
    public function testARowWithoutAFilenameIsSkipped(): void
    {
        $registry = new PdfDocumentRegistry();
        $registry->addProvider($this->createSource([
            ['filename' => '', 'label' => 'Never uploaded', 'editUrl' => null],
            ['filename' => 'presse.pdf', 'label' => 'Dossier de presse', 'editUrl' => null],
        ]));

        $this->assertSame(['presse.pdf'], array_column($registry->getDocuments(), 'filename'));
    }
}
