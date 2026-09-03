<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Testing;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use PHPUnit\Framework\TestCase;

// Runs a bundle's javascript the way a page does, and reads back what it made of the DOM. Every other test under tests/Assets/ reads the same files as text, which passes on a line sitting in a branch nothing ever reaches, and fails on a rename that changed no behaviour at all
// A real browser rather than a DOM emulated in node: 24 of this bundle's 59 scripts call scrollHeight, getBoundingClientRect, ResizeObserver, IntersectionObserver or matchMedia, and an emulated DOM lays nothing out - it answers 0 to every measure without erroring, so readmore's "scrollHeight <= clientHeight + 1" reads 0 <= 1 and the suite goes green over a broken controller. Chrome is already a dev dependency here (see LayoutAuditor), so this adds none
// The usual cost objection runs the other way round: the browser and its server start once for the whole run in well under a second, and a scenario on the reused page costs about a millisecond, which is less than a node runner spends booting
// Lives in src/ rather than tests/ for the same reason as StylesheetCascade beside it: a bundle's tests/ is autoload-dev and never reaches the bundles that depend on it. A satellite bundle extends this, points bundleRoot() at itself, and drives its own controllers - this bundle's assets are served alongside either way, Stimulus and the vendored libraries living here
// Never registered as a service (see the Testing/ exclusion in config/services.yaml): a test utility that ships, in the same spirit as Symfony's own Test namespaces
abstract class JsCase extends TestCase
{
    private const string CHROME = '/usr/bin/google-chrome';

    // How long a scenario may take to settle before it is called a failure rather than left hanging the suite
    private const int TIMEOUT_MS = 5000;

    private static ?Browser $browser = null;

    private static ?Page $page = null;

    private static ?string $docroot = null;

    private static ?int $port = null;

    /** @var resource|null */
    private static $server;

    private static int $copies = 0;

    /**
     * The bundle trees already copied under the docroot, as absolute root => the name they are served under.
     *
     * @var array<string, string>
     */
    private static array $published = [];

    protected function setUp(): void
    {
        if (!class_exists(BrowserFactory::class)) {
            $this->markTestSkipped('chrome-php/chrome is needed to run this bundle\'s javascript.');
        }

        if (!is_executable(self::CHROME)) {
            $this->markTestSkipped('google-chrome is needed to run this bundle\'s javascript.');
        }
    }

    /**
     * Mounts the fixture, starts the named controllers over it, runs the probe against it and returns what the probe answered.
     *
     * @param array<string, string> $controllers identifier => module, bare for this bundle's assets/js, path from the repository root otherwise
     * @param array<string, mixed>  $options     scripts, styles, css and settle
     */
    protected function observe(string $html, array $controllers, string $probe, array $options = []): mixed
    {
        $this->preload($options);
        $answer = $this->evaluate($this->scenario($html, $probe, $controllers, $options));

        if (true !== ($answer['ok'] ?? false)) {
            $this->fail((string) ($answer['error'] ?? 'The scenario answered nothing at all.'));
        }

        return $answer['value'];
    }

    // Classic scripts and stylesheets belong to the page rather than to the scenario: they are the vendored libraries, they define globals, and loading one per scenario would be the only slow thing here
    private function preload(array $options): void
    {
        foreach ($options['scripts'] ?? [] as $script) {
            $this->put($script, 'script');
        }

        foreach ($options['styles'] ?? [] as $style) {
            $this->put($style, 'stylesheet');
        }
    }

    /**
     * The controllers started over the fixture, and the plain modules handed to the probe as its second argument.
     *
     * Not everything a bundle ships is a Stimulus controller: block-picker.js and pointer-sort.js are plain modules,
     * one delegating from the document and the other exporting a function.
     *
     * @return array{0: string, 1: string}
     */
    private function imports(array $controllers, array $options): array
    {
        // A module keeps whatever it holds at its top level for as long as the document lives, and the browser hands back the same copy for the same url. A scenario describing what a module does the first time - map.js caching its loader, and clearing it again when that load fails - asks for a copy of its own
        $fresh = ($options['fresh'] ?? false) ? '?copy=' . ++self::$copies : '';

        $registrations = [];
        foreach ($controllers as $identifier => $module) {
            $registrations[] = sprintf('app.register(%s, (await import(%s)).default);', json_encode($identifier), json_encode($this->url($module) . $fresh));
        }

        $modules = [];
        foreach ($options['modules'] ?? [] as $name => $module) {
            $modules[] = sprintf('%s: await import(%s)', json_encode($name), json_encode($this->url($module)));
        }

        return [implode("\n                    ", $registrations), '{ ' . implode(', ', $modules) . ' }'];
    }

