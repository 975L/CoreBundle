---
name: c975l-ui-assets
description: "Use this skill when a stylesheet, a script, a font or a design token is involved in a Symfony application built on the c975L ecosystem — how a bundle gets its CSS and JS onto the page without a link tag, how the theme tokens resolve, what the scaffolded theme files own, and which helpers a satellite bundle must reuse rather than rewrite. Triggers on: ui.stylesheet, ui.script, BundleStylesheetProviderInterface, BundleScriptProviderInterface, bundle_stylesheets, StylesheetCacheWarmer, site.css, site-theme.css, ThemeVariablesCssListener, theme_variables_css, tokens, ui-defaults layer, ScaffoldThemeTest, scaffold themes, FontProviderInterface, font_preloads, importmap, handlers.js, UniqueSlug, BuildFileWriter, BlockFocusUrl, pointer-sort, sort-icon, ea-index-sort, infinite-scroll, toc.js, --icon-filter."
---

# c975L UiBundle — stylesheets, scripts and tokens

> No bundle ever asks an app to add a `<link>` or a `<script>`. It declares, the registry collects, and one compiled stylesheet is served.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Contract/BundleStylesheetProviderInterface.php`, `src/Contract/BundleScriptProviderInterface.php`, `src/Contract/FontProviderInterface.php`, `src/Service/StylesheetCacheWarmer.php`, `src/Service/BuildFileWriter.php`, `src/Service/UniqueSlug.php`, `src/Service/BlockFocusUrl.php`, `src/Listener/ThemeVariablesCssListener.php`, `sass/_tokens.scss`, `scaffold/assets/styles/themes/ui.css`, `assets/js/`, `assets/controllers.js`, `assets/controllers-admin.js`

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

Stay out of a theme file: colors and fonts, the per-variant section tokens mixed inside
`.section--bg-*` rules (declaring them in `:root` collapses the three variants — retune
`--section-bg-*` instead), and the tokens JavaScript writes at runtime.

Fonts are uploaded in the back office; `FontProviderInterface` feeds the `font` config kind's select,
and `font_preloads()` returns the files the current theme really uses.

## Helpers a satellite must reuse

| Helper | Role |
| --- | --- |
| `Service\UniqueSlug::build()` | normalizes a slug and suffixes `-2`, `-3`… until free; the scope stays the caller's |
| `Service\BlockFocusUrl::build()` | the EasyAdmin edit url of a block's owner, jumping to that block's row |
| `Service\BlockMoveRowAttrBuilder::build()` | the `row_attr` the sortable reads to drag a saved block into a container |
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
- **Do not set colors or fonts in a theme file** — they belong to the admin.
- **Do not put a second `@layer` in this bundle's stylesheets.**
- **Do not ship an empty theme file** "for later".
- **Do not share code between bundles through a trait** — use a static class or a service.
- **Do not write a generated file straight to its final path** — write and rename.
- **Do not subscribe a lazily-imported controller to DOMContentLoaded** without reading
  `document.readyState` first.
- **Do not hand-roll a drag gesture or a move grip** — `pointer-sort.js` and `sort-icon.js` are shared.
