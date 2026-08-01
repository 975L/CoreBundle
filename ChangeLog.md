# ChangeLog

## v1.17.0

Ship the token catalogue as a scaffolded theme file

- Added `scaffold/assets/styles/themes/ui.css`, the catalogue of every token this bundle reads (01/08/2026)
- `StylesheetRegistryPass` now tags any service implementing `BundleStylesheetProviderInterface` (01/08/2026)
- An auto-tagged provider now carries an explicit priority, below every bundle's range (01/08/2026)
- Added `--font-body-weight`, `--frame-background` and `--contact-details-col-min` to the scaffolded theme (01/08/2026)
- Added `ScaffoldThemeTest`, locking the scaffolded theme against the compiled token defaults (01/08/2026)
- The `contact_details` panel now stops at the page measure instead of running the full page width (01/08/2026)
- The opening-hours rows now wrap, and a long e-mail or url breaks instead of overflowing the panel (01/08/2026)

## v1.16.0

Lay a hero's medias as a grid, and rebuild the contact_details panel

- Added the `mediaLayout` field on `HeroType`, `slideshow` (default) or `grid` (01/08/2026)
- Added `.hero__media--grid`, three columns of square tiles (01/08/2026)
- `BlockType::HERO_MEDIA_MAX` went from 6 to 9, with the matching slideshow timings (01/08/2026)
- Fixed the hero slideshow freezing on its last image (01/08/2026)
- Each slide count now carries its own `@keyframes`, generated from `$hero-slide-max` (01/08/2026)
- The `contact_details` panel now lays its fields out on an `auto-fit` grid (01/08/2026)
- The `contact_details` panel now reads the `--section-*` tokens (01/08/2026)
- The `contact_details` e-mail is no longer a `mailto:` link (01/08/2026)
- `--contact-details-col-min` is now read instead of declared (01/08/2026)
- Fixed the links of a `contact_details` description being repainted as plain text (01/08/2026)
- Added `BlockAccentChoiceType`, twelve fixed hues for a block's accent (01/08/2026)
- Added the `accent` field on `CardType`, a colored rule across the card's top edge (01/08/2026)
- Added the twelve `--block-accent-*` tokens (01/08/2026)
- `.card` now carries `position: relative` (01/08/2026)
- A registered stylesheet path under `assets/` is now read as one of the app's own sheets (01/08/2026)
- Added `StylesheetRegistry::isAppAsset()` and `StylesheetRegistry::logicalPath()` (01/08/2026)
- `BlockFixtureProvider`'s urls are now `example.com` instead of `975l.com` (01/08/2026)
- `BlockFixtureProvider`'s section fixtures now hold neutral sample text (01/08/2026)
- Added `tests/Templates/HeroMediaLayoutTest` and `tests/Assets/HeroSlideshowTimingTest` (01/08/2026)
- Added `tests/Form/BlockAccentChoiceTypeTest` (01/08/2026)

## v1.15.2

Focus a named field from a link, and stop a duplicate reusing its source's id

- Added `assets/js/field-focus.js`, the `fieldFocus` Stimulus controller (01/08/2026)
- A `/management` form url carrying `focusField=<property>` now opens that field's tab, scrolls to it and focuses it (01/08/2026)
- `Controller\BlockFormController::dataForm()` now drops the posted `id` so a duplicated block gets its own (01/08/2026)
- Added `tests/Assets/FieldFocusControllerRegistrationTest` (01/08/2026)

## v1.15.1

Widen the banner_title bottom margin

- The `banner_title` block's bottom margin is now `3em` instead of `1em` (01/08/2026)

## v1.15.0

Replace rich_snippet by a contact_details block publishing JSON-LD

- Removed the `rich_snippet` block kind, `Form\Block\RichSnippetType`, `templates/blocks/RichSnippet.html.twig`, `templates/components/General/RichSnippet.html.twig` and `sass/_snippet.scss`, see UPGRADE.md (01/08/2026) [BC-Break]
- Added the `contact_details` block kind, `Form\Block\ContactDetailsType` and `Form\Block\ContactHoursType` (01/08/2026)
- Added `Service\ContactSnippetBuilder`, building the block's schema.org graph (01/08/2026)
- Added `Twig\ContactExtension`, exposing `contact_json_ld()` and `contact_day_runs()` (01/08/2026)
- Added `templates/components/Contact/Details.html.twig`, `templates/blocks/ContactDetails.html.twig` and `sass/_contact-details.scss` (01/08/2026)
- Opening hours are now entered as day/range rows instead of one free-text line (01/08/2026)
- Opening and closing times are now native time pickers storing `HH:MM` (01/08/2026)
- The website and map links are now stored absolute (01/08/2026)
- A malformed website, map link or e-mail is now refused on save (01/08/2026)
- Added the `addressComplement`, `addressRegion`, `mobile`, `email`, `url`, `mapUrl`, `latitude` and `longitude` fields (01/08/2026)
- Every field of the kind is now optional (01/08/2026)
- An attached image is now used as the logo and published as the graph's `image` (01/08/2026)
- `Twig\BlockExtension::renderBlock()` now skips a block whose kind is no longer registered instead of throwing (01/08/2026)
- Added a bottom margin to the `banner_title` block (01/08/2026)
- Added `tests/Service/ContactSnippetBuilderTest`, `tests/Twig/ContactExtensionTest`, `tests/Form/Block/ContactDetailsTypeTest`, `tests/Form/Block/ContactHoursTypeTest` and `tests/Templates/ContactDetailsMarkupTest` (01/08/2026)

## v1.14.2

Fix a slider swipe moving several images and leaving the dots behind

- `assets/js/slider.js` now drives the freeflow layout's horizontal drag itself (01/08/2026)
- A swipe now moves exactly one slide, from the one it started on (01/08/2026)
- The dots now follow any scroll of a freeflow list, swipe, wheel or trackpad (01/08/2026)
- Freeflow slides now snap centered instead of left-aligned (01/08/2026)
- The current slide is now read from the tracked index, not from the first one still classed active (01/08/2026)
- Fixed a second swipe landing mid-animation leaving two slides displayed at once (01/08/2026)
- The compatibility click following a swipe no longer advances a further slide (01/08/2026)
- Added `tests/Assets/SliderSwipeTest` (01/08/2026)

## v1.14.1

Alias dev-main as 1.x-dev so the ConfigBundle constraint resolves

- Added the `branch-alias` extra, aliasing `dev-main` as `1.x-dev` (01/08/2026)

## v1.14.0

Drop the showcase placeholder files, accept SVG for the icon roles

- Removed the five `public/images/gallery-photo-*.webp`, `public/videos/gallery-video.mp4`, `public/videos/gallery-video-embed.html`, `public/audio/gallery-audio.mp3` and `public/documents/gallery-document.pdf`, see UPGRADE.md (31/07/2026) [BC-Break]
- Removed `Service\BlockFixtureMediaAttacher::PLACEHOLDER_IMAGES`, `PLACEHOLDER_VIDEO`, `PLACEHOLDER_VIDEO_EMBED`, `PLACEHOLDER_AUDIO` and `PLACEHOLDER_DOCUMENT` (31/07/2026) [BC-Break]
- `Service\BlockFixtureMediaAttacher::nextPlaceholderImage()` now returns `?Media` (31/07/2026) [BC-Break]
- Added `Contract\PlaceholderMediaProviderInterface`, `Registry\PlaceholderMediaRegistry` and `DependencyInjection\Compiler\PlaceholderMediaProviderPass` (31/07/2026)
- `Service\BlockFixtureMediaAttacher` now reads that registry as its only source of placeholder media (31/07/2026)
- `attach()` attaches nothing for a media an app declares none of (31/07/2026)
- A placeholder's mimetype now follows its own file extension (31/07/2026)
- `Service\BlockFixtureProvider` reads the registry too, for `video_iframe`'s muted embed wrapper (31/07/2026)
- `Service\BlockFixtureProvider`'s `video_iframe` fixture carries an empty `src` when no embed wrapper is declared (31/07/2026)
- Added the `ui-block-showcase-url` config entry (*general* group), pre-filled with `https://bundles.975l.com/pages/blocks` (31/07/2026)
- `Management\MenuProvider::getLinks()` reads that entry instead of a hardcoded url (31/07/2026)
- `MenuProvider::BLOCK_SHOWCASE_URL` is the fallback when that entry is empty or missing (31/07/2026)
- Added the `label.ui_block_showcase_url` and `description.block_showcase_url` translations (en/fr/es) (31/07/2026)
- Updated the README's own references to that showcase (31/07/2026)
- Favicon and apple-touch-icon now accept an SVG upload (31/07/2026)
- Added `Service\SvgRasterizer`, rendering an uploaded SVG to PNG for the icon pipeline (31/07/2026)
- Added the `ext-imagick` suggest, needed to rasterize an SVG icon (31/07/2026)
- Added `Validator\FixedIconFormat`/`FixedIconFormatValidator` (31/07/2026)
- Removed `Entity\Media::validateFixedIconMimeType()`, replaced by that constraint (31/07/2026) [BC-Break]
- `Listener\VichImageResizeListener` now takes `Service\SvgRasterizer` as a third constructor argument (31/07/2026) [BC-Break]
- The `label.fixed_icon_invalid_format` translation now carries a `%formats%` parameter (en/fr/es) (31/07/2026) [BC-Break]
- `assets/js/video-iframe.js` no longer injects an iframe without a src (31/07/2026)
- `Listener\VichImageResizeListener` now decides on a file's content instead of its extension (31/07/2026)
- A raster favicon is now actually converted to a 48x48 `.ico`, its stored `.ico` name having excluded it until now (31/07/2026)
- UPGRADE.md's notes are now split per release, instead of one running `Unreleased` section (31/07/2026)