    /**
     * The bundle whose assets a test drives: a bare module name is looked up in its assets/js, and a path in its own tree.
     *
     * Overridden by a satellite bundle to point at itself. This bundle's assets are served beside it either way,
     * under "c975lui", Stimulus and the vendored libraries living here.
     */
    protected function bundleRoot(): string
    {
        return self::uiRoot();
    }

    /**
     * The url a file is served from, for a controller taking one as a value.
     */
    protected function url(string $path): string
    {
        $this->page();

        // Stimulus is the one thing served outside any bundle, every controller of every bundle importing the same copy
        if (!str_starts_with($path, 'vendor/')) {
            // A controller is named by its module alone, anything else by the path its bundle ships it at
            $path = $this->publish($this->bundleRoot()) . '/' . (str_contains($path, '/') ? ltrim($path, '/') : 'assets/js/' . $path . '.js');
        }

        return sprintf('http://127.0.0.1:%d/%s', self::$port, $path);
    }

    /**
     * Reads a file the bundle under test ships, for a test asserting on what it contains rather than on what it does.
     */
    protected function shipped(string $path): string
    {
        return (string) file_get_contents($this->bundleRoot() . '/' . $path);
    }

    // The scenario: a container of its own, an Application rooted on it, and both taken back down afterwards, so nothing a test leaves behind can reach the next one on this shared page
    private function scenario(string $html, string $probe, array $controllers, array $options): string
    {
        [$registrations, $modules] = $this->imports($controllers, $options);

        $settle = isset($options['settle'])
            ? sprintf('new Promise((r) => setTimeout(r, %d))', (int) $options['settle'])
            : 'new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))';

        // Stimulus swallows what a controller throws while connecting and only logs it, which would reach a test as "nothing happened" rather than as the error it is
        return sprintf(
            'window.__out = null; (async () => {
                // The page is shared by the whole run, so a scenario starts from whatever the previous one left on it: a cookie a library stored, and anything appended outside the container - vanilla-cookieconsent renders its banner straight into the body
                for (const pair of document.cookie.split(";")) {
                    const name = pair.split("=")[0].trim();
                    if (name) { document.cookie = name + "=;max-age=0;path=/"; }
                }
                const untouched = new Set(document.body.children);

                // And from wherever the previous one left the page: a scenario reading a scroll position, or aiming a pointer at coordinates it measured, starts from the top or it measures against somebody else\'s scroll
                window.scrollTo(0, 0);

                // A drag ended in the previous scenario leaves pointer-sort.js waiting to swallow the click that follows it (see suppressNextClick), and the next scenario\'s first click is the one it eats. This is what a real page does next, and what that listener gives up on
                document.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));

                // A modal dialog left open sits in the top layer over the whole page, so every hit test answers its backdrop rather than what the scenario put on screen
                for (const dialog of document.querySelectorAll("dialog[open]")) { dialog.close(); }

                // Emptied, and given back to the window: a scenario standing in for a browser that refuses storage defines an accessor of its own over it, and that refusal would otherwise be every later scenario\'s too. Restored from the descriptor taken when the page opened, because on a window these are own properties - deleting one does not uncover anything, it destroys it
                for (const [name, descriptor] of Object.entries(window.__storage)) { Object.defineProperty(window, name, descriptor); }
                try { window.localStorage.clear(); window.sessionStorage.clear(); } catch { /* a browser refusing it for real */ }

                // The same for what a scenario stands in for rather than refuses: a media query answered differently, a network answered by hand, a request driven step by step. Left in place, one scenario\'s reduced-motion stub is every later scenario\'s answer too
                for (const [name, value] of Object.entries(window.__globals)) { window[name] = value; }

