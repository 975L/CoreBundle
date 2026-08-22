<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Management\LinkableRouteProvider;
use PHPUnit\Framework\TestCase;

// The wishlist page offered to SiteBundle menus - its route name is checked against the controllers by ManagementTargetsTest, this one guards what the entry says
class LinkableRouteProviderTest extends TestCase
{
    // Only the page a visitor navigates to: the toggle and the list answer a click, and a menu item pointing at either would be a dead link
    public function testItOffersTheWishlistPageAlone(): void
    {
        $routes = new LinkableRouteProvider()->getLinkableRoutes();

        $this->assertSame(['ui_favorite_page'], array_keys($routes));
        $this->assertSame('label.favorites', $routes['ui_favorite_page']['label']);
    }

    // The label is a key of this bundle's own catalog: a site whose navbar carries the entry would otherwise show a raw key
    public function testEveryLabelIsTranslatedInThisBundleInEveryLocale(): void
    {
        foreach (new LinkableRouteProvider()->getLinkableRoutes() as $entry) {
            $this->assertSame('ui', $entry['translation_domain']);
        }

        foreach (['en', 'fr', 'es'] as $locale) {
            $xliff = simplexml_load_file(__DIR__ . '/../../translations/ui.' . $locale . '.xlf');
            $sources = [];
            foreach ($xliff->file->body->{'trans-unit'} as $unit) {
                $sources[(string) $unit->source] = (string) $unit->target;
            }

            foreach (new LinkableRouteProvider()->getLinkableRoutes() as $entry) {
                $this->assertArrayHasKey($entry['label'], $sources, sprintf('"%s" has no %s translation', $entry['label'], $locale));
                $this->assertNotSame('', $sources[$entry['label']]);
            }
        }
    }
}
