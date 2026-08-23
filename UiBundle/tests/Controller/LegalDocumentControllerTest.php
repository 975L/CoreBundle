<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\LegalDocumentController;
use c975L\UiBundle\Service\LegalDocument;
use c975L\UiBundle\Service\LegalModelCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Any legal document the site publishes, as a file - the terms of sales a shop owes its customers on a durable medium, the privacy policy and the legal notice the same way
class LegalDocumentControllerTest extends TestCase
{
    public function testTheDocumentIsServedAsAPdf(): void
    {
        $response = $this->controller()->pdf('sales/terms', new Request());

        $this->assertSame('%PDF-1.7', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    // Drawn in the page rather than saved: it is a document to read, and the reader saves it if they want to
    public function testTheDocumentIsMeantToBeReadInThePage(): void
    {
        $response = $this->controller()->pdf('sales/terms', new Request());

        $this->assertSame('inline; filename=sales-terms.pdf', $response->headers->get('Content-Disposition'));
    }

    // The identifier is half a template path, and nothing from a url may ever reach one
    public function testAModelTheCatalogDoesNotKnowIsRefused(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(known: false)->pdf('../../../etc/passwd', new Request());
    }

    // The fingerprint is in the tag, so a clause rewritten in the back office invalidates it on its own
    public function testTheDocumentCarriesItsOwnFingerprintAsAnEtag(): void
    {
        $response = $this->controller()->pdf('sales/terms', new Request());

        $this->assertSame('"abc123"', $response->getEtag());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame(3600, $response->getMaxAge());
    }

    // A browser holding the very same version is told so rather than sent the file again
    public function testABrowserHoldingTheSameVersionIsAnsweredWithNothing(): void
    {
        $request = new Request();
        $request->headers->set('If-None-Match', '"abc123"');

        $response = $this->controller()->pdf('sales/terms', $request);

        $this->assertSame(304, $response->getStatusCode());
    }

    // The reader's own language, not the site's: a document travels in the language of whoever asked for it
    public function testTheDocumentIsDrawnInTheLanguageOfTheRequest(): void
    {
        $legalDocument = $this->createMock(LegalDocument::class);
        $legalDocument->expects($this->once())->method('pdf')->with('sales/terms', 'es')->willReturn('%PDF-1.7');
        $legalDocument->method('fingerprint')->willReturn('abc123');

        $request = new Request();
        $request->setLocale('es');

        new LegalDocumentController($legalDocument, $this->catalog(true))->pdf('sales/terms', $request);
    }

    private function controller(bool $known = true): LegalDocumentController
    {
        $legalDocument = $this->createStub(LegalDocument::class);
        $legalDocument->method('pdf')->willReturn('%PDF-1.7');
        $legalDocument->method('fingerprint')->willReturn('abc123');

        return new LegalDocumentController($legalDocument, $this->catalog($known));
    }

    private function catalog(bool $known): LegalModelCatalog
    {
        $catalog = $this->createStub(LegalModelCatalog::class);
        $catalog->method('has')->willReturn($known);

        return $catalog;
    }
}