                const box = document.createElement("div");
                let app = null;
                let mount = null;
                let answer = null;
                try {
                    // Whatever has to be true before a controller connects: a global reset, a stub standing in for a library, a media query answered differently
                    %s

                    const style = document.createElement("style");
                    style.textContent = %s;
                    box.appendChild(style);

                    mount = document.createElement("div");
                    mount.innerHTML = %s;
                    box.appendChild(mount);
                    document.body.appendChild(box);

                    const { Application } = await import(%s);
                    app = new Application(mount);
                    let thrown = null;
                    app.handleError = (error) => { thrown = thrown || error; };
                    %s
                    app.start();
                    await %s;
                    if (thrown) { throw thrown; }

                    const mod = %s;
                    const value = await (async (root, mod) => { %s })(mount, mod);
                    answer = { ok: true, value: value === undefined ? null : value };
                } catch (error) {
                    answer = { ok: false, error: String(error && error.stack ? error.stack : error) };
                } finally {
                    // Emptied while the application is still running, and a tick given for it to notice. Stopping an application only stops it watching the DOM - it disconnects nothing already connected - and detaching the root it watches produces no mutation inside that root either, so neither of the two obvious moves gets a controller to let go. A controller left connected keeps whatever it set running: an interval, a listener on the window, an observer - on the very next scenario as much as on its own
                    mount?.replaceChildren();
                    await new Promise((r) => setTimeout(r, 0));
                    app?.stop();
                    box.remove();

                    // A library rebuilding its own root on every run (vanilla-cookieconsent) has to find the body as it was; a module owning a single element for the life of the page (the dialog block-picker builds) keeps a reference to it, and taking it away leaves the module driving something detached
                    if (%s) {
                        for (const element of [...document.body.children]) {
                            if (!untouched.has(element)) { element.remove(); }
                        }
                    }

                    // Answered last, so nothing is read back before the scenario has finished clearing up after itself
                    window.__out = JSON.stringify(answer);
                }
            })();',
            (string) ($options['before'] ?? ''),
            json_encode((string) ($options['css'] ?? '')),
            json_encode($html),
            json_encode($this->url('vendor/stimulus.js')),
            $registrations,
            $settle,
            $modules,
            $probe,
            json_encode(!($options['keepBody'] ?? false))
        );
    }

    // The probe answers on window rather than through a returned promise, which is the one shape that behaves the same whatever the scenario awaits inside
    private function evaluate(string $js): array
    {
        $page = $this->page();
        $page->evaluate($js)->getReturnValue();

        $deadline = microtime(true) + self::TIMEOUT_MS / 1000;
        while (microtime(true) < $deadline) {
            $out = $page->evaluate('window.__out')->getReturnValue();

            if (null !== $out) {
                return json_decode((string) $out, true, 512, \JSON_THROW_ON_ERROR);
            }

            usleep(2000);
        }

        return ['ok' => false, 'error' => sprintf('The scenario never answered within %d ms - something it awaits never settles.', self::TIMEOUT_MS)];
    }

    // A vendored library put on the page and awaited there: appending a script only queues it, and a scenario reading its global before it ran would fail on the ordering rather than on the library
    // Put back for every scenario that asks for it rather than once for the page: a scenario standing in for the library - confetti.js is handed a stub of its own - leaves that stub on the global, and the next scenario wanting the real one would find it gone. The file is served from the docroot and already in the browser\'s cache, so this costs a parse
    private function put(string $path, string $kind): void
    {
        $url = $this->url($path);

        $this->page()->evaluate(sprintf(
            'window.__ready = window.__ready || {}; (() => {
                const element = document.createElement(%s);
                element.onload = () => { window.__ready[%s] = true; };
                %s
                document.head.appendChild(element);
            })();',
            json_encode('script' === $kind ? 'script' : 'link'),
            json_encode($path),
            'script' === $kind
                ? sprintf('element.src = %s;', json_encode($url))
                : sprintf('element.rel = "stylesheet"; element.href = %s;', json_encode($url))
        ))->getReturnValue();

        $deadline = microtime(true) + self::TIMEOUT_MS / 1000;
        while (microtime(true) < $deadline) {
            if (true === $this->page()->evaluate(sprintf('window.__ready[%s] === true', json_encode($path)))->getReturnValue()) {
                return;
            }

            usleep(2000);
        }

        $this->fail(sprintf('"%s" never finished loading in the page.', $path));
    }

    // One browser, one server and one page for the whole run, which is what makes a scenario cost a millisecond instead of the half second a fresh page costs
    // Served over http rather than opened as a file, for two reasons that both turned up in practice: a file:// document has no cookie jar, so vanilla-cookieconsent accepted a category and remembered nothing, and a module served from a data: url cannot resolve a relative import (video-iframe.js imports ./nonced-style-element.js)
    private function page(): Page
    {
        if (null !== self::$page) {
            return self::$page;
        }

        self::$docroot = sys_get_temp_dir() . '/jscase-' . bin2hex(random_bytes(6));
        mkdir(self::$docroot . '/vendor', 0o777, true);
        copy(__DIR__ . '/stimulus.js', self::$docroot . '/vendor/stimulus.js');
        file_put_contents(self::$docroot . '/index.html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>c975L</title></head><body></body></html>');
        self::$port = $this->serve(self::$docroot);

        self::$browser = self::launch();

        self::$page = self::$browser->createPage();
        self::$page->navigate(sprintf('http://127.0.0.1:%d/', self::$port))->waitForNavigation();

        // Headless Chrome announces itself through navigator.webdriver, and a library that hides from bots then behaves for the tests as it never would for a visitor: vanilla-cookieconsent's "hideFromBots" is on by default, so the banner simply never rendered. Denying the flag puts the page back in the state being described, and leaves this bundle's own configuration untouched, which is the point
        self::$page->evaluate('Object.defineProperty(navigator, "webdriver", { get: () => false, configurable: true });')->getReturnValue();

        // Kept aside while nothing has touched them, so a scenario standing in for a browser that refuses storage - or for a network, a request, a media query - can be undone before the next one starts
        self::$page->evaluate('window.__storage = { localStorage: Object.getOwnPropertyDescriptor(window, "localStorage"), sessionStorage: Object.getOwnPropertyDescriptor(window, "sessionStorage") };
            window.__globals = { matchMedia: window.matchMedia, fetch: window.fetch, XMLHttpRequest: window.XMLHttpRequest, open: window.open };')->getReturnValue();

        // The browser outlives every test class on purpose, so it is closed with the process rather than by any one of them
        register_shutdown_function(static function (): void {
            self::$browser?->close();
            self::$browser = null;
            self::$page = null;

            if (\is_resource(self::$server)) {
                proc_terminate(self::$server);
                proc_close(self::$server);
                self::$server = null;
            }

            self::remove((string) self::$docroot);
        });

        return self::$page;
    }

    // Opens the browser, and once more if the first attempt dies on its way up
    // The run's first test pays for the coldest launch of the whole CI job, and that launch has been seen printing its debugging endpoint and then dying before the first message reached it, while the one the next test made worked (run 33774815362)
    // Twice rather than indefinitely, so a Chrome that is genuinely broken still fails the suite rather than doubling its time
    private static function launch(int $attempts = 2): Browser
    {
        try {
            // Parenthesised on purpose: PDepend, which phpmd reads this file through now that it lives in src/, cannot parse the parentheses-less form
            return (new BrowserFactory(self::CHROME))->createBrowser([
                'headless' => true,
                // Same reason as LayoutAuditor: the sandbox refuses to start for the user a CI image runs as
                'noSandbox' => true,
                'windowSize' => [1200, 900],
                // Chrome keeps a tab's shared memory under /dev/shm, and an image that sizes it small kills the tab instead of saying so
                'customFlags' => ['--disable-dev-shm-usage'],
            ]);
        } catch (CommunicationException | OperationTimedOut $exception) {
            if ($attempts <= 1) {
                throw $exception;
            }

            // Left the time to give back what the dead process still held
            usleep(500000);

            return self::launch($attempts - 1);
        }
    }

    // The bundles' assets copied under a docroot rather than served from the working tree: the copy is where the bare "@hotwired/stimulus" every controller imports is rewritten towards the vendored fixture, which is the only thing standing between a controller and a browser that has never heard of an import map
    /**
     * Copies a bundle's assets under the docroot and answers the name they are served under.
     *
     * Lazily, and once per bundle: the server lives for the whole run, and a test class of another bundle
     * reaches it long after it started - which is also why each tree is served under its own name rather
     * than under a shared one the first class to run would have claimed.
     */
    private function publish(string $root): string
    {
        if (isset(self::$published[$root])) {
            return self::$published[$root];
        }

        // This bundle first whatever is under test, its own name being needed before anything importing from it can be rewritten: a satellite's controller borrows by name, PaymentBundle's basket handlers the language reading held here and GalleryBundle's media sort the drag gesture
        if ($root !== self::uiRoot()) {
            $this->publish(self::uiRoot());
        }

        $name = basename($root);
        while (\in_array($name, self::$published, true)) {
            $name .= '-';
        }

        self::$published[$root] = $name;
        $docroot = (string) self::$docroot;

        // The copy is where the bare specifiers a controller imports are rewritten towards files a browser that has never heard of an import map can fetch
        $this->mirror($root . '/assets', $docroot . '/' . $name . '/assets', true);
        // The vendored libraries are loaded by url by the very controllers under test, so they are served at the path a site serves them from
        $this->mirror($root . '/public/js', $docroot . '/' . $name . '/public/js', false);
        $this->mirror($root . '/public/css', $docroot . '/' . $name . '/public/css', false);

        return $name;
    }

    // This bundle's own root, read from where this file sits and never from the consumer's layout: installed as a dependency it lives in vendor/c975l/ui-bundle, with no repository root above it to count from
    private static function uiRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Where a bare "@c975l/ui-bundle/..." specifier points, resolved the way importmap.php does.
     *
     * The name is an alias rather than a path (see UiBundle's ImportmapProvider): "pointer-sort.js" is served
     * from assets/js while the two barrels sit at the root of assets, so it is looked up rather than guessed at.
     */
    private function resolve(string $module): string
    {
        $ui = self::$published[self::uiRoot()];

        return is_file(self::uiRoot() . '/assets/js/' . $module)
            ? '/' . $ui . '/assets/js/' . $module
            : '/' . $ui . '/assets/' . $module;
    }

    private function mirror(string $from, string $to, bool $rewrite): void
    {
        if (!is_dir($from)) {
            return;
        }

        mkdir($to, 0o777, true);

        foreach (scandir($from) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            if (is_dir($from . '/' . $entry)) {
                $this->mirror($from . '/' . $entry, $to . '/' . $entry, $rewrite);

                continue;
            }

            if (!$rewrite) {
                copy($from . '/' . $entry, $to . '/' . $entry);

                continue;
            }

            $content = str_replace(
                ['"@hotwired/stimulus"', "'@hotwired/stimulus'"],
                '"/vendor/stimulus.js"',
                (string) file_get_contents($from . '/' . $entry)
            );

            // The quote is kept as it was written rather than rewritten with the specifier, a module opened on one quote and closed on the other being a parse error rather than a failed import
            file_put_contents($to . '/' . $entry, preg_replace_callback(
                '#([\'"])@c975l/ui-bundle/([^\'"]+)#',
                fn (array $matched): string => $matched[1] . $this->resolve($matched[2]),
                $content
            ));
        }
    }

    // A port taken at random and tried until one answers: the suite may well be run beside a site's own server, and a fixed port would make the two collide
    private function serve(string $docroot): int
    {
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $port = random_int(8300, 8999);
            // $pipes is the by-reference third argument proc_open's signature requires; nothing here writes to the server's stdin
            self::$server = proc_open(
                sprintf('exec php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($docroot)),
                [['pipe', 'r'], ['file', '/dev/null', 'a'], ['file', '/dev/null', 'a']],
                $pipes
            );
            unset($pipes);

            if (!\is_resource(self::$server)) {
                continue;
            }

            $deadline = microtime(true) + 3;
            while (microtime(true) < $deadline) {
                if ($socket = @fsockopen('127.0.0.1', $port, $code, $message, 0.1)) {
                    fclose($socket);

                    return $port;
                }

                // Both are by-reference out parameters fsockopen's signature requires; kept for the failure below rather than dropped
                $refused = sprintf('%s (%d)', $message, $code);

                usleep(20000);
            }

            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }

        $this->fail(sprintf('No port could be found to serve the javascript fixtures from: %s.', $refused ?? 'no attempt was made'));
    }

    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            is_dir($path . '/' . $entry) ? self::remove($path . '/' . $entry) : @unlink($path . '/' . $entry);
        }

        @rmdir($path);
    }
}
