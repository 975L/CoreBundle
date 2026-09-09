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
use Twig\TwigFilter;
use Twig\TwigFunction;

// The heading each item's title is drawn with: resolved from the block's own head unless the editor picked one, then carried down to whichever markup draws it - the same field and the same rule on the "collection" and "portfolio_grid" blocks
class ItemTitleLevelTest extends TestCase
{
    // The case this exists for: a collection standing on its items alone sits straight under the page's <h1>, where an <h3> skips a level
    public function testAGridWithNoHeadDrawsItsItemsAsH2(): void
    {
        $this->assertSame('h2', $this->resolvedLevel(['source' => 'site.collection.projects']));
    }

    // A head of its own is the <h2> the items then hang under, an eyebrow standing as that heading exactly as the grid reads it
    public function testAGridCarryingAHeadDrawsItsItemsAsH3(): void
    {
        $this->assertSame('h3', $this->resolvedLevel(['source' => 'site.collection.projects', 'title' => 'Réalisations']));
        $this->assertSame('h3', $this->resolvedLevel(['source' => 'site.collection.projects', 'eyebrow' => 'Nos travaux']));
    }

    // What the field is there for: a collection nested under a section that already carries its own <h2>
    public function testTheEditorsOwnLevelWinsOverTheDeducedOne(): void
    {
        $this->assertSame('h4', $this->resolvedLevel(['source' => 'site.collection.projects', 'title' => 'Réalisations', 'level' => 'h4']));
    }

    // Matched against the offered levels, never interpolated: block data must not be able to write a tag name
    public function testALevelOutsideTheOfferedOnesFallsBackToTheDeducedOne(): void
    {
        $this->assertSame('h2', $this->resolvedLevel(['source' => 'site.collection.projects', 'level' => 'script']));
    }

    // The portfolio variant used to hard-code its <h3>, which is where the reported level skip came from
    public function testThePortfolioItemDrawsItsTitleAtTheGivenLevel(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha', 'variant' => 'portfolio', 'level' => 'h2']);

        $this->assertStringContainsString('<h2 class="portfolio-grid__project-title">Projet Alpha</h2>', $html);
    }

    // Nothing said - an item rendered outside a "collection" block - keeps the <h3> this markup has always drawn
    public function testThePortfolioItemKeepsItsH3WhenNoLevelIsGiven(): void
    {
        $html = $this->renderItem(['title' => 'Projet Alpha', 'variant' => 'portfolio']);

        $this->assertStringContainsString('<h3 class="portfolio-grid__project-title">Projet Alpha</h3>', $html);
    }

    // The card variants read it through the component's own "level" prop, whichever of the three branches the item falls in
    public function testTheCardItemsHandTheLevelToTheCardComponent(): void
    {
        $this->assertStringContainsString('level="h2"', $this->renderItem(['title' => 'Item Un', 'content' => 'Text', 'level' => 'h2']));
        $this->assertStringContainsString('level="h2"', $this->renderItem(['title' => 'Item Un', 'imageUrl' => '/uploads/photo.jpg', 'url' => '/item', 'level' => 'h2']));
        $this->assertStringContainsString('level="h2"', $this->renderItem(['title' => 'Item Un', 'eyebrow' => 'Seigneur', 'level' => 'h2']));
    }

    // The portfolio_grid component draws its own projects, so it resolves the level itself - and by the same reading, a static caller included
    public function testThePortfolioGridDrawsItsProjectsAtTheDeducedLevel(): void
    {
        $this->assertStringContainsString('<h2 class="portfolio-grid__project-title">Projet Alpha</h2>', $this->renderGrid([]));
        $this->assertStringContainsString('<h3 class="portfolio-grid__project-title">Projet Alpha</h3>', $this->renderGrid(['title' => 'Réalisations']));
        $this->assertStringContainsString('<h4 class="portfolio-grid__project-title">Projet Alpha</h4>', $this->renderGrid(['title' => 'Réalisations', 'level' => 'h4']));
        $this->assertStringContainsString('<h3 class="portfolio-grid__project-title">Projet Alpha</h3>', $this->renderGrid(['eyebrow' => 'Nos travaux', 'level' => 'script']));
    }