## v1.13.2

Exclude the generation skeletons from Codacy, which ignores the Finder

- Codacy now excludes `**/*.tpl.php`, its runs ignoring php-cs-fixer's Finder (30/07/2026)
- Added the Codacy grade badge to the README (30/07/2026)

## v1.13.1

Give the CI a root version, ^1 otherwise resolving an older release

- The `CI` workflow now sets `COMPOSER_ROOT_VERSION` to `1.x-dev` (30/07/2026)
- `actions/checkout` is now pinned to `v5` (30/07/2026)
- `shivammathur/setup-php` and `codacy-coverage-reporter-action` are now pinned to a commit SHA (30/07/2026)
- Removed the duplicate `.ui-icon-preview` rule, its `32px` measure folded into the first (30/07/2026)
- Removed the duplicate `.slider-item` rule, its `animation` folded into the first (30/07/2026)
- Removed the duplicate `.slider-freeflow .slider-item` rule, its `aspect-ratio` folded into the first (30/07/2026)
- Removed a dead `.portfolio-grid__project-body` padding, the rule right below it having always overridden it (30/07/2026)
- Added a `.stylelintrc.json`, read by Codacy in place of its default ruleset (30/07/2026)
- The asset tests now name the file they read `$script` instead of `$js` (30/07/2026)
- Every `sass/` file now ends with a newline (30/07/2026)
- The `v1.13` heading is now `v1.13.0`, matching the tag (30/07/2026)

## v1.13.0

Lock a component's own inline layout against the rules strong enough to write it away

- `php` is now required in `>=8.4` instead of `>=8.0` (30/07/2026) [BC-Break]
- The `symfony/*` requirements are now constrained to `^8.0` instead of `*` (30/07/2026) [BC-Break]
- `symfony/maker-bundle` is now constrained to `^1.67` instead of `*` (30/07/2026)
- The third-party requirements left in `*` are now bounded on their installed version (30/07/2026)
- The `c975l/*` requirements are now bounded on their major (30/07/2026)
- `Block::$user`, `Media::$user` and `VichMediaTrait::$user` are now typed `c975L\ConfigBundle\Contract\UserInterface` instead of `App\Entity\User` (30/07/2026) [BC-Break]
- `BlockUserListener` now assigns the logged-in user only when it implements `c975L\ConfigBundle\Contract\UserInterface` (30/07/2026)
- Removed the `Tests\Fixtures\AppUserStub` fixture, a stub of the interface replacing it (30/07/2026)
- Added `.codacy.yaml`, `phpcs.xml.dist` and `eslint.config.mjs` (30/07/2026)
- Applied PSR-12 to the codebase (30/07/2026)
- Added `.php-cs-fixer.dist.php`, applying the Symfony coding standards (30/07/2026)
- Added `phpstan.dist.neon`, running the static analysis at level 5 (30/07/2026)
- Added `phpstan-baseline.neon`, freezing the errors that predate the analysis (30/07/2026)
- Added the `CI` GitHub Actions workflow, running PSR-12, the static analysis, the tests and the coverage upload (30/07/2026)
- The local Codacy CLI now runs `eslint@9.39.5` (30/07/2026)
- Fixed a `slider` block hugging the left edge, the section margin reset added in v1.12.0 dropping the `auto` its own centered measure is laid out on (30/07/2026)
- Fixed a `freeflow` slider losing its full-bleed breakout the same way, its `margin-left` being zeroed too (30/07/2026)
- The section margin reset now names its kinds one by one and resets the block axis alone, in place of a `section` shorthand (30/07/2026)
- Fixed a `hero` with a background, and every colored flat, losing their full-bleed breakout to that same shorthand (30/07/2026)
- Added `SectionMarginResetTest` coverage checking the reset never writes the inline axis, and names every kind rendered as a `<section>` (30/07/2026)
- Added `ComponentCenteringTest`, reading the compiled stylesheet as the cascade does and failing on any rule strong enough to write the margin shorthand over a component's own centering (30/07/2026)
- It reads a negative `vw`/`%` margin as a layout to protect too, a breakout being lost to the same shorthand as a centering (30/07/2026)
- A component whose class or tag is built by a Twig expression is now resolved, the whole page-section family being invisible to a reading that only takes what is written out (30/07/2026)
- It skips what only looks like a collision: a component with no measure of its own, one laid out by a flex/grid container, and every rule nested in an `@media`/`@supports`/`@layer` (30/07/2026)
- Added `Testing\StylesheetCascade` and `Testing\ComponentCenteringAnalyzer`, that analysis extracted so a bundle depending on this one runs the same engine over its own sheet and this one's (30/07/2026)
- They ship in `src/`, a bundle's `tests/` being autoload-dev and never reaching its dependents, and are excluded from the service container (30/07/2026)
- A rule whose subject names no class only reaches a component through its tag, and is now read as a coincidence rather than a collision when scoped under a concrete styled ancestor - under nothing, or under `display: contents` wrappers, it still counts (30/07/2026)
- Added `Service\LayoutAuditor` and the `c975l:ui:layout-audit` command, measuring a rendered page's geometry for what no stylesheet test can see (30/07/2026)
- It reports a block breaking out of the viewport, a component that lost its centering, and an image blown up past the window (30/07/2026)
- The frame a block is measured against now includes the scrollbar, a full-bleed `100vw` section otherwise reading as an overflow on every run (30/07/2026)
- Centering is read from the stylesheet through the CSSOM, not from the element, a centering already lost computing to `0px` with nothing left to show it was ever meant to be centred (30/07/2026)
- The page is settled first - animations zeroed and scrolled through - the block animations otherwise parking half the blocks off-screen and reading as overflows (30/07/2026)
- Reports without failing unless `--strict` is passed: a headless browser is never deterministic enough to gate a push on (30/07/2026)
- A page that could not be measured never reads as a clean one (30/07/2026)
- Deliberately not a `HealthCheckProviderInterface`: those run from cron on the managed server, which has no browser and no way to install one (30/07/2026)
- Added `chrome-php/chrome` as a `require-dev` and a `suggest`, to be installed in the consuming site rather than here, the command running from that site's console (30/07/2026)
- Added `LayoutAuditCommandTest` and `LayoutAuditorTest`, both browser-free (30/07/2026)
- Added the `plain_text` filter, reducing editor HTML to the text a caption, an `aria-label` or a `<meta>` can hold (30/07/2026)
- It decodes the entities `striptags` leaves behind, a `&amp;` otherwise reaching the page as `&amp;amp;` once Twig had escaped it a second time (30/07/2026)
- The `Image:Link` component's fallback accessible name now reads that filter, in place of `striptags` (30/07/2026)
- Added `.text-hook--article`, the variant an `article` block's hook now wears, marked out by the accent color where the standalone block is marked out by its bar (30/07/2026)
- Added `--text-hook-article-color`/`-max-width`/`-margin`, a theme whose `--primary` is its text color needing the first one set (30/07/2026)
- An article's hook is now laid out on the article's own measure, its 62ch box having left a centered hook reading off-center (30/07/2026)
- Added `TextHookStyleTest`/`TextHookMarkupTest` coverage for the article variant (30/07/2026)
- `.slider`, `.slider-single` and `.image-compare` now read `--reading-max-width`, the measure SiteBundle lays body copy out on, in place of their own hardcoded 800px (30/07/2026) [BC-Break]
- Fixed all three sitting edge to edge on a viewport of 800px, a bare length outrunning the room it had (30/07/2026)
- Added `--slider-margin-block`/`--image-compare-margin-block`, the room kept above and below each (30/07/2026)
- A slider now keeps symmetric room, the former `1em auto 3em` leaving it all but flush against the block introducing it (30/07/2026)
- Added the `.readmore` wrapper to the `Text:Readmore` component, and the `text_readmore` block now sits on that same measure (30/07/2026)
- Fixed a `text_readmore` block running the whole page frame, ~160 characters to the line, the only body-copy block bounded by nothing at all (30/07/2026)
- The "read more" toggle now reads `--link-color`, its hover mixed out of that same color, in place of a hardcoded `#007bff`/`#0056b3` (30/07/2026) [BC-Break]
- Fixed it rendering Bootstrap blue on every theme, a `<label>` being out of reach of the `a` rules that color every other link (30/07/2026)
- Added `ReadmoreStyleTest` (30/07/2026)
- Added `--section-head-max-width`/`--hero-text-max-width`/`--hero-media-max-width`/`--cta-band-text-max-width`/`--alert-max-width`, five measures that were bare lengths (30/07/2026)
- Added `ReadingMeasureTest`, locking every column-wide rule to that one measure and every section measure to a token (30/07/2026)
- `Block::$user` and `Media::$user` are now typed on `c975L\ConfigBundle\Contract\UserInterface`, in place of the application's own `App\Entity\User` (30/07/2026) [BC-Break]
- Removed `tests/Fixtures/AppUserStub.php`, the stand-in that hard dependency needed (30/07/2026)
- Fixed the Trix toolbar aligning a block of another editor on the same page (30/07/2026)
- A block rendered outside an http request is no longer cached (30/07/2026)
- Fixed a template's request-dependent output being frozen into that entry and served to every visitor afterwards (30/07/2026)
- Fixed `StylesheetCascade::specificity()` over-counting an `:is()`/`:not()` holding a comma-separated list (30/07/2026)
- Added `StylesheetCascadeTest`, `ComponentCenteringAnalyzerTest` and `TrixEditorSelectionScopeTest` (30/07/2026)

