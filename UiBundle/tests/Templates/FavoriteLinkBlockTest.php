<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

// The menu item leading to this bundle's wishlist page - offered to whoever builds menus out of these blocks rather than declared over there, the page and the count being this bundle's own
class FavoriteLinkBlockTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // "menu_navbar" is exclusive (see BlockRegistry::EXCLUSIVE_CONTEXTS): a kind that did not opt into it is never offered there, and a navbar is where this item is meant to sit
    public function testItIsOfferedInEveryMenuLocationALinkIs(): void
    {
        $tag = $this->blockTag();

        $this->assertSame('menu,menu_navbar,menu_slot', $tag['contexts']);
        $this->assertTrue($tag['pickable']);
    }

    // The item paints itself active on the wishlist page, which no cache entry shared between visitors can hold
    public function testItIsNotCacheable(): void
    {
        $this->assertFalse($this->blockTag()['cacheable']);
    }

    // A navbar is part of a page served cached and shared, so the count is painted from the visitor's own browser rather than printed here
    public function testTheMarkupCarriesNoCountOfItsOwn(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('data-controller="ui-favorite-count"', $template);
        $this->assertStringContainsString('data-ui-favorite-count-target="count"', $template);
        $this->assertStringContainsString('hidden></span>', $template);
    }

    // The heart announcing a click sits on a card far from the menu and its event bubbles to the document, so the item listens on the window rather than on itself
    public function testItListensToTheHeartsOwnEventOnTheWindow(): void
    {
        $this->assertStringContainsString(
            'data-action="ui-favorite:changed@window->ui-favorite-count#update"',
            $this->template()
        );
    }

    // The item shape a menu publishes, the very classes its own links carry: written here so the block sits in the row like every other item, and painted by that bundle's own stylesheet
    public function testItWearsTheItemShapeAMenuPaints(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('class="menu-link"', $template);
        $this->assertStringContainsString('<span class="menu-label">', $template);
    }

    // Inside that label, which is what carries the color: a mobile dropdown and a desktop bar paint it differently, and a "currentColor" fill follows both without restating either
    #[DataProvider('stylesheetProvider')]
    public function testTheHeartFollowsTheLabelsOwnColor(string $file): void
    {
        // Spaces stripped rather than asserted twice: the minified build writes the very same declaration without the one after the colon
        $css = str_replace(' ', '', $this->stylesheet($file));

        $this->assertStringContainsString('.favorite-menu-icon', $css);
        $this->assertStringContainsString('fill:currentColor', $css);
    }

    private function blockTag(): array
    {
        $services = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/services.yaml')['services'];

        return $services['ui.block.favorite_link']['tags'][0];
    }

    private function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/blocks/FavoriteLink.html.twig');
    }

    private function stylesheet(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/public/css/' . $file);
    }
}
