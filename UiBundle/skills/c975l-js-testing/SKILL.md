---
name: c975l-js-testing
description: "Use this skill when a bundle's javascript has to be tested in a Symfony application built on the c975L ecosystem — running a Stimulus controller or a plain module in a real browser and reading back what it made of the DOM, rather than asserting on the text of the file. Covers the harness UiBundle ships for every bundle depending on it, what a satellite declares, what a scenario is given back clean, and what still belongs to a textual test. Triggers on: JsCase, browser test, behaviour test, BehaviourTest, observe, probe, bundleRoot, shipped, tab, openPage, serve, reopen, TargetDestroyed, The session is destroyed, Group('browser'), exclude-group browser, chrome-php/chrome, BrowserFactory, headless Chrome, stimulus.js, vendor-assets.json, scope tests, docroot, settle, keepBody, fresh, modules, before, css option, scripts option, styles option, JsCaseRecoveryTest, JsCaseGuardTest, StylesheetCascade, jsdom, node test runner, package.json, DOM emulation, scrollHeight, matchMedia stub, window.__out, window.__storage, window.__globals, navigator.webdriver, vanilla-cookieconsent hideFromBots."
---

# c975L UiBundle — javascript run rather than read

> One browser, one page and one server for the whole suite, and a scenario on them costs about a millisecond. A satellite bundle declares where its assets are and writes nothing else.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Testing/JsCase.php`, `src/Testing/stimulus.js`, `src/Testing/StylesheetCascade.php`, `config/vendor-assets.json`, `tests/Assets/`, `tests/Assets/JsCaseRecoveryTest.php`, `tests/Assets/JsCaseGuardTest.php`

**Related skills:** `c975l-ui-assets`, `c975l-blocks`, `c975l-media`, `c975l-forms-emails` in this same bundle.

## What a satellite bundle declares

`Testing\JsCase` ships in `src/` and not in `tests/`, for the same reason as `StylesheetCascade` beside
it: a bundle's `tests/` is autoload-dev and never reaches the bundles depending on it. **The whole of
what a satellite declares is where its assets are** — eleven lines, once, and nothing else:

```php
namespace c975L\YourBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase as UiJsCase;