## v1.12.2

Fix the guided save steps highlighting a button EasyAdmin never names

- Fixed the guided steps highlighting `.action-save`, EasyAdmin naming that button `action-saveAndReturn` (29/07/2026)
- Added `UiGuidedProjectProviderTest` coverage locking every `.action-*` highlight to a declared EasyAdmin action (29/07/2026)

## v1.12.1

Added the guided projects for the media, form and email screens

- Added `UiGuidedProjectProvider`, contributing this bundle's guided projects to the dashboard (29/07/2026)
- Added the "Téléverser une image", "Créer un formulaire" and "Personnaliser un e-mail" projects (29/07/2026)
- Added the `label.guided_project_ui_*`/`label.guided_step_ui_*` translations and their `description.` pairs (29/07/2026)
- Every `src/Contract/` interface now documents itself through a PHPDoc block, in place of a `//` comment (29/07/2026)
- Added the array shape each of them returns, previously described in prose (29/07/2026)
- `IconServiceInterface` and `PrivateFileResponseFactoryInterface` follow the same (29/07/2026)
- Added `UiGuidedProjectProviderTest` (29/07/2026)

## v1.12.0

- Constraint messages now live in the `validators` catalogue, the one the validator translates them in (29/07/2026)
- Fixed a `feature_bar` holding six entries, and a `hero` holding seven images, showing the editor a raw translation key (29/07/2026)
- The `password` controller's submit button now reads every field it watches, in place of the last one blurred (29/07/2026)
- Fixed a matching confirmation re-enabling submit while the password itself failed its pattern (29/07/2026)
- Hiding a revealed password now restores the `autocomplete` the form declared (29/07/2026)
- Fixed the show/hide eye clobbering the `new-password` a sign-up form sets against autofill (29/07/2026)
- Added the `text_hook` block, a lead-in paragraph set apart from the text it introduces (29/07/2026)
- Added `sass/_text-hook.scss` and the `Text:Hook` component, shared with an article's own hook phrase (29/07/2026)
- Added `--text-hook-size`/`-line-height`/`-max-width`/`-color`/`-margin-bottom` and the `--text-hook-bar-*` pair (29/07/2026)
- Condensed the multi-line comments of `src/`, `templates/` and `assets/` to one line each (29/07/2026)
- Added `sass/_forms.scss`, the form-control layer moved over from SiteBundle (29/07/2026)
- Every form token it reads now carries a neutral fallback, so the rules survive without SiteBundle (29/07/2026)
- Added `--form-width`, the measure every form is laid out on (29/07/2026)
- Added the `password` Stimulus controller, moved over from SiteBundle's `basic` one (29/07/2026)
- Its format and confirmation checks now read `data-password-pattern`/`data-password-confirm`/`data-password-message` in place of hardcoded field ids (29/07/2026)
- Added `assets/js/handlers.js`/`translations.js`, moved over from SiteBundle (29/07/2026)
- Added `public/icons/eye.svg`/`eye-slash.svg` (29/07/2026)
- Fixed the validation message being inserted inside the `.has-toggle` wrapper, pushing the eye icon down (29/07/2026)
- Added `sass/_tokens.scss`, the default value of every token this bundle reads but does not declare, in `@layer ui-defaults` (29/07/2026)
- Added `--input-background`, the surface of a field, in place of a hardcoded `transparent` (29/07/2026)
- Added `--input-valid-border-color`/`--input-invalid-border-color` and their `-shadow-` pair (29/07/2026)
- Added `--input-icon-filter`, applied to the icons the `password` controller injects (29/07/2026)
- A field now turns green or red once judged, through `:user-valid`/`:user-invalid` and the controller's `.success`/`.error` (29/07/2026)
- Fixed the show/hide eye rendering black on a dark theme, invisible against the field (29/07/2026)
- Moved `.height-100` to `.height-300` in from SiteBundle (29/07/2026)
- An invalid or valid field now also carries a ✗/✓ glyph, color alone failing WCAG 1.4.1 (29/07/2026)
- That glyph now hangs on the field's wrapper through `:has()`, in place of the field's own `background-image` (29/07/2026)
- Fixed the glyph disappearing from a field Chrome had autofilled (29/07/2026)
- Added `--input-glyph-offset`, the distance from the bottom of a field's wrapper to its glyph (29/07/2026)
- The validation state now draws a crisp 3px ring in place of the focus rules' 10px glow (29/07/2026)
- It transitions into that ring unless `prefers-reduced-motion` (29/07/2026)
- Added `--input-invalid-text-color`, the message under a field (29/07/2026)
- Fixed that message reading at 4.14:1 on a dark page, having taken the border color (29/07/2026)
- `sass/_forms.scss` now builds its five type-selector lists from one Sass list (29/07/2026)
- Moved `sass/_badges.scss`, `_blockquotes.scss`, `_alignments.scss`, `_colors.scss` and `_iframe.scss` in from SiteBundle (29/07/2026)
- Added `--alert-color`, the text of an alert, previously a hardcoded `#000` (29/07/2026)
- `.alert` now colors its descendants too (29/07/2026)
- Fixed an alert holding a `<p>` or a bolded word rendering near-white on its pale tint in dark mode (29/07/2026)
- Added `Service\CssVariableResolver` and the `resolve_css_variables` Twig filter, replacing every `var()` and `color-mix()` of an email by the value it resolves to (29/07/2026)
- Fixed emails reaching Gmail, Outlook and most mobile clients with their colored declarations dropped, none of them resolving a custom property (29/07/2026)
- Added `sass/emails.scss` and its `sass/emails/` partials, the shared email base every c975L bundle sending mail now renders against (29/07/2026)
- Added the `@c975LUiCss` Twig namespace, so a bundle's own email layout can `source()` the compiled `emails.min.css` (29/07/2026)
- Fixed `.alert`/`.alert-danger` rendering unstyled in emails, no bundle's email stylesheet ever carrying the rules the templates use (29/07/2026)
- Added `--input-margin-block`, the room kept above and below a field (29/07/2026)
- Fixed the validation ring being drawn over a field's own label (29/07/2026)
- A field the browser has autofilled is now judged too (29/07/2026)
- `StylesheetCacheWarmer` now strips the UTF-8 BOM Sass writes into a compressed file (29/07/2026)
- Fixed `bundles/build/site.css` dropping the rule following each concatenated stylesheet (29/07/2026)
- The compiled stylesheets are now built with `sass --no-charset`, so they carry no BOM to begin with (29/07/2026)
- The validation states now share one rule through `:is()`, a browser not knowing `:user-invalid` dropping a flat selector list whole (29/07/2026)
- The slider's credits/rights strip now reads `--button-background-primary-light` (29/07/2026)
- The slider's play/pause hover now reads `--button-background-primary-dark` (29/07/2026)
- Added `PasswordControllerAssetsTest`, `TokenDefaultsTest`, `TranslationsJsTest`, `StylesheetByteOrderMarkTest` and `EmailStylesheetTest` (29/07/2026)
- Added `ConstraintMessageCatalogueTest`, `CssVariableExtensionTest`, `TextHookStyleTest`, `TextHookMarkupTest` and `TextHookTypeTest` (29/07/2026)
- Added the `columnWidth` field to the `flex_column` block, sizing a column in twelfths of its row (28/07/2026)
- Added `--flex-columns-gap`/`--flex-columns-span`, the row gutter and the unit each column hands its share of back (28/07/2026)
- A `flex_columns` row's slots are now restricted to the `flex_column` kind (28/07/2026) [BC-Break]
- Added `BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT`/`MENU_CONTEXT`/`MENU_NAVBAR_CONTEXT` (28/07/2026)
- Added `BlockRegistry::EXCLUSIVE_CONTEXTS`, offering nothing but the kinds that opted into the context (28/07/2026)
- `BlockType` now puts a kind its context no longer offers back in the choices of the block already holding it (28/07/2026)
- Added `Service\BlockAnchorCollector`, every in-page anchor a set of blocks declares (28/07/2026)
- The `hero` block's primary call to action is now optional (28/07/2026)
- The `Hero` component drops a button missing its label or its url, and the whole row when neither is set (28/07/2026)
- Added `--section-wrap-max-width`/`--section-wrap-gutter`, the measure every section is laid out on (28/07/2026)
- `.section-wrap` now follows the page's own frame (`--body-max-width`), in place of its own 1312px (28/07/2026) [BC-Break]
- `.blocks > .cards` and a flat `.feature-bar`'s grid read that same measure (28/07/2026)
- Added `box-sizing: border-box` to `.section-wrap` (28/07/2026)
- Fixed a strip of page background showing between two consecutive colored flats (28/07/2026)
- A `feature_bar` entry is now centered, its figure sized and weighted as a title (28/07/2026)
- Fixed the slider's caption and play/pause button reading `--primary-light`/`--primary-dark`, two tokens declared nowhere (28/07/2026)
- Added `FlexColumnWidthTest`, `FlexColumnWidthMarkupTest`, `FlexColumnsSlotContextTest`, `HeroCtaTest`, `SectionWrapMeasureTest`, `SectionMarginResetTest`, `BlockAnchorCollectorTest` and `CssVariableResolverTest` (28/07/2026)

