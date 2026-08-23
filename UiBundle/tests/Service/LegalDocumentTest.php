<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\PdfGeneratorInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\LegalDocument;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use c975L\UiBundle\Service\LegalModelRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

// One document, one truth: what a customer accepted is the site's own version, and everything printing, serving or attaching it reads the same thing
class LegalDocumentTest extends TestCase
{
    // The block is what a client rewrote, dated and hid clauses in - it wins over the model as the bundle ships it
    public function testTheSitesOwnVersionWinsOverTheModelAsShipped(): void
    {
        $block = new Block()->setKind('legal_model')->setData([
            'model' => 'france/terms-of-sales',
            'latestUpdate' => '2026-03-01',
            'customization' => ['delivery' => ['hidden' => true]],
        ]);

        $renderer = $this->createMock(LegalModelRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with('france/terms-of-sales', '2026-03-01', ['delivery' => ['hidden' => true]], 'fr')
            ->willReturn('<p>personnalisé</p>');

        $document = $this->document($renderer, [$block]);

        $this->assertSame('<p>personnalisé</p>', $document->html('france/terms-of-sales', 'fr'));
    }

    // A shop installed without page management holds no block, and gets the model as shipped - with its %config% markers resolved
    public function testASiteHoldingNoBlockGetsTheModelAsShipped(): void
    {
        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturn('<p>modèle</p>');

        $this->assertSame('<p>modèle %substituted%</p>', $this->document($renderer, [])->html('france/terms-of-sales', 'fr'));
    }

    // A block carrying another model says nothing about this one
    public function testABlockOfAnotherModelIsNotRead(): void
    {
        $block = new Block()->setKind('legal_model')->setData(['model' => 'france/privacy-policy']);

        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturn('<p>modèle</p>');

        $this->assertSame('<p>modèle %substituted%</p>', $this->document($renderer, [$block])->html('france/terms-of-sales', 'fr'));
    }

    // The key moves whenever a character of the document does, whatever moved it - a clause, a date, or a company name resolved from the configuration
    public function testTheFingerprintFollowsTheTextAndNothingElse(): void
    {
        $first = $this->documentRendering('<p>un</p>')->fingerprint('france/terms-of-sales', 'fr');
        $second = $this->documentRendering('<p>deux</p>')->fingerprint('france/terms-of-sales', 'fr');
        $again = $this->documentRendering('<p>un</p>')->fingerprint('france/terms-of-sales', 'fr');

        $this->assertNotSame($first, $second);
        $this->assertSame($first, $again);
    }

    // The file is named after what it holds, so a document that moved is never served from the one before it
    public function testTheCachedFileIsNamedAfterTheDocumentItHolds(): void
    {
        $document = $this->documentRendering('<p>un</p>');
        $file = $document->cacheFile('france/terms-of-sales', 'fr');

        $this->assertStringEndsWith(sprintf('france-terms-of-sales-fr-%s.pdf', $document->fingerprint('france/terms-of-sales', 'fr')), $file);
        $this->assertStringContainsString('/var/pdf/', $file);
    }

    private function documentRendering(string $html): LegalDocument
    {
        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturn($html);

        return $this->document($renderer, []);
    }

    /** @param list<Block> $blocks */
    private function document(LegalModelRenderer $renderer, array $blocks): LegalDocument
    {
        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('findByKind')->willReturn($blocks);

        $placeholders = $this->createStub(LegalModelPlaceholders::class);
        $placeholders->method('substitute')->willReturnCallback(static fn (string $html): string => str_replace('</p>', ' %substituted%</p>', $html));

        return new LegalDocument(
            $renderer,
            $placeholders,
            $blockRepository,
            $this->createStub(PdfGeneratorInterface::class),
            $this->createStub(Environment::class),
            '/srv/site',
        );
    }
}
