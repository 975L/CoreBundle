<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// The visitor's own list lives in their browser and nowhere else, the page carrying a heart being cached and shared. What that costs is checked across the files no browser runs here, so each end is held to what the others assume
class FavoriteButtonControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/favorite.js';
    private const string PAGE_JS = 'assets/js/favorites.js';
    private const string STORE_JS = 'assets/js/favorite-store.js';
    private const string COUNT_JS = 'assets/js/favorite-count.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Favorite/Button.html.twig';
    private const string PAGE = 'templates/favorite/page.html.twig';

    // Public pages only, and lazily: the barrel imports each for a document that actually holds one
    public function testBothControllersAreRegisteredAsLazyFrontControllersNamedAfterWhatTheTemplatesWrite(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringContainsString("'ui-favorite': () => import('./js/favorite.js'),", $barrel);
        $this->assertStringContainsString("'ui-favorites': () => import('./js/favorites.js'),", $barrel);
        $this->assertStringContainsString('data-controller="ui-favorite"', $this->read(self::COMPONENT));
        $this->assertStringContainsString('data-controller="ui-favorites"', $this->read(self::PAGE));
    }

    // The identifiers are registered in kebab-case on purpose: Stimulus derives every "data-ui-favorite-*-value" name from them, and a camelCase one would silently bind none of them
    public function testTheIdentifiersAreTheOnesEveryValueAttributeIsDerivedFrom(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringNotContainsString('uiFavorite:', $barrel);
        $this->assertStringNotContainsString('uiFavorites:', $barrel);
    }

    // Nothing is written to the browser before the visitor puts something aside: that is what keeps the heart out of consent territory, no identifier being created for someone who merely reads the page
    public function testConnectOnlyReadsTheBrowserStorage(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $connect = substr($controller, strpos($controller, 'connect()'), strpos($controller, 'async toggle(') - strpos($controller, 'connect()'));

        $this->assertStringContainsString('read().keys', $connect);
        $this->assertStringNotContainsString('write(', $connect);
        $this->assertStringNotContainsString('newToken()', $connect);
    }

    // One click at a time: a second while the first is in flight would send a toggle taking back what the first put aside
    public function testASecondClickIsIgnoredWhileOneIsInFlight(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('if (this.sending) {', $controller);
        $this->assertStringContainsString('} finally {', $controller);
        $this->assertStringContainsString('this.sending = false;', $controller);
    }

    // The server decides and the browser records what it decided: an account already holding the thing answers "removed" where an empty heart would have assumed "added"
    public function testTheStateIsPaintedFromTheAnswerAndNotFromTheClick(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.paint(answer.favorited);', $controller);
        $this->assertStringContainsString('aria-pressed', $controller);
    }

    // The page carrying the heart is shared between visitors, so nothing about this one's list may be printed in its html
    public function testTheMarkupCarriesNothingOfTheVisitorsOwnList(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('aria-pressed="false"', $component);
        $this->assertStringNotContainsString('favorite-button--on', $component);
    }

    // Its own store, not the rating one: a visitor clearing one of the two features must not lose the other
    public function testTheStoreIsTheFavoritesOwn(): void
    {
        $this->assertStringContainsString('"c975l-favorite"', $this->read(self::STORE_JS));
    }

    // A browser refusing storage (private mode, site data blocked) reads as an empty list rather than taking the page down with it
    public function testABrowserRefusingStorageIsReadAsAnEmptyList(): void
    {
        $store = $this->read(self::STORE_JS);

        $this->assertStringContainsString('return { token: null, keys: {} };', $store);
        $this->assertSame(2, substr_count($store, '} catch {'));
    }

    // What the server holds is written over what the browser assumed: one visit to the list and every heart of the site paints right, which is how a list follows an account onto a device that never saw it
    public function testThePageWritesTheServersKeysOverWhatTheBrowserAssumed(): void
    {
        $this->assertStringContainsString('sync(answer.keys);', $this->read(self::PAGE_JS));
    }

    // Whoever shows how many things are put aside hears it from an event rather than being handed a reference to the controller
    public function testTheCountIsAnnouncedRatherThanRead(): void
    {
        $this->assertStringContainsString('this.dispatch("changed"', $this->read(self::CONTROLLER_JS));
    }

    // The wishlist page announces it too: a browser whose storage was behind would keep showing what it had assumed, on the very page that just corrected it
    public function testThePageAnnouncesItUnderTheSamePrefix(): void
    {
        $this->assertStringContainsString('prefix: "ui-favorite"', $this->read(self::PAGE_JS));
    }

    // The counter is a third front controller, registered lazily like the two others and named after what SiteBundle's "favorite_link" block writes
    public function testTheCounterIsRegisteredAsALazyFrontController(): void
    {
        $this->assertStringContainsString("'ui-favorite-count': () => import('./js/favorite-count.js'),", $this->read(self::BARREL));
        $this->assertStringNotContainsString('uiFavoriteCount:', $this->read(self::BARREL));
    }

    // A navbar is part of a page served cached and shared, so the count is read from this browser's own store and never fetched
    public function testTheCounterNeverAsksTheServer(): void
    {
        $counter = $this->read(self::COUNT_JS);

        $this->assertStringContainsString('Object.keys(read().keys).length', $counter);
        $this->assertStringNotContainsString('fetch(', $counter);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