## v1.11.1

- Replaced ids by hash in translations (27/07/2026)

## v1.11.0

- Added `--section-flat-offset`/`--section-flat-width`/`--section-flat-margin-x`, the colored flats' full-bleed breakout (27/07/2026)
- `.hero--has-bg` reads the same three (27/07/2026)
- Added `--hero-title-size`/`-letter-spacing`/`-line-height` and `--hero-sub-size`/`--hero-sub-max-width` (27/07/2026)
- Added the `eyebrow` field to the `text_section` block (27/07/2026)
- The `Text:Section` component takes an `eyebrow` prop (27/07/2026)
- With no title, a `text_section`'s eyebrow becomes the section's `<h2>`, as the `Section:*` components (27/07/2026)
- `TextSectionType` now derives the anchor slug from the eyebrow when the block has no title (27/07/2026)
- Added the `background` field to the `hero`/`feature_bar`/`text_section` blocks, painting the section as a full-width colored flat - light grey, primary color or dark (27/07/2026)
- Added the `.section--bg-muted`/`--bg-primary`/`--bg-dark` variants to `sass/_page-sections.scss`, inverting the text, dividers, chips and buttons a flat holds (27/07/2026)
- Added `Form\Block\HasBackgroundFieldTrait`, the opt-in any other section kind uses to offer the same field (27/07/2026)
- The `Hero`/`Feature:Bar`/`Text:Section` components take a `background` prop (27/07/2026)
- `.hero--has-bg` now expresses its own inversion through the same `--section-*` properties (27/07/2026)
- Fixed a `feature_bar` holding fewer than five entries trailing as many empty columns to its right (27/07/2026)
- Fixed the divider hanging off the right edge of a three-entry `feature_bar` from 1025px up (27/07/2026)
- Added `SectionBackgroundTest`, locking the variant whitelist, the block adapters passing the value on, and every rule reading a `--section-*` property with its neutral fallback (27/07/2026)
- Added `sass/_rich-text.scss`, putting `color: inherit` back on `<b>`/`<strong>`/`<em>` and the other inline formatting tags (27/07/2026)
- Fixed a bolded word in a hero title turning black instead of keeping the color of the text around it (27/07/2026)
- Removed the `.slider-title a strong`/`.slider-text a strong` rules, the new base layer covering them (27/07/2026)
- Added `RichTextInheritColorTest`, locking those rules in the compiled stylesheets (27/07/2026)

## v1.10.2

- Fixed `.hero__media img` declaring no `height`, letting the intrinsic `height` attribute added in v1.10.1 override its `aspect-ratio` and blow up every hero (26/07/2026)
- Added `ImageAspectRatioHeightTest`, locking every `img` sass rule setting an `aspect-ratio` to also declare its `height` (26/07/2026)

## v1.10.1

- Added `Service\BlockCacheClearer`, invalidating the block render cache on `cache:clear` so a deployment shipping a changed template no longer serves the previous markup (26/07/2026)
- Added `BlockCacheClearerTest`/`BlockCacheClearerRegistrationTest`, locking the `kernel.cache_clearer` autoconfiguration (26/07/2026)
- Fixed the `videoIframe` controller loading YouTube without consent, querying `cookieConsent` where the banner registers as `cookie-consent` (26/07/2026)
- The video iframe is now injected only when its element nears the viewport, instead of on page load (26/07/2026)
- `controllers.js` now imports `blockEditOverlay`/`captcha`/`confetti`/`imageCompare`/`slider`/`videoIframe` dynamically, dropping their `modulepreload` from every public page (26/07/2026)
- Front-end lazy controllers are re-checked on `turbo:load` (26/07/2026)
- Added the `Hero` component's `width`/`height` options (26/07/2026)
- The `Hero` component's image now carries its intrinsic `width`/`height` and `fetchpriority="high"` (26/07/2026)
- The `Hero` component's slideshow images now carry their intrinsic `width`/`height` (26/07/2026)
- The `Portfolio:Grid` component's thumbnails now carry their intrinsic `width`/`height` and `loading="lazy"` (26/07/2026)
- The `document_download` block's thumbnail is now `loading="lazy"` (26/07/2026)
- The `collection_item` block's portfolio image now carries its intrinsic `width`/`height` and `loading="lazy"` (26/07/2026)
- The `card`/`collection_item` blocks now pass their media's intrinsic `width`/`height` to `Image:Link` (26/07/2026)
- Added `VideoIframeConsentSelectorTest`, locking the consent selector and the deferred injection (26/07/2026)
- Fixed `blocks/Hero.html.twig` raising a Twig `SyntaxError` (500 on every page carrying a hero block), a comment sitting between two attributes of the `<twig:...>` tag (26/07/2026)
- The honeypot field's decoy label is no longer handed to the translator (`translation_domain: false`), which reported it as a missing translation key on every public form (26/07/2026)

## v1.10

