---
name: c975l-ui-assets
description: "Use this skill when a stylesheet, a script, a font or a design token is involved in a Symfony application built on the c975L ecosystem — how a bundle gets its CSS and JS onto the page without a link tag, how the theme tokens resolve, what the scaffolded theme files own, and which helpers a satellite bundle must reuse rather than rewrite. Triggers on: ui.stylesheet, ui.script, BundleStylesheetProviderInterface, BundleScriptProviderInterface, bundle_stylesheets, StylesheetCacheWarmer, site.css, site-theme.css, ThemeVariablesCssListener, theme_variables_css, tokens, --viewport-width, --card-width-compact, ui-defaults layer, ScaffoldThemeTest, scaffold themes, --primary-ink, PrimaryInkRoleTest, ink tokens, --input-placeholder-color, FontProviderInterface, font_preloads, importmap, handlers.js, UniqueSlug, BuildFileWriter, BlockFocusUrl, pointer-sort, sort-icon, ea-index-sort, infinite-scroll, scroll-buttons, infiniteScroll, Paginator, Pagination, paginate, PAGE_PARAMETER, KnpPaginatorBundle, toc.js, --icon-filter, layout.html.twig, page layout, bodyClass, bodyClasses, bodyControllers, headingDisplayed, robots, alternates, hreflang, summarySocialNetwork, ogImage, ogImageAlt, csp-nonce, csp_nonce, format-detection, telephone=no, preconnect, site-preconnect, ui_can_hold_flash, flashes, block content, block container, block header, block footer, ignore_missing, StylesheetProvider, block-thumbs.min.css, block-picker."
---

# c975L UiBundle — stylesheets, scripts and tokens

