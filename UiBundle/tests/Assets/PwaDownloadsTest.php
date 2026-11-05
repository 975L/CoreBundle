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

// What a visitor downloads for offline use lives in a cache the service worker serves first and never drops on a deploy
class PwaDownloadsTest extends TestCase
{
    private const string WORKER = 'templates/pwa/sw.js.twig';
    private const string STORE = 'assets/js/pwa-downloads-store.js';

    // The worker and the scripts name the same cache, and its activation spares it
    public function testTheDownloadsCacheSurvivesADeploy(): void
    {
        $worker = $this->read(self::WORKER);

        $this->assertStringContainsString('const DOWNLOADS = "c975l-pwa-downloads";', $worker);
        $this->assertStringContainsString('export const DOWNLOADS = "c975l-pwa-downloads";', $this->read(self::STORE));
        $this->assertStringContainsString('key !== CACHE && key !== DOWNLOADS', $worker);
    }

    // A page stays the server's while online, its downloaded copy only answering when the network fails, whatever "Vary" it was served with
    public function testADownloadedPageIsOnlyTheFallback(): void
    {
        $worker = $this->read(self::WORKER);

        $this->assertStringContainsString('return downloaded(request).then((copy) => copy || offlinePage());', $worker);
        $this->assertStringContainsString('ignoreVary: true', $worker);
    }

    // A Turbo visit failing online is a redirection off the site, not a lost network: answering it with the offline page would stop a sign-in at a provider on "Offline"
    public function testAFailedTurboVisitIsNotCalledOfflineWhileOnline(): void
    {
        $worker = $this->read(self::WORKER);

        $this->assertStringContainsString("if (\"navigate\" !== request.mode && self.navigator.onLine) {\n                throw error;", $worker);
        $this->assertLessThan(
            strpos($worker, 'return downloaded(request).then((copy) => copy || offlinePage());'),
            strpos($worker, 'throw error;'),
        );
    }

    // Online, a recording or a script's own request never goes through the worker, so a site keeps its speed; offline, an audio element's ranges are answered from the stored file
    public function testARecordingIsOnlyServedOffline(): void
    {
        $worker = $this->read(self::WORKER);

        $this->assertStringContainsString("if (!self.navigator.onLine) {\n        event.respondWith(downloaded(request).then((copy) => (copy ? ranged(request, copy) : fetch(request))));", $worker);
        $this->assertStringContainsString('status: 206', $worker);
        $this->assertStringContainsString('"Content-Range": `bytes ${start}-${end}/${blob.size}`', $worker);
    }

    // The offline page leaves room for the list the worker writes, with no script of its own
    public function testTheOfflinePageListsTheDownloads(): void
    {
        $offline = $this->read('templates/pwa/offline.html.twig');

        $this->assertStringContainsString('<!--pwa-downloads-->', $offline);
        $this->assertStringContainsString('data-pwa-downloads hidden', $offline);
        $this->assertStringContainsString('.replace("<!--pwa-downloads-->", items)', $this->read(self::WORKER));
    }

    // A page kept offline carries no session, and a failed download leaves nothing behind
    public function testADownloadIsAnonymousAndAllOrNothing(): void
    {
        $store = $this->read(self::STORE);

        $this->assertStringContainsString('credentials: "omit"', $store);
        $this->assertStringContainsString('await dropUnshared(cache, kept, registry);', $store);
    }

    public function testBothControllersAreRegistered(): void
    {
        $controllers = $this->read('assets/controllers.js');

        $this->assertStringContainsString("'pwa-download': () => import('./js/pwa-download.js')", $controllers);
        $this->assertStringContainsString("'pwa-downloads': () => import('./js/pwa-downloads.js')", $controllers);
    }

    private function read(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, half of the mechanism this test checks is gone.', $file));

        return (string) file_get_contents($path);
    }
}