- Added `Service\MediaDimensionsFiller`, keeping a media's auto-detected size when both dimension inputs are submitted blank (26/07/2026)
- `MediaUploadType`'s and `MediaCrudController`'s constructors take a `MediaDimensionsFiller` (26/07/2026) [BC-Break]
- Added `Media::getIntrinsicWidth()`/`getIntrinsicHeight()`, returning the width/height only when it is a bare pixel count (26/07/2026)
- Replaced `karser/karser-recaptcha3-bundle` with `CaptchaVerifier`/`CaptchaType`/`Validator\Constraints\Captcha` and the `captcha` Stimulus controller (26/07/2026) [BC-Break]
- Google's `api.js` is now only fetched on the first interaction with the form (26/07/2026)
- The captcha token is now requested at submit time, instead of on page load (26/07/2026)
- Removed `ReCaptchaFactory`, `Recaptcha3TypeExtension`, `RecaptchaPass` and `CspListenerPass` (26/07/2026) [BC-Break]
- `FormSubmissionType`'s constructor takes a `CaptchaVerifier` instead of an optional `ContentSecurityPolicyListener` (26/07/2026) [BC-Break]
- `c975LUiBundle` now registers `form/captcha_theme.html.twig` as an app-wide form theme (26/07/2026)
- The captcha widget renders no inline `<script>`, so it needs no CSP nonce (26/07/2026)
- Added `CaptchaWidgetRenderTest`/`CaptchaControllerDataAttributesTest`, rendering the widget and locking its data-* attributes to the controller (26/07/2026)
- Added `c975LUiBundleTest`, locking the captcha form theme's app-wide registration (26/07/2026)
- Fixed Donovan's dashboard question box never sending its request, `_ai_assistant_widget.html.twig` writing `data-aiassistant-*` instead of `data-ai-assistant-*` (26/07/2026)
- Fixed the rephrase button the same way, `_ai_rephrase.html.twig` writing `data-airephrase-*` instead of `data-ai-rephrase-*` (26/07/2026)
- Added `tests/Assets/AiControllerDataAttributesTest`, checking each AI controller's templates write every `data-*` attribute its JS reads (26/07/2026)
- Added `title`/`description`/`class` to the `video` block's form, aligning it on the `video_iframe` block (26/07/2026)
- The `Video:Video` component now renders a `<figure>` with an optional title and description, same structure as `Video:Iframe` (26/07/2026)
- Added `title`/`description`/`class` to the `audio` block's form, until now field-less (26/07/2026)
- The `Audio:Audio` component now renders a `<figure>` with an optional title and description, and accepts a `class` (26/07/2026)
- `sass/_images.scss`'s `video-iframe-` figure rules are now shared with the `video-` and `audio-` prefixes (26/07/2026)
- The `Progress:Bar` component now applies its width through a `progress-bar--w*` class instead of an inline `<style>` element (26/07/2026)
- Added `.progress-bar--w0` to `--w100` to `sass/_progress.scss` (26/07/2026)
- The `Progress:Bar` component no longer generates a random `id` (26/07/2026)
- The `Section:Cards`/`Section:FlexColumns`/`Section:Features`/`Portfolio:Grid`/`Collection:Grid` components now render their eyebrow as the section's `<h2>` when no title is set (26/07/2026)
- The same components now render a `<div>` instead of a headingless `<section>` when they have neither eyebrow nor title (26/07/2026)
- Added `templates/components/Section/_head.html.twig`, shared by the three `Section:*` components (26/07/2026)
- The `Form:Form` component now renders a `<div>` instead of a headingless `<section>` (26/07/2026)
- The `Hero` component's background image is now a real `<img class="hero__bg">` instead of an inline `style` attribute (26/07/2026)
- Added the `hero` block's `titleLevel` field, rendering the title as `<h1>` or `<h2>` (26/07/2026)
- The `document_download` block's thumbnail no longer carries a trailing slash (26/07/2026)

## v1.9.19

- The `Image:Link` component's `aria-label` now falls back to the visible label, then to the image's `alt`, instead of the hardcoded "image" (26/07/2026)
- The `Image:Link` component no longer writes an `aria-label` on the `<span>` rendered when there is no url (26/07/2026)
- The `Text:Section` component now renders a `<div>` instead of a headingless `<section>` (26/07/2026)
- The `Text:Section` component now only writes its `id` when a slug is set (26/07/2026)
- The `Card` component now only writes its `id` and `data-animation` attributes when they are set (26/07/2026)
- `.visuallyhidden` now uses `clip-path: inset(50%)` instead of the deprecated `clip: rect()` (26/07/2026)
- Dropped `-ms-overflow-style` and `::-webkit-scrollbar` from `.slider-list`, `scrollbar-width` covering every current engine (26/07/2026)
- Dropped `-webkit-user-drag` from `.image-compare-img`, the images already carrying `draggable="false"` (26/07/2026)

## v1.9.18

- Added a `priority` option to the `Image`/`Icon`/`Link` components, rendering `loading="eager" fetchpriority="high"` instead of `loading="lazy"` (26/07/2026)
- Fixed `a.btn` overriding `.btn-secondary`'s and `.btn-link`'s own text color (26/07/2026)
- `canvas-confetti` is now served from the bundle instead of jsDelivr (26/07/2026)
- Added the `confetti` controller's `script` value, overriding the library's path (26/07/2026)
- Added `Service\ImageDimensionsReader`, reading an image file's pixel size (26/07/2026)
- `VichImageResizeListener` now fills `Media::$width`/`$height` on upload, from the stored file (26/07/2026)
- Added `c975l:ui:media-dimensions`, backfilling the `Media` rows that have no dimensions yet (26/07/2026)
- Added `MediaRepository::findWithoutDimensions()` (26/07/2026)
- The media form's width/height help text now says the values are auto-detected pixels, instead of suggesting `100px, 50%` (26/07/2026)
- The `Image`/`Link` components now keep `img-responsive` alongside explicit width/height, dropping it only for a height given without a width (26/07/2026)
- The `video` block now reads its file and format from an uploaded media instead of a `src`/`type` data field, breaking for blocks saved before (26/07/2026)
- The `video` block's cover image is now an uploaded `image/*` media instead of a `poster` path field (26/07/2026)
- The `video` block's `autoplay`/`muted`/`loop` checkboxes are now a single `options` multi-select (26/07/2026)
- The `audio` block now reads its format from the uploaded media instead of a `type` data field, breaking for blocks saved before (26/07/2026)
- The `video`/`audio` kinds now declare an explicit `media_types` list instead of the `video/*`/`audio/*` wildcard (26/07/2026)
- `MediaUploadType` now labels a `video` block's uploads by mimetype and hides the per-image display metadata (26/07/2026)
- `BlockFixtureMediaAttacher` now attaches a single video for a kind listing several video mimetypes (26/07/2026)
- `BlockFixtureMediaAttacher`'s placeholder images now carry a `mimeType` (26/07/2026)

## v1.9.17

- Fixed `.hero__media` collapsing to a tiny blob on narrow viewports (24/07/2026)

## v1.9.16

- Added `Twig\TrixExtension`'s `trix_inline` filter, stripping Trix's block-level wrapping `<div>`s so rich text can be used inside phrasing-only contexts like `<h1>` (24/07/2026)
- `Hero/Hero.html.twig`'s title now goes through `trix_inline` instead of raw output (24/07/2026)
- Fixed `Card`/`CollectionItem`/`Process/Steps` wrapping raw Trix content in a `<p>`, invalid since Trix already wraps it in its own block-level `<div>` (24/07/2026)
- `Feature/Bar.html.twig` now renders a `<div>` instead of a headingless `<section>` (24/07/2026)

## v1.9.15

- `text_section` now uses a real Media upload (`media_types: 'image/*'`) instead of a raw `image` URL field (24/07/2026)
- Added `Media::$importedThumbnailPath`, letting `VichPdfThumbnailListener` reuse a Sync import's pre-generated PDF thumbnail instead of re-running Ghostscript (24/07/2026)

## v1.9.14

- Fixed `VichImageResizeListener::processFixedIcon()` crashing content import when re-importing a site graphic whose stored file is already in its fixed-icon format (e.g. favicon.ico), now skips reprocessing instead (24/07/2026)
- Fixed `Media::getFixedIconSpec()` triggering a "null as array offset" deprecation for every upload of a role-less (block) media (24/07/2026)

## v1.9.13

- Fixed `VichPdfThumbnailListener` crashing content import when `exec()` is disabled (e.g. Infomaniak managed hosting), now skips the thumbnail instead (24/07/2026)

## v1.9.12

- Added `ImportmapProvider`, declaring `controllers-admin.js`/`controllers.js`'s importmap.php entries for ConfigBundle's `c975l:config:check-importmap` (24/07/2026)
- Fixed README's Stimulus controller registration instructions, stale since `controllers.js` became self-starting (24/07/2026)
- Fixed README referencing a nonexistent `admin.js` and misattributing `eaSortable`/the kind-switcher to `controllers.js` instead of `controllers-admin.js` (24/07/2026)

## v1.9.11

- The dashboard guided tour's "Vitrine des blocks" and "Donovan" links now have a description (24/07/2026)

## v1.9.10

