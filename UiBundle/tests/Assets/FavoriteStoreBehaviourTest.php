<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/favorite-store.js against a real localStorage, including a browser that refuses it
// Everything here is a promise about storage: that a refused one reads as an empty list instead of taking the page down, that no identifier is minted for someone who merely reads, and that the server's answer overwrites what this browser assumed. Storage is the one thing an emulated environment provides in name only
#[Group('browser')]
class FavoriteStoreBehaviourTest extends JsCase
{
    // A browser in private mode, or with site data blocked, throws on the very first read
    public function testABrowserRefusingStorageReadsAsAnEmptyListRatherThanThrowing(): void
    {
        $read = $this->store(
            'return mod.store.read();',
            'Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new DOMException("blocked"); } });'
        );

        $this->assertNull($read['token'], 'A refused storage answers something other than "no list at all".');
        $this->assertSame([], $read['keys'], 'A refused storage answers with keys, so a page would draw a list nobody has.');
    }

    // And writing to it must be just as quiet: the list is the server's, this browser simply will not remember it between two pages
    public function testWritingToARefusedStorageIsQuiet(): void
    {
        $this->assertTrue(
            (bool) $this->store(
                'try { mod.store.write({ token: "t", keys: {} }); return true; } catch { return false; }',
                'Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new DOMException("blocked"); } });'
            ),
            'A refused storage takes the page down when something is written to it.'
        );
    }

    // Content nobody wrote, or wrote in another shape, must not stop the page either
    public function testStorageHoldingSomethingUnreadableIsTreatedAsEmpty(): void
    {
        $read = $this->store('window.localStorage.setItem("c975l-favorite", "not json at all"); return mod.store.read();');

        $this->assertNull($read['token']);
        $this->assertSame([], $read['keys'], 'Unreadable storage is not treated as an empty list.');
    }

    // A token minted on the first click and never before is what keeps this out of consent territory
    public function testATokenIsOnlyEverMintedOnDemandAndIsOpaque(): void
    {
        $minted = $this->store('return { before: mod.store.read().token, made: mod.store.newToken(), other: mod.store.newToken() };');

        $this->assertNull($minted['before'], 'A token exists for a visitor who has done nothing at all, which is an identifier created without asking.');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $minted['made'], 'The token is not the opaque 32-character identifier the server is given.');
        $this->assertNotSame($minted['made'], $minted['other'], 'Two tokens minted in a row are the same, so every visitor of this browser shares one list.');
    }

    // What the server just answered, written over what this browser assumed: someone signing in on a new device holds a list their storage knows nothing about
    public function testSyncingReplacesTheStoredKeysRatherThanAddingToThem(): void
    {
        $synced = $this->store(
            'mod.store.write({ token: "kept", keys: { stale: true, gone: true } });
             const after = mod.store.sync(["fresh", "other"]);

             return { keys: Object.keys(after.keys).sort(), token: after.token, stored: JSON.parse(window.localStorage.getItem("c975l-favorite")) };'
        );

        $this->assertSame(['fresh', 'other'], $synced['keys'], 'The server\'s answer was merged into what the browser assumed instead of replacing it, so a removed favourite comes back.');
        $this->assertSame('kept', $synced['token'], 'Syncing the list threw the token away, so the browser loses the list it was holding.');
        $this->assertSame(['fresh', 'other'], array_keys($synced['stored']['keys']), 'The replacement was never written back, so the next page reads the stale list again.');
    }

    // Its own store and not the rating one: a visitor clearing one of the two features must not lose the other
    public function testTheListLivesInItsOwnStoreAndInNoCookie(): void
    {
        $stored = $this->store('mod.store.sync(["one"]); return { keys: Object.keys(window.localStorage), cookie: document.cookie };');

        $this->assertSame(['c975l-favorite'], $stored['keys'], 'The list is kept somewhere other than its own store, so clearing another feature takes it with it.');
        $this->assertSame('', $stored['cookie'], 'The list reaches a cookie, which travels on every request and is answered on pages that are cached and shared between visitors.');
    }

    private function store(string $probe, string $before = ''): mixed
    {
        return $this->observe(
            '<div></div>',
            [],
            $probe,
            ['modules' => ['store' => 'favorite-store'], 'before' => $before]
        );
    }
}
