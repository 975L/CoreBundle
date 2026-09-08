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

// The one variant of this adapter whose markup is written out rather than drawn by the Card component: the name sits between the picture and the text, an order the component cannot give since its title is a sibling of the whole body
class CollectionItemPortraitVariantTest extends TestCase
{
    // What the variant exists for - the picture first, the name under it - read on the rendered order and not on the presence of each part
    public function testThePictureComesBeforeTheNameAndTheNameBeforeTheText(): void
    {
        $html = $this->render(['title' => 'Mamie ViteVite', 'content' => 'Toujours pressée', 'imageUrl' => '/uploads/photo.webp', 'variant' => 'portrait']);

        $picture = strpos($html, '/uploads/photo.webp');
        $name = strpos($html, 'Mamie ViteVite');
        $text = strpos($html, 'Toujours pressée');

        $this->assertIsInt($picture);
        $this->assertGreaterThan($picture, $name, 'The name no longer comes under the portrait, which is the whole of this variant.');
        $this->assertGreaterThan($name, $text, 'The text no longer comes under the name.');
        $this->assertStringNotContainsString('card-header', $html, 'The portrait card is headed with a band again, where its name is meant to be read under the face.');
    }

    // The card's own classes all the same: the row, its measure and the shadow are every other card's, only the band is not
    public function testItIsACardCarryingItsOwnModifier(): void
    {
        $this->assertStringContainsString('class="card card--portrait box-shadow "', $this->render(['title' => 'Item Un', 'variant' => 'portrait']));
        $this->assertStringContainsString('class="card card--portrait box-shadow variant-custom"', $this->render(['title' => 'Item Un', 'class' => 'variant-custom', 'variant' => 'portrait']));
        $this->assertStringContainsString('class="card card--portrait box-shadow card--accent-red shadow"', $this->render(['title' => 'Item Un', 'class' => ['card--accent-red', 'shadow'], 'variant' => 'portrait']));
    }

    // The detail url first, the source's own url behind it - the very order the default variant reads them in, and it links the portrait as well as the name so the picture is not a dead zone above a link
    public function testBothThePortraitAndTheNameGoToTheDetailPageWhenThereIsOne(): void
    {
        $html = $this->render(['title' => 'Item Un', 'imageUrl' => '/uploads/photo.webp', 'detailUrl' => '/pages/collection/item', 'url' => 'https://example.org', 'variant' => 'portrait']);

        $this->assertStringContainsString('url="/pages/collection/item"', $html);
        $this->assertStringContainsString('<a href="/pages/collection/item">Item Un</a>', $html);
    }

    // The tab is a setting of the item's own "url": the picture and the name follow it exactly as the button below them does
    public function testThePortraitAndTheNameOpenTheTabTheItemAsksFor(): void
    {
        $html = $this->render(['title' => 'Item Un', 'imageUrl' => '/uploads/photo.webp', 'url' => 'https://example.org', 'target' => '_blank', 'variant' => 'portrait']);

        $this->assertStringContainsString('url="https://example.org" target="_blank"', $html);
        $this->assertStringContainsString('<a href="https://example.org" target="_blank">Item Un</a>', $html);
    }

    // A detail page is a page of this very site, so nothing opens a tab for it - the reason the portfolio variant states, and the rule the default variant's own picture follows
    public function testADetailPageNeverOpensATabOfItsOwn(): void
    {
        $html = $this->render(['title' => 'Item Un', 'imageUrl' => '/uploads/photo.webp', 'detailUrl' => '/pages/collection/item', 'url' => 'https://example.org', 'target' => '_blank', 'variant' => 'portrait']);

        $this->assertStringContainsString('url="/pages/collection/item" target=""', $html);
        $this->assertStringContainsString('<a href="/pages/collection/item">Item Un</a>', $html);
    }

    // The picture's box is reserved from the attached media, so a row of portraits does not recompose as the files arrive
    public function testThePictureCarriesTheAttachedMediasOwnMeasure(): void
    {
        $media = (object) ['intrinsicWidth' => 800, 'intrinsicHeight' => 1000];

        $html = $this->render(['title' => 'Item Un', 'imageUrl' => '/uploads/photo.webp', 'block' => (object) ['media' => [$media]], 'variant' => 'portrait']);

        $this->assertStringContainsString('width="800" height="1000"', $html);
    }

    // The heading the "collection" block resolved for its items, matched against the offered levels and never interpolated: what the form holds is data, and data must not be able to write a tag name
    public function testTheHeadingFollowsTheLevelTheBlockResolvedAndFallsBackOnH3(): void
    {
        $this->assertStringContainsString('<h2 class="card-title">', $this->render(['title' => 'Item Un', 'level' => 'h2', 'variant' => 'portrait']));
        $this->assertStringContainsString('<h3 class="card-title">', $this->render(['title' => 'Item Un', 'variant' => 'portrait']));
        $this->assertStringContainsString('<h3 class="card-title">', $this->render(['title' => 'Item Un', 'level' => 'script', 'variant' => 'portrait']));
    }

    // The text stays in plain flow where a name is read right under its face; only the button takes the ".card-data" every other variant pins to the bottom of the body
    public function testOnlyTheButtonIsPinnedToTheBottomOfTheCard(): void
    {
        $this->assertStringNotContainsString('card-data', $this->render(['title' => 'Item Un', 'content' => 'Une ligne', 'variant' => 'portrait']));
        $this->assertStringContainsString('card-data', $this->render(['title' => 'Item Un', 'url' => 'https://example.org', 'variant' => 'portrait']));
    }

    // A bare Environment writes the "<twig:...>" calls out as text, which is what these assertions read
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));

        return $twig->render('blocks/CollectionItem.html.twig', $context);
    }
}