- Added `BlockMoveController`/`BlockRelocator`, letting an editor drag an already-saved Block into a different collection (a container's slots, or back to top-level) instead of only reordering within one (23/07/2026)
- Added `HasBlocksInterface::detachBlock()`, breaking for direct implementers not using `HasBlocksTrait`, and `BlockOwnerResolverInterface`/`BlockOwnerRegistry`, supporting the above (23/07/2026)
- Renamed the `section_cards` block kind to `section_features` (23/07/2026)
- Added a `section_cards` container kind, stacking full `card` blocks with an eyebrow/title/anchor (23/07/2026)
- Added a "no-cookie" checkbox to `video_iframe`, rewriting the URL to `youtube-nocookie.com` on save (23/07/2026)
- Fixed `BlockRelocator` leaving position gaps in the source collection, risking a duplicate `position` on a later relocation into the same container (24/07/2026)
- `FlexColumnsType`/`SectionCardsType` now share `AbstractSectionHeadContainerType` instead of duplicating the same fields (24/07/2026)
- Fixed `video_iframe`'s width/height of `"0"` being silently reinterpreted as unset (24/07/2026)
- `BlockOwnerRegistry::find()` now throws instead of silently picking the first resolver when several claim the same owner type (24/07/2026)
- `section_cards`' slots field now has its own "Cards" label instead of sharing `flex_columns`' "Columns" one (24/07/2026)
- Fixed `video_iframe`'s per-instance sizing `<style>` element never being removed on disconnect (24/07/2026)
- Fixed `videoIframe`/`aiRephrase`/`aiAssistant` Stimulus controllers' `data-*` attributes not matching their camelCase identifiers, breaking every value/target binding (24/07/2026)
- `video_iframe`'s width/height now applied via a per-instance nonce'd `<style>` rule, since HTML attributes lose to the site's global `iframe` CSS rule (24/07/2026)
- `video_iframe`'s iframe now centers in its container instead of hugging the left edge (24/07/2026)
- Fixed `hero`'s stacked (mobile) layout not centering its text/media/CTA (24/07/2026)
- `Progress:Bar`'s width now renders through a nonce'd `<style>` element instead of an inline style stripped by CSP (24/07/2026)
- `Progress:Bar` now accepts an optional `id` for embedding several bars on one page (24/07/2026)

## v1.9.9

- Added optional `title`/`description` fields to `video_iframe` (23/07/2026)
- Fixed Trix editors staying hidden on collection items added dynamically via EasyAdmin (23/07/2026)
- `Section:Cards` now reuses the shared `Card:Card` component instead of bespoke CSS (23/07/2026)
- Added a category to the clear cache shortcut (23/07/2026)

## v1.9.8

- Added `Entity\Trait\VichMediaTrait`, shared fields/methods for a satellite bundle's own Vich-uploaded media entity (23/07/2026)
- Added `Listener\MediaFileRemoveListener`, deleting a media entity's underlying file on removal (23/07/2026)
- Added `Service\PrivateFileResponseFactory`, building a download response for a private file (23/07/2026)
- Fixed `ea.collection.item-added` dispatch missing `detail.newElement`, crashing EasyAdmin's own collection-item JS (23/07/2026)
- Centered `.alert` with a max-width and readable text/link color (23/07/2026)
- Enlarged `document_download`'s thumbnail to show actual page content instead of a cropped sliver (23/07/2026)
- `document_download` now renders one card per attached PDF, opened in a new tab instead of forced download (23/07/2026)
- Added a `document_download`-specific media help text distinguishing its one-card-per-file behaviour (23/07/2026)
- Block kind picker's category optgroups now follow a fixed display order instead of alphabetical (23/07/2026)
- Fixed `Collection/Grid`'s portfolio head not showing when only an eyebrow is set (23/07/2026)
- Fixed ghost buttons in `Collection/Grid`, `Portfolio/Grid` and `Hero` linking to nothing when no URL is set (23/07/2026)
- Fixed `Image/Link` and `Portfolio/Grid` project cards rendering a dead link instead of a plain tag when no URL is set (23/07/2026)
- Added a Donovan Q&A card to ConfigBundle's dashboard home (23/07/2026)

## v1.9.7

- Added `flex_columns`/`flex_column` container blocks, laying other blocks side by side or stacked (22/07/2026)
- Fixed the Media library index/edit screens not showing a PDF's `.webp` thumbnail (22/07/2026)
- Added a `Media::$name` field, slugified into the stored PDF filename instead of the default `block-{kind}-{id}` (22/07/2026)
- Expanded the explanatory text on the Form/Form field template/Email template/Media index and edit screens (22/07/2026)
- Removed the detail/view page on Form, Form field template and Email template (22/07/2026)
- Added a Cancel action on every create/edit screen (22/07/2026)
- Added a duplicate button to any plain nested collection item inside a block's data form, not just top-level blocks/media (22/07/2026)
- Fixed reCAPTCHA v3 config keys (site key, secret key, score threshold) missing their seed rows (22/07/2026)

## v1.9.6

- Added `Contract\FontProviderInterface`/`Registry\FontRegistry`/`Form\FontChoiceType`, letting a bundle offer its `@font-face` font-family names as a real `<select>` for ConfigBundle's `font`-kind configs (21/07/2026)
- Fixed `hero` block's title/subtitle not reliably inheriting typography through `display: contents` on some engines (21/07/2026)

## v1.9.5

- Added a `hero` block toggle showing its attached image as a full-width background instead of beside the text (21/07/2026)
- `hero` block's subtitle now goes through the Trix rich-text editor instead of a plain text field (21/07/2026)
- Restricted the Donovan Q&A endpoint/token config keys (21/07/2026)
- Relabeled Donovan config keys from "Donovan (Site)" to "Donovan (Q&A)" (21/07/2026)

## v1.9.4

- Added `Doctrine\VectorType`, mapping a PHP `float[]` to MariaDB's native `VECTOR(n)` column (11.7+) - a shared building block for a semantic cache, not used by anything in this bundle itself (21/07/2026)
- Added an interactive question to `c975l:ui:donovan-qa:create` ("add a semantic cache?", default no) - answering yes additionally generates an `Answer` entity/repository (exact-hash + `VEC_DISTANCE_COSINE()` semantic match), an `EmbeddingClient`, and a `Service` orchestrating cache-or-call, on top of the previously-generated exact-hash-free skeleton (21/07/2026)
- Documented the two-tier (exact-hash + semantic/embeddings) caching pattern 975l.com's own Donovan Q&A backend now uses, as a reference for a self-hosted dashboard-assistant backend past a handful of questions (see Readme "AI Assistant" > "Self-hosting your own backend") (21/07/2026)

## v1.9.3

- Added `Management\ProcedureProvider`, contributing this bundle's own admin procedures to ConfigBundle's `ProcedureBuilder` (21/07/2026)
- Added a "scrolled" navbar state, toggled on scroll for SiteBundle's `--navbar-*-scrolled`/`.menu.is-scrolled` (21/07/2026)
- Fixed slider caption links losing their white color to the global link-color rule (21/07/2026)

## v1.9.2

- Constrained `easycorp/easyadmin-bundle` composer requirement to `^5.1` (20/07/2026)
- Fixed `email-debug` config key missing its seed row, its debug-preview toggle had no way to be enabled from the Config UI (20/07/2026)
- Added `Contract\EmailLayoutProviderInterface`/`Registry\EmailLayoutRegistry`, letting a bundle wrap `EmailTemplateRenderer::render()`'s output in its own branded email layout (20/07/2026)
- Reworked email block templates to use CSS classes instead of hardcoded styling, so a registered `EmailLayoutProviderInterface` can apply real theme colors (20/07/2026)
- Fixed email `<table>` blocks inheriting unwanted spacing from a wrapping layout's own generic `table`/`td` CSS rules (20/07/2026)
- Added `DependencyInjection/Compiler/CspListenerPass`, fixing `FormSubmissionType`'s CSP nonce autowiring to null when `nelmio/security-bundle` is registered (20/07/2026)
- Added a "* Required field" note under a Form's submit button when it has a required field (20/07/2026)
- Fixed `.section-btn--primary`/`.section-btn--ghost`/`.section-btn--dark` hover state losing to the site's global link-hover rule (20/07/2026)
- Fixed a Trix-wrapped `<div>` breaking `hero__title`'s box model (20/07/2026)
- Removed sample placeholder text from `FormFieldTemplate` import defaults (20/07/2026)
- Added a 10-row default height to `textarea` Form fields (20/07/2026)
- Added real icon files to the `section_cards` block gallery fixture (20/07/2026)
- Trimmed several EN/ES/FR translation strings for brevity (20/07/2026)

## v1.9.1

- Fixed `FormSubmissionType`'s `password_repeated` fields only enforcing `NotBlank`, letting a Form (e.g. registration) accept an arbitrarily weak password - now also enforces `Length`/`PasswordStrength`/`NotCompromisedPassword`, same policy as `ChangePasswordFormType` (19/07/2026)

## v1.9

- Fixed `FormSubmissionType`'s password fields missing `autocomplete="new-password"`, letting browsers autofill an existing saved password onto e.g. the registration form (19/07/2026)
- Fixed `FormController::submit()` running `DnsEmail`'s DNS/MX lookup before the honeypot/rate-limiter check - `FormBotProtection::isSuspicious()` now reads the honeypot straight off the request, before `handleRequest()` (19/07/2026)
- Added `FormField::$url`, rendered as a link right after the field's label (e.g. a checkbox's "J'accepte les CGU (lire)") - see UPGRADE.md [DB-Migration] (19/07/2026)
- Added `Contract\RequiresAnonymousInterface`, letting a `FormActionInterface` provider hide its Form from an already-authenticated visitor behind an "already logged in" notice (19/07/2026)
- Added `Form::$enabled`, letting an admin pause a Form without unpublishing its Page - see UPGRADE.md [BC-Break] (19/07/2026)
- Added an "Edit" hover button on rendered blocks for `ROLE_EDITOR` users (19/07/2026)
- Added `Contract\BlockEditUrlProviderInterface`/`Registry\BlockEditUrlRegistry`, resolving a Block's edit URL across bundles (19/07/2026)
- Added `password`/`password_repeated`/`url`/`tel`/`number`/`date` field types to Forms (19/07/2026)
- Added `Validator\Constraints\DnsEmail`, checking a Form's email fields resolve to a real domain - see UPGRADE.md [BC-Break] (19/07/2026)
- `FormSubmissionType` now validates email fields' format too (19/07/2026)
- Fixed a required checkbox silently accepting an unchecked box (19/07/2026)
- Fixed `FormFieldNamer` renaming a restricted field's stable key on relabel (19/07/2026)
- Added `Entity/EmailTemplate`/`EmailBlock` and `Service/EmailTemplateRenderer`, an email-safe block-based email builder (19/07/2026)
- Added `Controller/Management/EmailTemplateCrudController` (19/07/2026)
- Added `EmailSendRequest::$html`/`EmailService` support for pre-rendered HTML bodies (19/07/2026)
- Added `SendEmailFormAction`'s `emailTemplate` config key (19/07/2026)
- Added `EmailTemplateRenderer::renderBody()`/`email_template_body()`, embedding an EmailTemplate in an app's own layout (19/07/2026)
- `EmailTemplateRenderer` resolves relative `TYPE_IMAGE` urls against `site-url` (19/07/2026)
- Added `Entity/FormFieldTemplate`/`Controller/Management/FormFieldTemplateCrudController`, a reusable field catalog (19/07/2026)
- Added `c975l:ui:form-field-template:import-defaults` command (19/07/2026)
- Fixed `FormFieldTemplateCrudController`'s "type" select showing untranslated keys (19/07/2026)
- Added `Twig\ConfigLinkExtension`'s `config_edit_url()` and a GDPR note on Form/EmailTemplate CRUD pages (19/07/2026)
- Added a toolbar link from `FormCrudController` to the FormFieldTemplate catalog (19/07/2026)
- Fixed the field-template picker showing a template's internal key instead of its label (19/07/2026)
- Fixed `EmailTemplateRenderer` mishandling protocol-relative image urls (19/07/2026)
- Added a "(disabled)" suffix on a paused Form's label in the `form` block picker (19/07/2026)
- Fixed a hardcoded French placeholder in the field-template picker, now translated (19/07/2026)
- Suppressed `ConfigLinkExtension`/`AiAssistantController`'s duplicated config-edit-url logic, now shared via `Service/ConfigEditUrlResolver` (19/07/2026)
- Suppressed `BlockType`/`FormFieldType`/`EmailBlockType`'s duplicated id-reconciliation listener, now shared via `CollectionReconciler::addIdField()` (19/07/2026)
- Added `FormField::$url`, an optional link appended to a field's label (e.g. a checkbox's "Terms of use") (19/07/2026) [Needs db update]