> No bundle ever asks an app to add a `<link>` or a `<script>`. It declares, the registry collects, and one compiled stylesheet is served.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Contract/BundleStylesheetProviderInterface.php`, `src/Contract/BundleScriptProviderInterface.php`, `src/Contract/FontProviderInterface.php`, `src/Service/StylesheetCacheWarmer.php`, `src/Service/BuildFileWriter.php`, `src/Service/UniqueSlug.php`, `src/Service/BlockFocusUrl.php`, `src/Service/Paginator.php`, `src/Model/Pagination.php`, `src/Listener/ThemeVariablesCssListener.php`, `src/Service/StylesheetProvider.php`, `sass/_tokens.scss`, `sass/_block-thumbs.scss`, `scaffold/assets/styles/themes/ui.css`, `assets/js/`, `assets/controllers.js`, `assets/controllers-admin.js`, `templates/layout.html.twig`

**Related skills:** `c975l-blocks`, `c975l-media`, `c975l-forms-emails` in this same bundle, and `c975l-config` in ConfigBundle beside it.

## Getting CSS and JS onto the page

A bundle implements `BundleStylesheetProviderInterface` and tags the service `ui.stylesheet` with a
`priority` saying where its sheet belongs in the cascade. Scripts do the same with
`BundleScriptProviderInterface` / `ui.script`, and the back office has its own pair
(`BundleStylesheetManagementProviderInterface`, `BundleScriptAdminProviderInterface`) — **the
dashboard loads `@c975l/ui-bundle/admin.js`, never a site's own `app` entry**, which is what keeps a
site's front-end stylesheet out of EasyAdmin.

`bundle_stylesheets()` returns each sheet separately in debug, and in production a single
`public/bundles/build/site.css` concatenated by `StylesheetCacheWarmer`. A generated sheet under
`bundles/build/` is linked with its own mtime as `?v=`, that directory being served immutable for a
year.

**An app** implements the same interface and tags nothing. The scaffolded
`App\Service\ThemeStylesheetProvider` contributes the whole `assets/styles/themes/` directory plus the
app's own `app.css`, read last, so the design keeps the final word.

This bundle's `Service\StylesheetProvider` lists what every site needs. A sheet only some pages want
is compiled but left out of it, the app adding it from its own provider: `block-thumbs.min.css`, the
block-kind silhouettes (see `c975l-blocks`), is the first — the back office gets those same rules
through `sass/management.scss` instead.

**Never import a stylesheet from `assets/app.js`**: AssetMapper addresses a CSS entry by a
`data:application/javascript,` url, which a site's CSP blocks — taking the whole entrypoint down with
it. Symfony's own recipe writes exactly that import.

Importmap entries come from `ImportmapProviderInterface` (ConfigBundle) and are written on the first
`composer update` after installing a bundle; `c975l:config:check-importmap` reports a missing one.

A Stimulus controller of this bundle is registered in `assets/controllers.js` — front office — or in
`assets/controllers-admin.js`, which also mounts the back-office ones on `<body>` itself, EasyAdmin's
layout never writing a `data-controller`. A controller listed in that file's `LAZY_CONTROLLERS` is
imported dynamically, so **`connect()` usually runs after the page's DOMContentLoaded**: read
`document.readyState` rather than subscribing to an event already fired.

An icon laid on the page itself is an `<img>`, which paints its file's own black and takes no
`currentColor`: the ambience states the treatment in `--icon-filter`, `none` leaving a colored file
alone. `.btn .icon` and `.card-header .icon` carry their own inversion and weigh more.

## The page layout

`@c975LUi/layout.html.twig` is the shell every c975L site renders through, and the **single source for the
`<head>`**. SiteBundle's own layout `{% extends %}` it and adds only what having `Page`s, menus and a navbar
brings; the scaffolded `templates/layout.html.twig` extends whichever of the two is installed, so a site gains
or loses SiteBundle without a template changing. Nothing in the head is written twice.

**What the template rendering a page may set** - all optional, all read with `is defined`:

| Variable | What it does |
|---|---|
| `title` | The `<h1>` the `heading` block prints, and the `<title>`/`og:title`, suffixed with the site name |
| `headingDisplayed` | `false` keeps the title in the tab and out of the page |
| `robots` | The `robots` meta - `index, follow` by default, `noindex, follow` on an error page. Set it on a page not worth finding |
| `summarySocialNetwork` | The `description` meta and `og:description`, reduced to plain text |
| `ogImage` / `ogImageAlt` | The share image and what it shows, when the template knows better than the media library |
| `alternates` | `hreflang => absolute url` for the same page in other languages, the group named whole |
| `bodyClass` | A **block**: the class this one page adds to `<body>` |

**A child layout hands its own values over** as top-level `{% set %}`s - `ogImageMedia`, `headingDisplayed`,
`bodyClasses`, `bodyControllers`. Twig compiles the child's body ahead of the parent's, so they are already set
when the parent reads them: that is how SiteBundle feeds the head from its entities without this bundle knowing
one of them.

**Blocks to fill:** `header` and `footer` are empty here, filled by a layout that has a navbar or footer menus.
`main` wraps `heading`, `flashes`, `container` (holding `content`) and `share` (holding `navigationBottom`);
`container` adds no wrapper of its own. In the head: `meta`, `preconnect`, `fontPreload`, `stylesheets`,
`javascripts`/`importmap`, `title`.

- **The CSP nonce is minted for `script-src` *and* `style-src`** in the same breath and printed once as
  `<meta name="csp-nonce">`: Turbo nonces the inline tags it re-executes with that value *and* its own
  progress-bar `<style>`, so both directives have to carry it. NelmioSecurityBundle mints one nonce per
  response, so two adjacent `csp_nonce()` calls hand back the same string and put it in both.
- **`format-detection` is fixed at `telephone=no`**, a tag the layout always writes: left out, iOS turns
  anything shaped like a number - a date, a reference, a price - into a tappable phone link, restyled and
  pointing nowhere useful. `FormatDetectionMetaTest` asserts it on the shipped template.
- **`bodyClasses`/`bodyClass` land on `<body>`**, not on the content: a screen laid on its own background - a
  photo gallery, a reader - paints the navbar and the footer with it too.
- **Preconnects** merge the `site-preconnect` list with Matomo's origin, that one gated on
  `site-enable-matomo`: tracking off means no connection opened to a third party the site sends nothing to.
- **Flashes are read only behind `ui_can_hold_flash()`** - reading `app.flashes` is what starts a session. A
  label outside `success`/`info`/`warning`/`danger` is mapped onto its tint (`error`, `notice`) or falls back
  on `info`, rather than printing black ink on the dark page's own background.
- **An optional bundle's markup is `include`d, never called.** Twig resolves a function call at compile time
  and an include at render time, so a template calling a bundle's function answers 500 on every page of a site
  not installing it, whatever runtime guard wraps it. The share band is
  `include('@c975LSocial/shareButtons/default.html.twig', ignore_missing: true)` for exactly that reason.

## The token layers

Four layers, in this order:

1. **`sass/_tokens.scss`** — this bundle's default for every custom property it reads but does not
   own. They sit in `@layer ui-defaults`, and that layer is the point: a layered rule always loses to
   an unlayered one whatever the source order, so nothing has to be sequenced. An unresolved `var()`
   with no fallback invalidates its whole declaration, which is why a missing token would mean a card
   with *no* border rather than a slightly off one.
2. **Each bundle's own compiled stylesheet.**
3. **The admin's values** — the `theme-*` config entries, compiled by `ThemeVariablesCssListener` into
   `--c975l-*` properties in `bundles/build/site-theme.css`, also inlined into emails through
   `theme_variables_css()`. Colors, fonts and light/dark mode are **the admin's, not a design file's**.
4. **The app's `assets/styles/themes/*.css`**, scaffolded and owned by the app from then on.

**One theme file per bundle, named after it**, holding the tokens *that* bundle reads, every line
shipped commented out at its own default: uncomment to take it over, leave it and it keeps following
the bundle. `ScaffoldThemeTest` fails if a declared token is missing from the file, if a value shown
there is no longer the one in force, or if a line ships uncommented.

**A bundle ships its theme file the day it reads its first token, never before** — `assets` is the one
scaffold directory never overwritten once the target exists, so an empty placeholder installed today
would stay empty forever.

**Declare a token on `:root, [data-theme]`, never on `:root` alone.** A `var()` inside a custom
property's value is substituted where the declaration sits, not where the token is read: on `:root`
only, every derived token resolves once against the root palette and descends already computed, and a
scope opened below cannot repaint it.

**Write with `--primary-ink`, paint with `--primary`.** The brand color is two tokens, told apart by
what the rule does with it: `--primary` is the fill laid on the page (a button's background, a chip, a
band), `--primary-ink` that same color read against it (text, an outline, a rule, a focus ring). They
hold the same value until dark mode, where SiteBundle lightens the ink and leaves the fill its hue — so
a rule writing with `--primary` on a dark ground stays the dark brand color and vanishes into it.
`PrimaryInkRoleTest` fails on any ink property (`color`, `outline`, `border-*`, `text-decoration-color`,
`caret-color`, `fill`, `stroke`) reading `--primary`, its one listed exception being a label on a flat
that inverts to a stated white.

**Read the viewport through `--viewport-width`, never as a bare `100vw`.** A `calc()` subtracting a
`var()` from a `vw` length is valid CSS that the W3C validator reports as an *error* ("The types are
incompatible") on every page of a site — read through a custom property the same expression validates,
and resolves identically in every browser. `--card-width-compact` is the one that hit it.

Stay out of a theme file: colors and fonts, the per-variant section tokens mixed inside
`.section--bg-*` rules (declaring them in `:root` collapses the three variants — retune
`--section-bg-*` instead), and the tokens JavaScript writes at runtime.

Fonts are uploaded in the back office; `FontProviderInterface` feeds the `font` config kind's select,
and `font_preloads()` returns the files the current theme really uses.

## Listings that grow instead of paging

No listing in the c975L bundles renders page links. A template wraps its items in
`data-controller="infiniteScroll"`, marks the list `data-infiniteScroll-target="list"` and the link to
the next page `data-infiniteScroll-target="next"` with `data-action="click->infiniteScroll#load"`; the
controller fetches that page, appends the items found there and reads that page's own link to know
where to go next. An optional `data-infiniteScroll-target="count"` is filled with the number loaded.
Nothing is hidden by it: without javascript, and for a crawler, that link is the ordinary link to the
next page it looks like, and a failed fetch leaves it clickable.

The pages it walks are cut by **`Service\Paginator`**, this bundle's own. KnpPaginatorBundle is gone,
and so is the Pagination component that rendered nothing but its links.

```php
$books = $this->paginator->paginate($repository->findPublished(), $this->paginator->getPage($request->query), 12);
```

`paginate()` takes the listing **whole**, as an array - every caller reads its rows and sorts them in
php, there is no query-level pagination here - and returns a `Model\Pagination`, countable and
iterable, so `|length` and a `for` read it as an array. It answers `getCurrentPageNumber()`,
`getPageCount()`, `getTotalItemCount()`, `getItemNumberPerPage()`, `getRoute()` and `query()`:

```twig
<a href="{{ path(books.route, books.query({'p': books.getCurrentPageNumber + 1})) }}"
   data-infiniteScroll-target="next">{{ 'label.next'|trans }}</a>
```

`query()` carries the route's own parameters as well as the request's query, so a listing served under
`/serie/{slug}` keeps its slug and a search or a filter survives the jump. `getPage()` reads the number
from a query bag, `p` (`Paginator::PAGE_PARAMETER`) being the parameter every listing uses.

## Helpers a satellite must reuse

| Helper | Role |
| --- | --- |
| `Service\UniqueSlug::build()` | normalizes a slug and suffixes `-2`, `-3`… until free; the scope stays the caller's |
| `Service\BlockFocusUrl::build()` | the EasyAdmin edit url of a block's owner, jumping to that block's row |
| `Service\BlockMoveRowAttrBuilder::build()` | the `row_attr` the sortable reads to drag a saved block into a container |
| `Service\Paginator::paginate()` | cuts a listing into pages and says what the next page's url is built from - the paginator of the ecosystem, KnpPaginatorBundle being gone |
| `Service\BuildFileWriter::write()` | the one way to drop a generated stylesheet into `public/bundles/build/` — temp file then `rename()`, so no request ever reads half a sheet |
| `Form\VichImageOptions::default()` | the five Vich upload options |
| `Listener\AbstractBlockCacheInvalidationListener` | the Doctrine wiring of a listener reacting to one kind changing |
| `assets/js/pointer-sort.js` | the drag gesture, mouse and finger alike — reach for it rather than hand-rolling one |
| `assets/js/sort-icon.js` | the move grip both sortables mark their handle with, sized by EasyAdmin's own `.icon svg` |
| `assets/js/ea-index-sort.js` | reorders the rows of an EasyAdmin index; a CRUD opts in with `data-reorder-url`, `data-reorder-token` and an optional `data-reorder-group` on its index rows |
| `@c975l/ui-bundle/handlers.js` | the importmap entry a satellite imports the language reading and the translation helper from, rather than copying them |

They are static classes and services rather than traits **on purpose**: a trait shared across packages
is only analysed against callers in the same package, so PHPStan reports its properties as dead in
every bundle that copies it.

## Do not

- **Do not add a `<link>` or a `<script>` to a layout** for a bundle's assets. Declare a provider.
- **Do not import a stylesheet from `assets/app.js`.**
- **Do not load a site's `app` entry in the back office.**
- **Do not declare a token on `:root` alone**, and do not read a token without declaring its default.
- **Do not write a bare `vw` length into a `calc()` that also subtracts a `var()`** — use
  `--viewport-width`.
- **Do not write text, an outline or a rule with `--primary`** — that is the fill; ink is
  `--primary-ink`.
- **Do not set colors or fonts in a theme file** — they belong to the admin.
- **Do not put a second `@layer` in this bundle's stylesheets.**
- **Do not ship an empty theme file** "for later".
- **Do not share code between bundles through a trait** — use a static class or a service.
- **Do not write a generated file straight to its final path** — write and rename.
- **Do not subscribe a lazily-imported controller to DOMContentLoaded** without reading
  `document.readyState` first.
- **Do not pull KnpPaginatorBundle back in**, and do not render page links - `Paginator` cuts the pages
  and `infiniteScroll` walks them.
- **Do not hand-roll a drag gesture or a move grip** — `pointer-sort.js` and `sort-icon.js` are shared.
- **Do not restate a `<head>` tag in a child layout** — extend `@c975LUi/layout.html.twig` and set the
  variable it already reads.
- **Do not call an optional bundle's Twig function from a shared template** — `include` its fragment with
  `ignore_missing: true`. A call is resolved at compile time and 500s where that bundle is absent.
