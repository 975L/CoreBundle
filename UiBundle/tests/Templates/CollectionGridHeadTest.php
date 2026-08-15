<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// The block's form offers an eyebrow and a "see all" link whatever the presentation picked, so both variants have to print them - the default one used to drop them silently, leaving the editor filling in fields nothing ever rendered
class CollectionGridHeadTest extends TestCase
{
    private const array ITEMS = ['<article class="card">Une fiche</article>'];

    public function testTheDefaultVariantPrintsTheEyebrowTheTitleAndTheLink(): void
    {
        $html = $this->render([
            'items' => self::ITEMS,
            'eyebrow' => 'Surtitre',
            'title' => 'Le titre',
            'linkLabel' => 'Voir tout',
            'linkUrl' => '/personnages',
        ]);

        $this->assertStringContainsString('<div class="collection-grid__head">', $html);
        $this->assertStringContainsString('<p class="section-eyebrow">Surtitre</p>', $html);
        $this->assertStringContainsString('<h2 class="section-title">Le titre</h2>', $html);
        $this->assertStringContainsString('<a class="section-btn section-btn--ghost" href="/personnages">Voir tout</a>', $html);
    }

    // Shared with every other kind taking the same head: with no title the eyebrow is the section's heading
    public function testAnEyebrowAloneBecomesTheSectionHeading(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'eyebrow' => 'Surtitre']);

        $this->assertStringContainsString('<section class="collection-grid"', $html);
        $this->assertStringContainsString('<h2 class="section-eyebrow">Surtitre</h2>', $html);
    }

    // The portfolio variant keeps borrowing PortfolioGrid's own head markup, so it matches a real portfolio_grid sitting on the same page
    public function testThePortfolioVariantKeepsThePortfolioHead(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'title' => 'Le titre', 'variant' => 'portfolio']);

        $this->assertStringContainsString('<div class="portfolio-grid__head">', $html);
        $this->assertStringContainsString('class="collection-grid collection-grid--portfolio"', $html);
    }

    // A headingless <section> is invalid HTML, and a grid standing on its items alone is the ordinary case
    public function testAGridWithNoHeadRendersAsADivAndPrintsNoHead(): void
    {
        $html = $this->render(['items' => self::ITEMS]);

        $this->assertStringContainsString('<div class="collection-grid"', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringNotContainsString('__head', $html);
    }

    // A link is no heading: it gets its head, but the grid stays a <div>
    public function testALinkAloneIsPrintedWithoutTurningTheGridIntoASection(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'linkLabel' => 'Voir tout', 'linkUrl' => '/personnages']);

        $this->assertStringContainsString('<div class="collection-grid"', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringContainsString('<div class="collection-grid__head">', $html);
        $this->assertStringContainsString('href="/personnages">Voir tout</a>', $html);
    }

    // Half a link is no link - a label with nowhere to go, or a url with nothing to click
    public function testAHalfConfiguredLinkIsNotPrinted(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'title' => 'Le titre', 'linkLabel' => 'Voir tout']);

        $this->assertStringNotContainsString('section-btn', $html);
    }

    private function render(array $context): string
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'c975LUi');

        return new Environment($loader)->render('components/Collection/Grid.html.twig', $context);
    }
}