## v1.8.1

- Merged branch commit/push problem (19/07/2026)

## v1.8

- Added `Form`/`FormField` entities (`site_form`/`site_form_field` tables) and `FormFieldType`, a shared sortable field-collection system (18/07/2026)
- Added `Form::$action` and `FormActionInterface`/`FormActionRegistry` (18/07/2026)
- Added a generic `form` Block kind (`FormController`/`FormSubmissionType`/`FormPickerType`) (18/07/2026)
- Added `FormField::$restricted`, locking a form-owning bundle's core fields (18/07/2026)
- Added `Service/FormBotProtection`/`RateLimiterGuard`/`ReCaptchaFactory` (reCAPTCHA v3), moved out of ContactFormBundle - see UPGRADE.md [BC-Break] (19/07/2026)
- Added `Service/EmailService`/`SendEmailFormAction`, moved out of ContactFormBundle - see UPGRADE.md [BC-Break] (19/07/2026)
- Added `Form::$restricted` (19/07/2026)
- Added `Controller/Management/FormCrudController` (19/07/2026)
- Added shared bot/recaptcha/rate-limiter protection and a "receive a copy" option to the `form` Block - see UPGRADE.md [BC-Break] (19/07/2026)
- Fixed `FormSubmissionType`'s recaptcha field missing a CSP nonce under strict CSP (19/07/2026)
- Added `Contract/DebugPreviewCapableInterface`, showing a debug email preview on `FormController` (19/07/2026)
- Added `Service/FormPrefillHelper` - see UPGRADE.md [BC-Break] (19/07/2026)
- Added a `form` Block showcase fixture, replacing ContactFormBundle's own (19/07/2026)
- Changed the sidebar's block showcase link to `https://975l.com/pages/blocks` (19/07/2026)
- Added an "AI Assistant" back-office page (dashboard Q&A + text rephrase), optional and config-driven (19/07/2026)
- Added `c975l:ui:donovan-qa:create` maker command (19/07/2026)
- Added monthly AI rephrase spend tracking and dashboard alerts (19/07/2026)
- Changed `site_media()` to memoize per-request (19/07/2026)
- Suppressed `CollectionExtension`'s eager DB-touching constructor, split into `CollectionRuntime` (19/07/2026)
- Fixed `FormController` open redirect via unvalidated Referer header (19/07/2026)
- Fixed `EmailService`'s debug preview banner corrupting on some subjects (19/07/2026)
- Fixed `SendEmailFormAction` dropping a value when two fields share the same label (19/07/2026)
- Fixed `ReCaptchaFactory` ignoring a configured score threshold of 0 (19/07/2026)
- Fixed `FormController`'s rate limiter sharing one bucket when the client IP can't be resolved (19/07/2026)
- Changed `RateLimiterGuard`/`FormController` to type against `RateLimiterFactoryInterface` (19/07/2026)
- Fixed the AI Assistant page's centralized-backend override ordering (19/07/2026)

## v1.7.2

- Added an optional Anchor field to `hero`/`feature_bar`/`section_cards`/`expertise_banner`/`process_steps`/`portfolio_grid`/`cta_band`/`collection`, for in-page navigation (17/07/2026)
- Added `collection_item` block kind, replacing the `card` kind reuse for `collection` items (17/07/2026)
- Added a detail-page link on `collection` item titles, when the item's source provides a slug (17/07/2026)
- Added a `variant` field to `collection`, switching every item's presentation between `card` and `portfolio_grid` styles (17/07/2026)
- Added a pure-CSS crossfade slideshow to `hero` when several media are attached (17/07/2026)
- Changed `button`/`card`/`cta_band`/`hero`/`portfolio_grid` url fields from `UrlType` to `TextType`, allowing in-page anchor/relative links (17/07/2026)
- Changed `hero` to store a wider image (1200px) to avoid pixelating on retina displays (17/07/2026)
- Changed `hero`/`portfolio_grid` images to `object-fit: contain` instead of `cover`, avoiding cropping (17/07/2026)
- Changed `portfolio_grid` project links to open in a new tab (17/07/2026)
- Added consistent top padding across stacked "Page sections" blocks (17/07/2026)
- Fixed `cta_band` text color on dark background, nested-`<p>` double-wrapping same as `expertise_banner` (17/07/2026)
- Changed `portfolio_grid` gallery fixture to generic placeholder copy, not real client names (17/07/2026)
- Removed `GalleryShowcaseProvider` (17/07/2026)
- Added a cap of 6 attached media on `hero`, matching its crossfade slideshow's own CSS limit (17/07/2026)
- Fixed `portfolio_grid` project links opening a blank new tab when the project has no real url (17/07/2026)
- Added `hero` to the gallery's multi-image fixtures, so its slideshow can be previewed (17/07/2026)
- Suppressed the anchor-id composition duplicated across 8 block adapter templates, now computed once by `BlockExtension` (17/07/2026)
- Suppressed `article`/`text_section`'s own hand-rolled slug logic, now sharing `BlockAnchorSlugger` (17/07/2026)

## v1.7.1

- Corrected link <https://975l.com/pages/blocks> (17/07/2026)

## v1.7

- Changed `MediaCrudController` index action buttons to icon-only with hover title (16/07/2026)
- Added `c975l:ui:block:create` maker command (16/07/2026)
- Fixed `articles_slider` cache not invalidating on referenced `article` block changes (16/07/2026)
- Added `BlockCacheTagProviderInterface` (16/07/2026)
- Added `collection` block (16/07/2026)
- Added `Card.html.twig` `imageUrl` fallback for non-Media images (16/07/2026)
- Added `document_download` block (16/07/2026)
- Added optional `label` field to `progress_bar` (16/07/2026)
- Fixed `image_compare`/`slider` under CSP `style-src` (16/07/2026)
- Changed `video_iframe` to gate behind cookie consent and use `youtube-nocookie.com` (16/07/2026) [BC-Break]
- Fixed `expertise_banner` text color on dark background (16/07/2026)
- Removed the EasyAdmin block gallery, superseded by 975l.com's showcase (16/07/2026) [BC-Break]
- Added sidebar link to `https://975l.com/pages/blocks` (16/07/2026)