    // A head holding nothing is margins standing over the projects, the same rule the collection grid already follows
    public function testThePortfolioGridPrintsNoHeadWhenThereIsNothingToPutInIt(): void
    {
        $this->assertStringNotContainsString('portfolio-grid__head', $this->renderGrid([]));
        $this->assertStringContainsString('portfolio-grid__head', $this->renderGrid(['linkLabel' => 'Voir tout', 'linkUrl' => '/realisations']));
    }

    // The frameless variants only take the chrome off: one modifier on the section, the grid and its titles staying what they are
    public function testThePortfolioGridCarriesItsVariantModifierOnlyWhenAsked(): void
    {
        $this->assertStringContainsString('class="portfolio-grid portfolio-grid--plain"', $this->renderGrid(['variant' => 'plain']));
        $this->assertStringContainsString('class="portfolio-grid portfolio-grid--thumbnail"', $this->renderGrid(['variant' => 'thumbnail']));
        $this->assertStringContainsString('class="portfolio-grid"', $this->renderGrid([]));
    }

    // Matched against the offered variants and never interpolated, the same rule as the level above: block data must not be able to write a class name
    public function testAVariantOutsideTheOfferedOnesWritesNoModifier(): void
    {
        $this->assertStringContainsString('class="portfolio-grid"', $this->renderGrid(['variant' => 'evil onclick=x']));
    }

    // A media carrying neither label nor description gets no body at all: an empty heading is padding under the picture, and a screen reader announces it as a heading all the same
    public function testAProjectWithNothingToSayPrintsNoBody(): void
    {
        $this->assertStringNotContainsString('portfolio-grid__project-body', $this->renderGrid([], label: '', description: null));
        $this->assertStringContainsString('portfolio-grid__project-body', $this->renderGrid([], label: '', description: 'Le texte du projet.'));
    }

    // The level the block hands collection_render_items(), the function itself being stubbed to record what it was given
    private function resolvedLevel(array $context): string
    {
        $received = '';
        $twig = $this->twig();
        $twig->addFunction(new TwigFunction('collection_render_items', static function (string $source, ?int $limit, ?string $detailPage, ?string $variant, ?string $order, ?string $level) use (&$received): array {
            $received = (string) $level;

            return [];
        }));
        $this->render($twig, 'blocks/Collection.html.twig', $context);

        return $received;
    }

    // The component knows nothing of VichUploader, which it calls for each project's own file
    private function renderGrid(array $context, string $label = 'Projet Alpha', ?string $description = null): string
    {
        $twig = $this->twig();
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (): string => '/uploads/project.jpg'));
        $media = (object) ['url' => null, 'label' => $label, 'description' => $description, 'intrinsicWidth' => null, 'intrinsicHeight' => null];

        return $this->render($twig, 'components/Portfolio/Grid.html.twig', [...$context, 'media' => [$media]]);
    }

    private function renderItem(array $context): string
    {
        return $this->render($this->twig(), 'blocks/CollectionItem.html.twig', $context);
    }

    // A bare Environment stringifies the arrays the component syntax hands over as they are (the items, a card's "stats"), same as CollectionItemStatVariantTest: that one warning is dropped here rather than worked around in the templates
    private function render(Environment $twig, string $template, array $context): string
    {
        set_error_handler(static fn (int $severity, string $message): bool => str_contains($message, 'Array to string conversion'), \E_WARNING);

        try {
            return $twig->render($template, $context);
        } finally {
            restore_error_handler();
        }
    }

    // A bare Environment writes the "<twig:...>" calls out as text, which is what these assertions read
    private function twig(): Environment
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 2) . '/templates');
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LUi');
        $twig = new Environment($loader);

        // The bundle's own filter, which a bare Environment knows nothing of - the same rule BoolExtension applies, so "false" spelled out stays false here too
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => !\in_array($value, [false, 'false', '0', 0, ''], true)));

        return $twig;
    }
}
