<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\BlockLocationProviderInterface;
use c975L\UiBundle\Contract\PdfGeneratorInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockLocationRegistry;
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
        $this->assertStringStartsWith('/srv/site/var/cache/prod/pdf/', $file);
    }

    private function documentRendering(string $html): LegalDocument
    {
        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturn($html);

        return $this->document($renderer, []);
    }

    // A hidden block is set aside on its page: the text a customer reads is the next one
    public function testAHiddenBlockIsNotTheDocument(): void
    {
        $hidden = $this->block(1, '2026-01-01')->setHidden(true);
        $shown = $this->block(2, '2026-02-01');

        $this->assertSame('2026-02-01', $this->documentDating([$hidden, $shown])->html('france/terms-of-sales', 'fr'));
    }

    // The terms duplicated then the old copy put in the trash: the confirmations attach the page still published, not the oldest block
    public function testTheBlockOfAPublishedPageWinsOverAnOlderOneInTheTrash(): void
    {
        $trashed = $this->block(1, '2026-01-01');
        $published = $this->block(2, '2026-02-01');
        $locations = [1 => ['label' => 'Old', 'url' => null, 'published' => false], 2 => ['label' => 'New', 'url' => '/cgv', 'published' => true]];

        $this->assertSame('2026-02-01', $this->documentDating([$trashed, $published], $locations)->html('france/terms-of-sales', 'fr'));
    }

    // Every copy unpublished: the site publishes none of them, the model as shipped is what stands
    public function testNoPublishedBlockFallsBackOnTheModelAsShipped(): void
    {
        $locations = [1 => ['label' => 'Old', 'url' => null, 'published' => false]];

        $this->assertSame('<p>shipped %substituted%</p>', $this->documentDating([$this->block(1, '2026-01-01')], $locations)->html('france/terms-of-sales', 'fr'));
    }

    // A legal model nested in a container is located through the block its page holds, so a published page's copy wins over a draft's
    public function testANestedBlockIsLocatedThroughItsContainer(): void
    {
        $draft = $this->block(1, '2026-01-01');
        $container = new Block()->setKind('container');
        new \ReflectionProperty(Block::class, 'id')->setValue($container, 3);
        $nested = $this->block(2, '2026-02-01')->setParentBlock($container);
        $locations = [1 => ['label' => 'Draft', 'url' => null, 'published' => false], 3 => ['label' => 'Terms', 'url' => '/cgv', 'published' => true]];

        $this->assertSame('2026-02-01', $this->documentDating([$draft, $nested], $locations)->html('france/terms-of-sales', 'fr'));
    }

    // Written aside then renamed: nothing but the finished PDF is ever left under its name
    public function testThePdfIsWrittenWholeWithNoTemporaryFileLeft(): void
    {
        $cacheDir = sys_get_temp_dir() . '/legal-document-' . bin2hex(random_bytes(4));
        $pdfGenerator = $this->createStub(PdfGeneratorInterface::class);
        $pdfGenerator->method('renderHtml')->willReturn('%PDF-1.7 whole');
        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturn('<p>un</p>');
        $document = $this->document($renderer, [], pdfGenerator: $pdfGenerator, cacheDir: $cacheDir);

        $this->assertSame('%PDF-1.7 whole', $document->pdf('france/terms-of-sales', 'fr'));
        $files = glob($cacheDir . '/pdf/*') ?: [];
        $this->assertSame([$document->cacheFile('france/terms-of-sales', 'fr')], $files);
        $this->assertSame('%PDF-1.7 whole', file_get_contents($files[0]));

        array_map(unlink(...), $files);
        rmdir($cacheDir . '/pdf');
        rmdir($cacheDir);
    }

    // A terms-of-sales block with an id, dated so the test reads which one was rendered
    private function block(int $id, string $latestUpdate): Block
    {
        $block = new Block()->setKind('legal_model')->setData(['model' => 'france/terms-of-sales', 'latestUpdate' => $latestUpdate]);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    // Renders the date of the block it is given, and "shipped" for the model without one
    private function documentDating(array $blocks, array $locations = []): LegalDocument
    {
        $renderer = $this->createStub(LegalModelRenderer::class);
        $renderer->method('render')->willReturnCallback(static fn (string $model, ?string $latestUpdate): string => $latestUpdate ?? '<p>shipped</p>');

        return $this->document($renderer, $blocks, $locations);
    }

    /**
     * @param list<Block>                                                     $blocks
     * @param array<int, array{label: string, url: ?string, published: bool}> $locations
     */
    private function document(LegalModelRenderer $renderer, array $blocks, array $locations = [], ?PdfGeneratorInterface $pdfGenerator = null, string $cacheDir = '/srv/site/var/cache/prod'): LegalDocument
    {
        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('findByKind')->willReturn($blocks);

        $placeholders = $this->createStub(LegalModelPlaceholders::class);
        $placeholders->method('substitute')->willReturnCallback(static fn (string $html): string => str_replace('</p>', ' %substituted%</p>', $html));

        $locationProvider = $this->createStub(BlockLocationProviderInterface::class);
        $locationProvider->method('getLocations')->willReturn($locations);
        $blockLocationRegistry = new BlockLocationRegistry();
        $blockLocationRegistry->addProvider($locationProvider);

        return new LegalDocument(
            $renderer,
            $placeholders,
            $blockRepository,
            $pdfGenerator ?? $this->createStub(PdfGeneratorInterface::class),
            $this->createStub(Environment::class),
            $blockLocationRegistry,
            $cacheDir,
        );
    }
}