## v1.6

- Added `image_compare` block: draggable before/after image comparison slider (15/07/2026)
- Added `StylesheetCacheWarmer`: compiles registered stylesheets to `bundles/build/site.css`/`admin.css` outside `kernel.debug` (15/07/2026)
- Added `BlockRegistry::getBundle()`/`groupedByBundle()`, grouping block kinds by originating bundle (15/07/2026)
- Changed block gallery access role from `ROLE_SUPER_ADMIN` to `ROLE_EDITOR` (15/07/2026)
- Changed Media library `New` action to `ROLE_SUPER_ADMIN`, bumped max upload size to 100M (15/07/2026)
- Added `BlockUserListener::preUpdate()`, tracking the last editor instead of only the creator (15/07/2026)
- Fixed `Media::isOgImage()` false-positive on any role-less/block-less Media (15/07/2026)
- Fixed freeflow slider autoplay scrolling the whole page instead of just its own slide list (15/07/2026)
- Removed custom composer `vendor-dir`, back to standard `vendor/` (15/07/2026)
- Fixed the compiled `bundles/build/site.css` URL not busting caches across deploys, now versioned by the compiled file's own mtime (15/07/2026)
- Suppressed duplication between `BlockRegistry::groupedByCategory()`/`groupedByBundle()`, now sharing a private `groupBy()` helper (15/07/2026)
- Added `BlockIdGenerator`, shared by `SliderType`/`ImageCompareType` instead of each duplicating the same id-generation line (15/07/2026)
- Changed block gallery to a full-width single-column layout with a jump-to table of contents, instead of a grid of fixed-width cards (15/07/2026)
- Added `BlockFixtureMediaAttacher`, a shared service attaching placeholder media to fixture blocks - reused by any consuming app's own block showcase, not just the gallery (15/07/2026)
- Changed block gallery placeholder media to a rotating pool of real photos/video/audio, instead of a single generic image (15/07/2026)
- Changed slider `freeflow` gallery preview to 5 images instead of 3 (15/07/2026)
- Fixed `video_iframe` gallery preview autoplaying with sound, now muted (15/07/2026)
- Fixed `expertise_banner` text color turning black on its dark background for unclassed rich-text content (15/07/2026)

## v1.5.4

- Added test to trigger deprecations (14/07/2026)

## v1.5.3

- Suppressed DependencyInjection/Configuration.php as not needed (14/07/2026)

## v1.5.2

- Added `hero`, `feature_bar`, `section_cards`, `expertise_banner`, `process_steps`, `portfolio_grid`, `cta_band` page section blocks (14/07/2026)
- Added `Media::$url`/`Media::$description` columns, used by `portfolio_grid` (14/07/2026)

## v1.5.1

- Corrected slider default layout (13/07/2026)

## v1.5

- Added help text to the Media library CRUD index (13/07/2026)
- Fixed `render_block()` caching every never-persisted block (id `null`) under the same cache key (13/07/2026)
- Fixed `.btn-success`/`.btn-danger`/`.btn-link` styles (13/07/2026)
- Added block gallery (`/management/ui/block/gallery`), previewing every pickable block kind with sample data (13/07/2026)
- Added `BlockFixtureProviderInterface`: lets a bundle supply sample data for its own kinds in the block gallery (13/07/2026)
- Added `GalleryShowcaseProviderInterface`: lets a bundle show non-block content in the block gallery (13/07/2026)
- Added `GalleryShowcaseProviderInterface`'s `category` override, for a kind-less showcase that still belongs next to a related category (13/07/2026)
- Added `contexts` block tag flag, restricting a kind to specific `BlockType` usages (e.g. `menu`) (13/07/2026)
- Added multi upload files feature (13/07/2026)
- Added Block BannerTitle (13/07/2026)
- Added `media_required` block tag flag, rejecting a block with no attached media at save time (13/07/2026)
- Added ability to add videos to the slider (13/07/2026)
- Added freeflow parameter for Slider (13/07/2026)

## v1.4.13

- Corrected sass for slider withu unique slide (13/07/2026)
- Added Dashoard's shortcut (13/07/2026)

## v1.4.12

- Added invalidate cache for blocks (13/07/2026)

## v1.4.11

- Moved tests to the right place (13/07/2026)

## v1.4.10

- Corrected What's new date (12/07/2026)
- Added tests (13/07/2026)
- Modified media library functionalities (12/07/2026)

## v1.4.9

- Added pickable on services.yaml (12/07/2026)
- Added server cache on Blocks (12/07/2026)
- Added animations on blocks (12/07/2026) [DB-Migration]
- Re-numbered xlf files (12/07/2026)

## v1.4.8

- Updated What's new (11/07/2026)
- Updated iconPicker (11/07/2026)

## v1.4.7

- Corrected Favicon/AplleTouchIcon to disallow svg format (10/07/2026)
- Added isPickable on Blocks (10/07/2026)
- Modified iconPicker (11/07/2026)

## v1.4.6

- Modified Block Image (09/07/2026)
- Added icon-picker.js (09/07/2026)
- Renamed css classes img-xxx to .width-xxx (09/07/2026)
- Re-ordered xlf files (10/07/2026)
- Added hook to article (10/07/2026)
- Added trix tools to center text (10/07/2026)
- Added media to Card Block (10/07/2026)
- Added resize/namig for favicon.ico and apple-touch-icon.png (10/07/2026)

## v1.4.5

- Added automatic CSS injection for EasyAdmin management pages (08/07/2026)
- Added possibility to upload user defined error images (08/07/2026)

## v1.4.4.1

- Fixed CompilerPass (05/07/2026)

## v1.4.4

- Added a cross-bundle Media Library in EasyAdmin: browse every `Media` regardless of how it's attached (Block, Page og-image, site-wide role...), see where it's used via a new `MediaUsageProviderInterface` extension point, and edit its metadata (05/07/2026)
- Restyled the Slider navigation dots (ring style with an `active` state) and fixed slide transitions extending the page's scrollable area by clipping `.slider` overflow (05/07/2026)

## v1.4.3

- Added duplication of Media (04/07/2026)
- Added duplication of Block (04/07/2026)
- Added a What's new file that will appear on main dashboard + menu (04/07/2026)
- Added live preview of a newly picked image in EasyAdmin, before saving (05/07/2026)
- Added site-wide media roles (favicon, apple-touch-icon, og-image, logo) on `Media`, retrievable via the `site_media()` Twig function (05/07/2026) [Needs db update]
- Added touch gestures (swipe to navigate, press-and-hold to pause) to the Slider block (05/07/2026)
- Moved Menu/MenuItem + sass related to c975L/SiteBundle (05/07/2026)

## v1.4.2

- Added auto-scroll/focus to the newly added block row in the "blocks" collection (04/07/2026)
- Added an optional translated `description`, shown under the label in the block kind picker (04/07/2026)
- Added BoolExtension to manage boolean values in twig (04/07/2026)
- Added description for Blocks (04/07/2026)
- Corrected deletion of physical file when deleting media in Block (04/07/2026)

## v1.4.1

- Fixed media files (image, etc.) not being saved when picking a block kind with media on a form not yet multipart (04/07/2026)
- Fixed Slider display for title and text (04/07/2026)
- Added possibility to use prop message for Alert (04/07/2026)

## v1.4

- Taken sass from c975L/SiteBundle (01/07/2026)
- Separated Blocks system from components as to be used in other bundles (01/07/2026)
- Corrected bug when adding more than one block (02/07/2026)
- Added kind of autoload for js controllers (02/07/2026)
- Added field on Media entity (02/07/2026) [Needs db update]
- Restored per-slide `credits`/`rightsReserved` on Slider (02/07/2026)
- Added a display `ratio` choice (free or fixed) on the Slider block (02/07/2026)
- Added translation for blocks label and category, using bundle defined translation domain (04/07/2026)

## v1.3.1

- Converted blocks.js to Stimulus controller (28/06/2026)

## v1.3

- Added TrixEditor for FormType (27/06/2026)
- Added animations from c975L/SiteBundle (27/06/2026)
- Removed dependency on page for blocks (27/06/2026)
- Transformed block.js in a js module (27/06/2026)
- Added a system to autoload CSS files from bundles (27/06/2026)

## v1.2

- Suppressed Stimulus Component (27/06/2026)
- Added UI related files from c975L/SiteBundle (27/06/2026)

## v1.1

- Added lots of things... (26/06/2026)

## v1.0

- Added native blocks and system to manage them (25/06/2026)

## v0.1.1

- Updated composer.json (24/06/2026)

## v0.1

- Creation of bundle (24/06/2026)
