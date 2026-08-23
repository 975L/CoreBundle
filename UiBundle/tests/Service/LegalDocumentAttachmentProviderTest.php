<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\LegalDocument;
use c975L\UiBundle\Service\LegalDocumentAttachmentProvider;
use c975L\UiBundle\Service\LegalModelCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

// The terms a customer accepted, handed to them as a file: a link points at a page that can be rewritten afterwards, which is not the "durable medium" the sale is owed
class LegalDocumentAttachmentProviderTest extends TestCase
{
    public function testEveryLegalModelTheSitePublishesCanBeAttached(): void
    {
        $kinds = $this->provider()->getAttachmentKinds();

        $this->assertArrayHasKey('legal:france/terms-of-sales', $kinds);
        $this->assertCount(\count(new LegalModelCatalog()->all()), $kinds);
    }

    public function testTheDocumentIsDrawnFromTheVersionTheSitePublishes(): void
    {
        $legalDocument = $this->createMock(LegalDocument::class);
        $legalDocument->expects($this->once())
            ->method('pdf')
            ->with('france/terms-of-sales', 'fr')
            ->willReturn('%PDF-1.7 the terms');

        $attachment = $this->provider($legalDocument)->createAttachment('legal:france/terms-of-sales', ['locale' => 'fr']);

        $this->assertSame('%PDF-1.7 the terms', $attachment?->content);
        $this->assertSame('application/pdf', $attachment?->contentType);
    }

    // The recipient reads the filename, so it is written in their language and not in the identifier's
    public function testTheFileIsNamedInTheRecipientsLanguage(): void
    {
        $attachment = $this->provider()->createAttachment('legal:france/terms-of-sales', ['locale' => 'fr']);

        $this->assertSame('conditions-generales-de-vente.pdf', $attachment?->filename);
    }

    // A kind another bundle owns, handed over by the registry only because this one was asked first
    public function testAKindThatIsNotALegalModelIsDeclined(): void
    {
        $this->assertNull($this->provider()->createAttachment('invoice', []));
        $this->assertNull($this->provider()->createAttachment('legal:france/made-up', []));
    }

    // A caller saying nothing about the language gets the site's own, never a broken lookup
    public function testAnEmailWithoutALanguageIsDrawnInTheSites(): void
    {
        $legalDocument = $this->createMock(LegalDocument::class);
        $legalDocument->expects($this->once())->method('pdf')->with('france/copyright', 'es')->willReturn('pdf');

        $this->provider($legalDocument, 'es')->createAttachment('legal:france/copyright', []);
    }

    private function provider(?LegalDocument $legalDocument = null, string $defaultLocale = 'fr'): LegalDocumentAttachmentProvider
    {
        // Answers the wording a French catalogue holds for the one key the filename is built from, and the key itself for the rest
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => 'label.terms_of_sales' === $id ? 'Conditions générales de vente' : $id
        );

        return new LegalDocumentAttachmentProvider(
            new LegalModelCatalog(),
            $legalDocument ?? $this->createStub(LegalDocument::class),
            $translator,
            new AsciiSlugger(),
            $defaultLocale,
        );
    }
}