abstract class JsCase extends UiJsCase
{
    protected function bundleRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
```

ConfigBundle, SiteBundle, BookBundle, PaymentBundle, SocialBundle and GalleryBundle each write exactly
that. Their controllers are then copied under the run's docroot and served beside UiBundle's, the bare
`@hotwired/stimulus` every controller imports is rewritten towards the Stimulus vendored in
`src/Testing/`, and a bare `@c975l/ui-bundle/…` specifier is resolved the way `importmap.php` resolves
it — an alias looked up rather than a path rewritten by hand, which is what makes a borrowing between
two bundles a checked contract rather than a hope.

## Writing a scenario

`JsCase::observe()` takes markup, the controllers to start over it, a probe read back as the answer,
and options. It mounts the fixture in a container of its own, roots a Stimulus `Application` on that
container, runs the probe, and takes both back down:

```php
#[Group('browser')]
class ReadmoreBehaviourTest extends JsCase
{
    public function testATextTheFoldCutsIsNotMarkedComplete(): void
    {
        $this->assertFalse($this->observe(
            '<div class="readmore" data-controller="readmore">...</div>',
            ['readmore' => 'readmore'],
            'return root.firstElementChild.classList.contains("readmore--complete");',
            ['css' => self::CSS, 'settle' => 120]
        ));
    }
}
```

- **The controllers map** is `identifier => module`: a bare name is looked up in the bundle's own
  `assets/js`, anything holding a slash is a path from that bundle's root.
- **The probe** is a function body run as `async (root, mod) => { … }`. `root` is the mount, `mod` the
  modules asked for in the options. Whatever it returns is what `observe()` answers; `undefined` comes
  back as `null`, and a throw fails the test with the browser's own stack.
- **An error a controller throws while starting** is caught by the application's error handler and
  rethrown before the probe runs, so a broken `connect()` fails the test rather than answering a DOM
  that was never built.

### The options

| Key | What it does |
| --- | --- |
| `scripts` | classic scripts put on the **page**, not the scenario — the vendored libraries, which define globals. Put back for every scenario that asks for one, and awaited: the file is already in the browser's cache, so a second scenario pays a parse |
| `styles` | stylesheets put on the page the same way |
| `css` | a rule block written into the scenario's own container, for a controller that measures a layout |
| `settle` | milliseconds waited after the application starts, for a real animation or a debounce |
| `before` | javascript run inside the scenario before the fixture is mounted and before the application starts — where a global is stood in for |
| `modules` | `name => module`, plain modules handed to the probe as its `mod` argument |
| `fresh` | asks for a copy of the module under a url of its own, for a scenario describing what it does the *first* time |
| `keepBody` | leaves what the scenario appended to `<body>` in place, for a library that owns a root of its own |

`JsCase::url()` gives the url a file is served from, for a controller taking one as a value.
`JsCase::shipped()` reads a file the bundle under test ships, for an assertion on what it contains
rather than on what it does.

## What every scenario is given back clean

The page is shared, so the resets are the harness rather than a detail. A scenario never has to undo
any of these itself:

| Left behind | Why it would reach the next scenario |
| --- | --- |
| A connected controller | Stopping an application disconnects nothing, and detaching the root it watches produces no mutation inside that root either — so the mount is emptied while the application still runs, and a tick given for it to notice. A slider left connected goes on advancing the *next* scenario's slider |
| An armed click swallower | A pointer-drag module waits to eat the click that ends a drag, and would eat the next scenario's first click |
| A modal dialog | It sits in the top layer over the whole page, so every hit test answers its backdrop |
| A cookie, a scroll position, a storage accessor | A scenario standing in for a browser that refuses storage defines its own accessor over `window.localStorage`, and that refusal would be every later scenario's too |
| A global stood in for | `matchMedia`, `fetch`, `XMLHttpRequest` and `open` are snapshotted when the page opens and put back between scenarios |
| A library stood in for | A scenario handed a stub of its own leaves it on the global, so a `scripts` entry is put back for every scenario that asks for it rather than once for the page |
| Anything appended to the body | Removed unless `keepBody` says otherwise, the body's own children being noted before the scenario runs |

`navigator.webdriver` is denied on the page: vanilla-cookieconsent hides itself from bots by default, so
its banner never rendered. The flag is what the page has to lie about — never this bundle's own
configuration.

## One browser, one page, one server

Made once for the whole run and kept in statics, which is what makes a scenario cost a millisecond
instead of the half second a fresh page costs:

- **The docroot and its server are made once and never again.** Each bundle's assets are copied under
  it the moment a test of that bundle first asks for them, and served under a directory named after
  that bundle — the server outliving every test class, a shared name would belong to whichever class
  happened to run first.
- **Served over `http://127.0.0.1` rather than opened as files.** A `file://` document has no cookie
  jar, so a consent library accepted a category and remembered nothing; and a module served from a
  `data:` url cannot resolve the relative import its neighbour is behind.
- **Nothing leaves the machine.** Third-party endpoints are pointed at `127.0.0.1:1`, which refuses the
  connection, and what is asserted is the DOM the library built — never a pixel, never a token minted
  by someone else.

## When the tab goes away under the run

A Chrome upgraded while the suite runs, or a renderer taken for its memory, destroys the tab — and
every scenario left then answered `The session is destroyed` without ever running. A scenario meeting a
`TargetDestroyed` is given a browser and a tab of its own and run **once** again, the docroot and its
server being kept, since they are what the bundles already copied are served from.

**A renderer that crashes rather than a tab that goes away reads as a timeout and is not replayed**,
being indistinguishable from a scenario that never settles.

Three members are protected for a test describing the harness itself, and for nothing else:
`JsCase::tab()` hands over the run's tab, `JsCase::serve()` starts the server and `JsCase::openPage()`
mounts the page. `JsCaseRecoveryTest` destroys the tab and asserts the next scenario still answers;
`JsCaseGuardTest` refuses a server and then a page, and asserts an attempt that failed halfway
published neither its docroot nor its page — a half-built one, shared, condemns every scenario that
comes after it rather than the one that met the failure.

## Why a real browser and not an emulated DOM

A controller that measures a layout — `scrollHeight`, a bounding rect, a resize or intersection
observer, a media query — reads `0` from an emulated DOM without erroring. A fold check written as
`scrollHeight <= clientHeight + 1` then reads `0 <= 1` and the suite goes green **over exactly the case
the controller exists to catch**. Chrome is already a dev dependency of this bundle (`chrome-php/chrome`,
which `LayoutAuditor` uses), so the layer adds none, and no `package.json` comes with it.

## What still belongs to a textual test

The scenarios did not replace the tests under `tests/Assets/` that read a script — they narrowed them.
A scenario mounts markup of its own, so it can say nothing about **what ships**:

- the barrel entry, the `data-controller` name and the `data-*-target` a real template writes;
- the class a stylesheet paints and the value a PHP service and a controller have to spell the same way;
- every guarantee of *absence* — that a controller never measures a layout, never builds a popup from a
  string, never queries the document twice — which no scenario can demonstrate.

Where an assertion on a line of implementation had no scenario, the scenario was written rather than
the assertion dropped.

## Running them

The whole layer is grouped: a test class carries `#[Group('browser')]`, and
`phpunit --exclude-group browser` takes it back out. It skips itself when `chrome-php/chrome` is absent
or `/usr/bin/google-chrome` is not executable, so a checkout without Chrome stays green rather than red.

## Do not

- **Do not reach for jsdom, a node runner or a `package.json`.** An emulated DOM lays nothing out and
  answers `0` to every measure, which is green over a broken controller.
- **Do not write a browser, a page or a server of your own** in a satellite bundle — declare
  `bundleRoot()` and nothing else. A second docroot leaves every bundle already copied unserved.
- **Do not put the harness under your bundle's `tests/`**: autoload-dev never reaches the bundles
  depending on you.
- **Do not clean up after your own scenario** — the container, the application, the body, the cookies,
  the storage accessors and the stood-in globals are all put back for you.
- **Do not load a vendored library inside the scenario**; hand it to `scripts` or `styles`, which put it
  on the page and wait for it — appending a script only queues it, and a scenario reading its global
  before it ran fails on the ordering rather than on the library.
- **Do not call `JsCase::tab()`, `JsCase::serve()` or `JsCase::openPage()` from a behaviour test.** They
  exist for a test describing the harness, and a behaviour test speaking to the tab rather than to the
  page it carries is one that leaves the next scenario something.
- **Do not catch a dead session yourself and retry** — the harness replays a destroyed tab once, and a
  second replay hides a scenario that genuinely never settles.
- **Do not publish a shared static before what it names is usable**, when editing the harness itself: a
  docroot whose server never answered sends every later test to port 0, and a page that never finished
  being mounted is handed to every scenario left in the run.
- **Do not assert on a pixel, a screenshot or a third party's answer.** Assert on the DOM the library
  built.
