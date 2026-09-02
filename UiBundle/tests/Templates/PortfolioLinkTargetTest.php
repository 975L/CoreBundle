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
use Twig\TwigFunction;

// A project card either leaves the site or stays in it, and only the first opens a tab of its own - locked for both templates drawing that same card, the "collection" block's portfolio variant and the PortfolioGrid component it borrows its classes from
class PortfolioLinkTargetTest extends TestCase
{
    // The case this exists for: an item pointing at a page of this very site (its own detail Page, say) must stay in the visitor's tab
    public function testAnItemLinkedInsideTheSiteKeepsTheTab(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha', 'url' => '/pages/sites-realises/projet-alpha']);

        $this->assertStringContainsString('href="/pages/sites-realises/projet-alpha"', $html);
        $this->assertStringNotContainsString('_blank', $html);
    }

    // An item pointing outside still behaves as it always has
    public function testAnItemLinkedOutsideTheSiteOpensItsOwnTab(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha', 'url' => 'https://projet-alpha.example']);

        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
    }

    // A detail url is this site's own by construction, whatever the item's "url" happens to be
    public function testTheDetailUrlKeepsTheTabEvenOverAnOutsideUrl(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha', 'detailUrl' => '/pages/sites-realises/projet-alpha', 'url' => 'https://projet-alpha.example']);

        $this->assertStringContainsString('href="/pages/sites-realises/projet-alpha"', $html);
        $this->assertStringNotContainsString('_blank', $html);
    }

    // Nothing to link to: a href="#" would be a dead link that still looks clickable
    public function testAnItemWithNothingToLinkToIsNotALinkAtAll(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha']);

        $this->assertStringContainsString('<div class="portfolio-grid__project">', $html);
    }

    // The component draws the very same card from its own medias, so it reads the link the same way
    public function testTheComponentReadsTheLinkTheSameWay(): void
    {
        $html = $this->renderGrid([
            $this->media('/pages/sites-realises/projet-alpha', 'Projet Alpha'),
            $this->media('https://editions-exemple.example', 'Editions Exemple'),
        ]);

        $this->assertStringContainsString('href="/pages/sites-realises/projet-alpha">', $html);
        $this->assertStringContainsString('href="https://editions-exemple.example" target="_blank" rel="noopener"', $html);
    }

    // The container's own tag is not the projects' - a <section> closed by whatever the last card rendered would be invalid markup
    public function testTheComponentClosesItsOwnContainer(): void
    {
        $html = $this->renderGrid([$this->media('https://editions-exemple.example', 'Editions Exemple')], ['title' => 'Réalisations']);

        $this->assertStringContainsString('<section class="portfolio-grid">', $html);
        $this->assertStringEndsWith("</section>\n", $html);
    }

    private function media(string $url, string $label): object
    {
        return (object) ['url' => $url, 'label' => $label, 'description' => null, 'intrinsicWidth' => null, 'intrinsicHeight' => null];
    }

    private function renderItem(array $context): string
    {
        return $this->twig()->render('blocks/CollectionItem.html.twig', [...$context, 'variant' => 'portfolio']);
    }

    private function renderGrid(array $media, array $context = []): string
    {
        return $this->twig()->render('components/Portfolio/Grid.html.twig', [...$context, 'media' => $media]);
    }

    // A bare Environment writes the "<twig:...>" calls out as text and knows nothing of VichUploader, which this component calls for each media's own file
    private function twig(): Environment
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (): string => '/uploads/project.jpg'));

        return $twig;
    }
}
