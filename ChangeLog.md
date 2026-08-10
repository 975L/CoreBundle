# ChangeLog

## v1.7.0

The layout nonces style-src, and every inline style gives way

### ConfigBundle

- The maintenance page nonces its `<style>`, under a guard for a site with no `csp:` section (11/08/2026)
- The failed-message table truncates its cells by class instead of `style=""` (11/08/2026)
- Added `.failed-message-cell`, `.failed-message-cell-wide` and `.failed-message-cell-group` (11/08/2026)
- Added `MaintenancePageStyleTest` and `FailedMessagesCellWidthTest` (11/08/2026)

### UiBundle

- `layout.html.twig` calls `csp_nonce('style')` on every page, so `style-src` is nonced uniformly (11/08/2026) [BC-Break]
- `Banner:Title` writes its image and its height into a nonce'd `<style>` addressing its own id (11/08/2026)
- Those values are escaped for CSS rather than for HTML, an entity reaching a stylesheet undecoded (11/08/2026)
- `block-edit-overlay.js` writes its measured coordinates into a nonce'd `<style>` (11/08/2026)
- That sheet and its rule are dropped on disconnect, so a reconnect builds its own (11/08/2026)
- `block-toolbar.js` places its buttons by class instead of an inline `order` (11/08/2026)
- `ea-sortable.js` marks a dragged row, a grab zone and a hidden delete button by class (11/08/2026)
- `pointer-sort.js` carries its `touch-action` and its hit-testing opt-out by class (11/08/2026)
- `media-preview.js` and `form-field-template.js` drop their inline styling for a class (11/08/2026)
- `legal_model_customize.html.twig` spaces its action row by class (11/08/2026)
- Added `sass/management/_form-fields.scss` and `sass/management/_legal-model.scss` (11/08/2026)
- Added `.ui-field-locked`, the shared rule a consumer bundle's locked field wears (11/08/2026)
- The rules those scripts used to write moved into `sass/management/_block-collection.scss` (11/08/2026)
- The coarse-pointer handle drops its `!important`, the padding it widens being a class now (11/08/2026)
- Added `NoncedStyleSrcTest`, `BannerTitleStyleTest` and `BlockToolbarOrderTest` (11/08/2026)
- `MinimalLayoutTest` covers `style-src` being nonced on every page (11/08/2026)
- `PointerSortTest` reads its classes off the compiled stylesheets (11/08/2026)
- README documents the rule and the two forms a nonce authorizes (11/08/2026)
- The cookie banner's link to the `video_iframe` section points at a heading that exists (11/08/2026)
- UPGRADE.md documents what a site's own templates and scripts have to change (11/08/2026)

## v1.6.1

A config file left unloaded now fails the run

### ConfigBundle

- `c975l:config:load-all` exits with a failure code when a config file could not be loaded (10/08/2026)
- Every file is still attempted, the failure being reported once at the end with how many are missing (10/08/2026)
- `ConfigLoadAllCommandTest` now covers the failing exit code, all files failing and one among several (10/08/2026)
- README documents what an unloaded file does to the run (10/08/2026)

### UiBundle

- Added `--form-label-color`, the ink of a form label, defaulting to `--black` (10/08/2026)
- A focused field keeps `--form-input-color` instead of switching to `--black` (10/08/2026)
- The scaffolded `ui.css` offers the new token (10/08/2026)
- Added `FormInkTest`, locking both inks to their own token in the compiled stylesheets (10/08/2026)
- README documents the label token and the focus ink (10/08/2026)

## v1.6.0

Three new block kinds, and a hero opening on a video

### ConfigBundle

- Added the `choice` config kind, its value picked from a `<select>` over a fixed list rather than typed (10/08/2026)
- Added the `choices` column on `site_config`, declared per entry in `configs.json` and re-synced by `c975l:config:load-all` (10/08/2026)
- A `choice` value off its entry's list is now rejected on save and by `c975l:config:set` (10/08/2026)
- A stored value the list no longer offers stays selectable, so the entry can still be opened and fixed (10/08/2026)
- `choices` travels with the SQL and content exports (10/08/2026)
- Added the `label.invalid_choice` validator translations (10/08/2026)
- `display-made-by` and `display-hosted-by` are now `choice` configs: `none`, `logo`, `name` or `logo-name` (10/08/2026) [BC-Break]
- Added `site-made-by-name` and `site-hosted-by-name`, the text half of a credit whose logo and url were already there (10/08/2026)
- `site-hosted-by-url`, `site-hosted-by-logo`, `display-made-by` and `display-hosted-by` are declared here now, moved from SiteBundle (10/08/2026) [BC-Break]
- Added `Twig\CreditsExtension` and its `credits_mode()` function, the one place a credit's mode is read (10/08/2026)
- A row still holding the `true`/`false` of the bool era reads as `logo`/`none`, no value being ever rewritten (10/08/2026)
- Added `CreditsExtensionTest` (10/08/2026)
- UPGRADE.md documents the moved keys, the four modes and the values to re-pick (10/08/2026)
- `DevProfileAnalyzer::MAX_DUPLICATE_QUERIES` raised from 2 to 6, a block-composed page repeating a query shape by construction (10/08/2026)
- The n+1 offence now reads "repeats of a same SQL", the repeats being grouped with their parameters left out (10/08/2026)
- README documents the `choice` kind, its `choices` key and the raised duplicate threshold (10/08/2026)
- UPGRADE.md documents the new column and the three entries becoming `choice` (10/08/2026)
- Added the `choice` cases to `ConfigSetCommandTest` and `ConfigImportProviderTest` (10/08/2026)

### UiBundle

- Added the `block_group` block kind, a chrome-less container laying its slots out as a row or as a stack (10/08/2026)
- Added `BlockGroupType`, `blocks/BlockGroup.html.twig` and `sass/_blocks-group.scss` (10/08/2026)
- Its slots take the default `BlockRegistry::SLOT_CONTEXT`, so a group takes every pickable kind and never another group (10/08/2026)
- `BlockRegistry::isAllowedInContext()` reads the slot contexts off the registered containers instead of a fixed pair, so a satellite bundle's own `slot_context` gets the same depth guard (10/08/2026)
- Added `--blocks-group-gap`, offered by the scaffolded `ui.css` (10/08/2026)
- Added `BlockGroupMarkupTest` and `BlockGroupContainerTest` (10/08/2026)
- README documents the group, its layout fields and what it is for in a footer (10/08/2026)
- Added the `video_grid` block kind, a section head over a grid of whole nested `video_iframe`/`video` blocks (10/08/2026)
- Added `VideoGridType`, `blocks/VideoGrid.html.twig` and `components/Video/Grid.html.twig`, sharing `portfolio_grid`'s head and grid rules (10/08/2026)
- A `video_iframe` now takes an optional poster image, its single media (10/08/2026)
- Added `VideoPlatform::posterUrls()`, the stills a platform serves for a video id - YouTube only (10/08/2026)
- Added `VideoPosterImporter`, copying a platform's still into the site's own files rather than hotlinking it (10/08/2026)
- The import is a one-shot checkbox, cleared once done, so a still can be replaced by hand or refreshed by ticking again (10/08/2026)
- A `video_iframe` carrying a poster now waits for a click before creating its iframe, instead of loading it on approach (10/08/2026)
- Consent still comes first on a poster: the prompt sits over the still while it is undecided, never a bare play button (10/08/2026)
- Added `VideoGridTypeTest`, `VideoPosterImporterTest` and `posterUrls()` cases to `VideoPlatformTest` (10/08/2026)
- Added `VideoIframePosterTest`, and the click-to-play cases to `VideoIframeConsentSelectorTest` (10/08/2026)
- Added the poster context cases to `MediaUploadTypeTest`, `VideoIframeTypeTest` and `BlockTypeTest` (10/08/2026)
- `theme-mode`, `ui-watermark-position` and `ui-ai-assistant-rephrase-provider` are now `choice` configs, each declaring the values it accepts (10/08/2026)
- Added the `flip_card` block kind, a two-sided card turned by a button (10/08/2026)
- Added `FlipCardType`, `blocks/FlipCard.html.twig`, `components/FlipCard/FlipCard.html.twig` and `sass/_flip-card.scss` (10/08/2026)
- Added `assets/js/flip-card.js`, registered as the lazy `flipCard` front controller (10/08/2026)
- The face turned away is marked `inert`, and focus follows the face that just came up (10/08/2026)
- The fold and its buttons only exist once the controller ran, both faces staying readable without it (10/08/2026)
- A flip card now sways under the pointer, running the catalogue's own `rotateY5deg` keyframes (10/08/2026)
- A flip card now takes a ratio, the slider's own list, held as a floor under its shape rather than as a crop (10/08/2026)
- The turn and the sway are both dropped under `prefers-reduced-motion` (10/08/2026)
- A `flip_card` now joins the same auto-wrapped `.cards` row as a plain `card` (10/08/2026)
- Added `FlipCardTypeTest` and `FlipCardAccessibilityTest` (10/08/2026)
- A `hero` now takes a background video (`video/mp4`, `video/webm`, `video/ogg`), told from its images by mimetype (10/08/2026)
- An attached video turns the hero's background mode on by itself, whatever the toggle says (10/08/2026)
- Added `assets/js/hero-video.js`, registered as the lazy `heroVideo` front controller (10/08/2026)
- The video carries no `autoplay` attribute, the controller playing it and pausing it under `prefers-reduced-motion` (10/08/2026)
- An image uploaded beside the video is painted under it, as the section's LCP element (10/08/2026)
- A hero opening on a video takes `--hero-video-min-height` (70vh) of room, its text centered in it (10/08/2026)
- A hero title is now optional, and a hero holding no text at all prints no text block (10/08/2026)
- A hero background video declared without a `videoType` now plays, instead of being skipped over an empty `type` attribute (10/08/2026)
- Added the `video` hero variant to the block showcase, its placeholder video attached by `BlockFixtureMediaAttacher` (10/08/2026)
- Added `HeroVideoBackgroundTest`, `HeroVideoMotionTest` and `HeroMediaTypesTest` (10/08/2026)
- A `feature_bar` now takes an optional eyebrow and title (10/08/2026)
- A `feature_bar` renders as a `<section>` once a head is typed on it, a `<div>` otherwise (10/08/2026)
- A `feature_bar` zeroes the bottom margin its `<section>` shape earns from SiteBundle (10/08/2026)
- The head of a section with nothing under it no longer trails its bottom margin, whichever of its two lines is the last (10/08/2026)
- A section's eyebrow and title now state their own alignment, instead of being centered by the theme's heading rule (10/08/2026)
- Every block title now reads the same family, color and alignment: expertise banner, process step, portfolio project, video, audio, card band, banner and slider caption (10/08/2026)
- A block meaning to be centered - hero, cta_band, banner_title - now says so on its own container alone (10/08/2026)
- A legal document's headings now sit on the very measure their own copy is laid out on (10/08/2026)
- A section's title now starts one step in on its eyebrow, `--section-head-indent` (10/08/2026)
- Added `FeatureBarHeadTest` and `SectionHeadAlignmentTest` (10/08/2026)
- `SectionMarginResetTest` now reads a kind's own block margin off both its edges (10/08/2026)
- A `flex_columns` row now picks how its columns sit vertically - top, middle or bottom (10/08/2026)
- Added the `label.vertical_align` translations in the three locales (10/08/2026)
- A theme font-family is now quoted in `site-theme.css`, a name carrying a digit no longer being an invalid `<custom-ident>` (10/08/2026)
- A `text_section` now takes a presentation, `normal` or `secondary`, the quieter one for a section standing beside a louder one (10/08/2026)
- Added `--text-section-secondary-size`, `--text-section-secondary-line-height` and `--text-section-secondary-color`, offered by the scaffolded `ui.css` (10/08/2026)
- Added the `label.text_tone` translations in the three locales (10/08/2026)
- Added `TextSectionToneTest` (10/08/2026)
- Block text set in the body font is now sized in `em`, so it follows SiteBundle's `--font-size-body` (10/08/2026)
- A title and an eyebrow keep their own px lengths, each multiplied by `--font-size-title-scale`, resp. `--font-size-eyebrow-scale` (10/08/2026)
- `--hero-sub-size` now falls back to `1.1875em` instead of `19px` (10/08/2026)
- Added `BlockTextScaleTest` (10/08/2026)
- A refused block move now opens a modal carrying the server's reason, instead of a native `alert()` (10/08/2026)
- Added `assets/js/admin-modal.js`, exporting `showAdminMessage()` for any admin script (10/08/2026)
- `BlockMoveController` answers a translated message for a kind the target container does not take (10/08/2026)
- `BlockMoveRowAttrBuilder` adds the `data-block-move-close-label` the modal labels its button with (10/08/2026)
- Added the `action.close` and `flash.block_move_kind_not_allowed` translations (10/08/2026)
- Added `AdminModalTest` (10/08/2026)
- A container kind is now only offered in the `menu` context if it declared that context itself (10/08/2026)
- README documents the two new kinds, the hero's background video, the poster import and the heads' alignment (10/08/2026)
- README documents the presentation field, the two typographic factors and the move failure modal (10/08/2026)
- UPGRADE.md documents the menu opt-in, the failure modal and the `em` conversion (10/08/2026)

## v1.5.0

A console now asks a site what it runs, instead of waiting to be told

### ConfigBundle

- Added `StatusController`, serving the status report at `/status/report` to whoever presents the site's key (10/08/2026)
- A key missing, wrong, or not configured at all is answered 404, none of the three saying which (10/08/2026)
- The answer carries `Cache-Control: private, no-store`, its body depending on a header (10/08/2026)
- Removed `c975l:status:send`, nothing leaving a site on its own anymore (10/08/2026) [BC-Break]
- Removed the `site-status-url` config entry (10/08/2026) [BC-Break]
- Added `c975l:status:dump`, printing the report the route serves, needing no key and no network (10/08/2026)
- `site-status-key` now authenticates an incoming reader rather than signing an outgoing report (10/08/2026)
- A `site-status-key` shorter than 32 characters is treated as no key at all (10/08/2026)
- A refused status report request is logged as a warning, with the caller's IP and the reason (10/08/2026)
- `/status/report` stays reachable while the site is in maintenance mode (10/08/2026)
- Added `psr/log` to the required packages (10/08/2026)
- Updated the `description.site_status_key` translations (10/08/2026)
- Removed the two `site_status_url` translations (10/08/2026)
- README documents the route, its key, and the `config/routes.yaml` import it needs (10/08/2026)
- UPGRADE.md documents the move from a sent report to a read one (10/08/2026)
- Added the `StatusControllerTest` and `StatusDumpCommandTest` cases, replacing `StatusSendCommandTest` (10/08/2026)
- `MaintenanceListenerTest` covers the status report staying reachable during maintenance (10/08/2026)

## v1.4.3

An url the sitemaps declare is checked against robots.txt

### ConfigBundle

- Added `RobotsTxtMatcher`, deciding whether a `robots.txt` allows a path, longest matching rule first (08/08/2026)
- Added `SitemapRobotsHealthCheckProvider`, cross-checking every url the sitemap providers declare against the deployed `robots.txt` (08/08/2026)
- An url declared to search engines and forbidden to them by `robots.txt` is now reported, where `seo-files` only ever caught the blanket `Disallow: /` (08/08/2026)
- An url a sitemap provider declares on another host is left out of the cross-check (08/08/2026)
- The cross-check is read as `Googlebot`, not as the wildcard group alone (08/08/2026)
- The `sitemap-robots` rows are shown in the "Site" section (08/08/2026)
- Added `HostResolver`, telling a hostname that exists from one that doesn't, over both `A` and `AAAA` records (08/08/2026)
- Added `SslCertificateClient::fetchSubjectNames()`, reading the hostnames a certificate covers (08/08/2026)
- `DeploymentHealthCheckProvider` no longer reads a host variant that resolves and refuses the connection as one serving nothing (08/08/2026)
- The row names what the variant's certificate does cover, wildcards included (08/08/2026)
- A refusal a certificate covering the variant leaves unexplained is reported as such (08/08/2026)
- Added the `label.health_check_host_variant_certificate`, `label.health_check_host_variant_unreachable` and the four `label.health_check_sitemap_robots_*` translations (08/08/2026)
- README documents the two ways a site keeps crawlers out without meaning to (08/08/2026)
- Added the `RobotsTxtMatcherTest`, `SitemapRobotsHealthCheckProviderTest` and `HostResolverTest` cases (08/08/2026)
- `DeploymentHealthCheckProviderTest` covers a variant that resolves and refuses, with and without a certificate naming it (08/08/2026)
- `SslCertificateClientTest` covers the names read off a certificate, its alternative names included (08/08/2026)

### UiBundle

- `--hero-media-grid-padding` now defaults to `5%` instead of `27%`, a mark no longer sitting lost in the middle of its own tile (08/08/2026)
- The token is documented as the room a bare glyph needs (08/08/2026)
- `HeroMediaGridTileTest` locks the new default (08/08/2026)
- A downloadable document is shown as a card in a wrapping row, rather than as a full-width bar (08/08/2026)
- The thumbnail takes the card's own width and keeps its A4 ratio, rather than a fixed box (08/08/2026)
- The format is carried by a `badge`, darkened by a hover anywhere on the card (08/08/2026)
- A card lights up whole on hover and keeps the underline `a:hover` would add off (08/08/2026)
- `.btn` is drawn in the body font instead of the title one (08/08/2026)
- `.section-btn` states that same font rather than inheriting whatever the section wraps it in (08/08/2026)
- Added the `DocumentDownloadCardTest` and `ButtonFontFamilyTest` cases, `DocumentDownloadThumbTest` covers the ratio (08/08/2026)

## v1.4.2.1

- Updated Readme

## v1.4.2

A generated stylesheet is versioned by its own mtime

### UiBundle

- Added `StylesheetRegistry::isGenerated()`, telling a sheet written under `bundles/build/` from one an asset manifest versions (08/08/2026)
- `StylesheetExtension` appends a generated sheet's mtime as a cache-busting param, a theme color or font edited in the back-office no longer waiting for a hard reload (08/08/2026)
- A generated sheet not written yet is linked without a `?v=` param, rather than with `filemtime()`'s false (08/08/2026)
- `StylesheetExtension::resolve()` holds the per-path resolution the `array_map()` callback carried (08/08/2026)
- README documents versioning a generated sheet by its mtime (08/08/2026)
- `StylesheetRegistryTest`, `StylesheetExtensionTest` cover the generated path (08/08/2026)

## v1.4.1

An importmap entry is checked against what AssetMapper can serve

### ConfigBundle

- `c975l:config:check-importmap` judges a path by what AssetMapper can serve, rather than by the file being on disk (08/08/2026)
- An entry left pointing at a bundle's development checkout is repointed once Composer puts the real package back (08/08/2026)
- The warning names a path out of AssetMapper's reach, gone from disk or outside the mapped paths alike (08/08/2026)
- README documents what makes a customized path survive (08/08/2026)
- `CheckImportmapCommandTest` covers an entry sitting outside the mapped paths (08/08/2026)

## v1.4

A video platform is declared once for the whole ecosystem

### ConfigBundle

- A linkable route entry can name the `route` to generate and the `params` to fill it with, its key standing for one row of the contributing bundle's own data (08/08/2026)
- `translation_domain` accepts `false`, for an entry labelled with that row's own title (08/08/2026)
- Added `LinkableRouteRegistry::label()`, read by the target picker, the rendered menu item and `SiteCreateCommand` alike (08/08/2026)
- An entry can carry a `picker_label`, shown by the target select alone, where the rendered menu item keeps its `label` (08/08/2026)
- Added `LinkableRouteRegistry::pickerLabel()`, falling back to `label()` (08/08/2026)
- `LinkableRouteRegistry` normalizes every entry it hands back and only walks its providers when actually read (08/08/2026) [BC-Break]
- `ManagementTargetsTestCase` checks an entry's declared `route` rather than its key (08/08/2026)
- `ManagementTargetsTestCase` refuses a linkable route key that is a bare number, a row's id alone being ambiguous between two bundles (08/08/2026)
- `LinkableRouteRegistry` merges its providers by hand, a key surviving as the provider wrote it (08/08/2026)
- README documents contributing one entry per row (08/08/2026)
- Added `c975l:config:get`, reading back what `c975l:config:set` writes, from the database rather than from the cache (08/08/2026)
- The command takes a slug, or a prefix ending with `*` (a `%` is accepted too) (08/08/2026)
- `--show-sensitive` decrypts a secret, `--raw` prints the values alone to feed a shell variable (08/08/2026)
- An unknown slug, or a prefix matching nothing, exits non-zero, so a typo in a script doesn't read as an empty value (08/08/2026)
- `--raw` exits non-zero on an entry it would have to mask, instead of feeding the mask to a shell variable (08/08/2026)
- README documents reading values from the command line, naming the `site_config` table and its `slug` column (08/08/2026)
- The offsite `deleted/` folder is now `previous/`, naming what an operator looks for rather than the rclone mechanism filling it (08/08/2026) [BC-Break]
- Purging the offsite `previous/` folders no longer warns when the destination has none yet (08/08/2026)
- `OffsiteSynchronizer::run()` is protected, so the tests read back rclone's own output without a binary to run (08/08/2026)
- Added `LoginRequestSubscriber`, redirecting a login post carrying no usable username instead of letting it log an error (08/08/2026)
- README documents that subscriber next to the login throttling (08/08/2026)
- `c975l:scaffold:install` git-ignores `private/medias`, alongside `public/medias` (08/08/2026)
- `ConfigGetCommandTest`, `LoginRequestSubscriberTest` cover the new files, `ManagementTargetsTestCaseTest` the row-keyed entry (08/08/2026)

### UiBundle

- Added `Video\VideoPlatform`, the registry declaring a platform's urls, embed url, shape and CSP origin (08/08/2026)
- Added `Video\ResolvedVideo`, what a pasted url turns out to be (08/08/2026)
- Vimeo and Dailymotion join YouTube and TikTok as declared platforms (08/08/2026)
- `Twig\VideoExtension` reads the registry, so `privacy_embed_url` covers every declared platform (08/08/2026) [BC-Break]
- `privacy_embed_url` resolves an url to its platform's canonical embed url, player parameters not surviving (08/08/2026) [BC-Break]
- A YouTube playlist's `list` and an unlisted Vimeo's `h` do survive, being what makes the player play at all (08/08/2026)
- Added the `c975l_ui.video.embed_origins` parameter, for a site to build its CSP from the registry (08/08/2026)
- `Video:Iframe` gained a `caption` prop, hiding the figure's heading without losing the iframe's name (08/08/2026)
- `VichImageResizeListener` leaves a file that is not an image alone, an entity carrying a second Vich field no longer having it resized (08/08/2026)
- `Video:Video` gained the same `caption` prop as `Video:Iframe` (08/08/2026)
- The consent placeholder no longer names YouTube, every declared platform being behind it (08/08/2026)
- The framed player is given back its `allow` and `referrerpolicy` attributes, a platform's media server refusing a file to a player that cannot name the site it plays on (08/08/2026)
- Added `StylesheetShortcutController`, a dashboard tile recompiling the stylesheets an edit to the site's own theme files leaves stale (08/08/2026)
- `BlockFormController` submits a duplicated block's values instead of passing them as initial data, a non-string field no longer breaking the copy (08/08/2026)
- The duplication preview form is built without CSRF nor validation, a freshly copied field no longer coming back decorated with violations (08/08/2026)
- `.document-download__thumb` contains its thumbnail instead of covering the box, a landscape document no longer being cropped to a strip of its left edge (08/08/2026)
- README documents the registry, what it resolves, the CSP parameter, the caption prop and the recompile tile (08/08/2026)
- `VideoPlatformTest`, `StylesheetShortcutControllerTest`, `VideoIframeCaptionTest`, `VideoCaptionTest` and `DocumentDownloadThumbTest` cover the new files (08/08/2026)

## v1.3

A backup leaves the server, and a photo comes out signed

### ConfigBundle

- Added `BackupPathProviderInterface` and `BackupPath`, each bundle declaring its own irreplaceable files in `archive` or `mirror` mode (07/08/2026)
- Added `BackupPathCollector`, deduplicating the declarations and skipping what isn't on disk (07/08/2026)
- A declared folder already covered by another declaration's ancestor is dropped (07/08/2026)
- `ConfigBackupPathProvider` declares `.env.local` alone, `public/medias` and `private/medias` belonging to the bundles that write there (07/08/2026)
- `c975l:config:backup` no longer archives `public/` and `private/` whole, only the declared `archive` paths (07/08/2026) [BC-Break]
- Removed the complete/partial folder modes, their two marker files and the `site-backup-full-interval-months` config (07/08/2026) [BC-Break]
- `c975l:config:backup` now writes `var/backup/manifest.json`, naming the archives folder and the mirrored paths (07/08/2026)
- Added `c975l:config:backup:offsite`, mirroring the declared folders through rclone, and `--ack` for installs whose backups are pulled (07/08/2026)
- Added `OffsiteSynchronizer`, holding no credential and validating `site-backup-offsite-target` against `remote:path` before any `Process` (07/08/2026)
- `OffsiteSynchronizer` reads `rclone.conf` at the root of the project when the install has one, rather than depending on the `HOME` a task scheduler may not provide (07/08/2026)
- `c975l:scaffold:install` git-ignores `/rclone.conf`, the offsite credential never being committed (07/08/2026)
- Added `OffsiteState`, only a successful transfer moving the clock forward (07/08/2026)
- `OffsiteState::recordSuccess()` merges over the previous state instead of replacing it, the archives push no longer dropping what the mirror counted (07/08/2026)
- A success only clears the failure its own stream raised, `recordFailure()` taking the stream it names (07/08/2026)
- An emptied `site-backup-offsite-max-age-hours` falls back to 30 hours instead of switching the staleness alert off (07/08/2026)
- Added the `site-backup-offsite-target`, `site-backup-offsite-max-age-hours` and `site-backup-offsite-keep-days` configs (07/08/2026)
- The `backup` health check row now carries whether anything left the server, and what the mirror holds (07/08/2026)
- A backup that never left the server, or left too long ago, is now a warning on the run and an alert on the dashboard (07/08/2026)
- Scheduled `c975l:config:backup:offsite` nightly, `c975l:config:backup` keeping its 6-hourly slot (07/08/2026)
- Dropping the partial archive removes the file list it passed as command-line arguments, which broke past a few tens of thousands of files (07/08/2026)
- README rewrites the backup section around the three kinds of state and the two offsite models (07/08/2026)

### UiBundle

- Added `UiBackupPathProvider`, declaring `medias/site`, `medias/fonts` and the site-wide graphics `UiMediaNamer` writes at the root of `public/` (07/08/2026)
- `UiBackupPathProvider` reads its singleton graphics off `Media`'s own roles, covering the watermarks and the extensions a role can be stored under (07/08/2026)
- `MediaUploadType` validates a declared media type against the aliases its files are guessed as, a real `.wav` no longer being rejected by an `audio/wav` kind (07/08/2026)
- `VichImageResizeListener` generates the `-thumb.webp` derivative inset instead of outbound-cropped (07/08/2026) [BC-Break]
- UPGRADE describes the thumbnails no longer being cropped square, and what a grid counting on it has to do (07/08/2026)
- `VichMultiSizeImageInterface::getThumbnailSize()` now caps the thumbnail's longest side, not a square's side (07/08/2026)
- `VichImageResizeListenerTest` covers the multi-size derivatives, until now untested (07/08/2026)
- README describes `VichMultiSizeImageInterface` and its three sizes (07/08/2026)
- Added `Contract\VichOriginalKeepableInterface`, copying the untouched upload aside before the resize (07/08/2026)
- The kept original's extension comes from the file's own mime, against an allow-list (07/08/2026)
- README describes `VichOriginalKeepableInterface` (07/08/2026)
- Added `Contract\VichWatermarkableInterface` and `Service\ImageWatermarker`, stamping a site-wide signature into a corner of an upload (07/08/2026)
- The signature is picked between a dark and a light version on the luminance of the very corner it lands in (07/08/2026)
- Added the `watermark-on-light` and `watermark-on-dark` media roles, uploaded from the site graphics screen (07/08/2026)
- Neither watermark role raises a dashboard alert when missing, a site signing nothing being a finished site (07/08/2026)
- Added the `ui-watermark-position`, `ui-watermark-width` and `ui-watermark-margin` configs (07/08/2026)
- The signature is stamped once on the highres derivative, every smaller size being cut from it (07/08/2026)
- `ImageWatermarker` keeps the two logos for the whole request, a batch no longer reloading them per photo (07/08/2026)
- `VichImageResizeListener` applies an upload's EXIF orientation before measuring anything off it (07/08/2026)
- `VichImageResizeListener` derives every size from one resampling instead of three, halving the time a photo costs (07/08/2026)
- `VichImageResizeListener` re-arms `set_time_limit()` per file, a batch no longer sharing one request budget (07/08/2026)
- composer suggests `ext-exif`, without which a photo shot upright is stored on its side (07/08/2026)
- README describes the watermark, its two roles and its three configs (07/08/2026)
- Added `assets/js/pointer-sort.js`, the drag gesture behind the sortable, on Pointer Events instead of HTML5 drag and drop (07/08/2026)
- `ea-sortable.js` runs on it, so blocks and medias reorder at the finger (07/08/2026)
- At the finger a row is picked up by its move handle alone, the header bar staying a mouse-only grab zone (07/08/2026)
- The move handle gets a wider hit area on a coarse pointer (07/08/2026)
- The page scrolls itself when a drag comes within 60px of the top or bottom of the viewport (07/08/2026)
- Declared `@c975l/ui-bundle/pointer-sort.js` in the importmap, for a bundle sorting something that isn't a collection field (07/08/2026)
- Added `assets/js/mobile-file-accept.js`, dropping the `accept` attribute on touch devices (07/08/2026)
- An editor on Android reaches Drive and kDrive from an upload input instead of the photo gallery alone (07/08/2026)
- `MediaUploadType` enforces a kind's `media_types` server-side, which is what allows that attribute to be dropped (07/08/2026)
- `.portfolio-grid__project-img img` pads a contained screenshot off the card's edges (07/08/2026)
- Added `cancelAnimationFrame` to the eslint globals (07/08/2026)
- `ImageWatermarkerTest`, `PointerSortTest` and `MobileFileAcceptTest` cover the new files (07/08/2026)
- README describes the touch drag, its reuse from another bundle and the mobile file picker (07/08/2026)

## v1.2.5

A bundle keeps its own theme colors in its own group

### ConfigBundle

- Added `ConfigRepository::findBySlugPrefix()`, returning every config whose slug starts with the given prefix (05/08/2026)
- `Config::validateThemeColorValue()` checks any `theme-color-*` config, whatever its group (05/08/2026)
- `Config::validateThemeColorValue()` refuses a hex typed without its `#`, which CSS drops silently (05/08/2026)
- `ConfigTest` covers a `theme-color-*` declared outside the theme group, and a hex missing its `#` (05/08/2026)

### UiBundle

- A blocks collection's add button reads "Ajouter un UiBlock" instead of EasyAdmin's "Ajouter un nouvel élément" (05/08/2026)
- Added the `action.add_block` translation (05/08/2026)
- Added `assets/js/title-confirm.js`, moved in from SiteBundle and registered in `controllers-admin.js` (05/08/2026)
- Added `TitleConfirmControllerRegistrationTest` (05/08/2026)
- Documented the title-change confirmation in the readme (05/08/2026)
- `ThemeVariablesCssListener` compiles on the `theme-` slug prefix instead of the theme group (05/08/2026)
- A satellite bundle can therefore declare its colors in its own back-office group (05/08/2026)
- `ThemeVariablesCssListenerTest` covers a `theme-` slug carried by another group (05/08/2026)

## v1.2.4

A form counts the caller behind an address, not the address itself

### UiBundle

- Added `RateLimiterGuard::isAcceptedForIp()`, counting an IPv6 caller by its /64 rather than by the single address it uses (05/08/2026)
- A ceiling keyed on the address itself is walked straight through in IPv6, one more address out of one's own block opening a fresh bucket (05/08/2026)
- An IPv4 address stays counted whole, being scarce enough to stand for whoever holds it (05/08/2026)
- `FormController` reads it instead of `isAccepted()`, so the forms it serves are limited per caller (05/08/2026)
- `VichImageResizeListener` no longer enlarges an upload narrower than the entity's own target width, capping it at the original (05/08/2026)
- A `VichMultiSizeImageInterface` entity fed such a source served a stored "medium" bigger than its "highres", the two resolutions inverted (05/08/2026)
- `RateLimiterGuardTest`, `FormControllerTest` and `VichImageResizeListenerTest` cover the new cases (05/08/2026)
- Updated the readme's form protection section, and the UPGRADE notes (05/08/2026)

## v1.2.3

A deployed site is asked what it hands to a stranger and to a crawler

### The package

- Added the `audit-deps` script, `composer audit --abandoned=report` over the resolved dependencies (05/08/2026)
- It opens `composer qa` and the CI workflow, being the cheapest check and the one bearing on the dependencies just resolved (05/08/2026)
- Updated the README's quality checks section (05/08/2026)

### ConfigBundle

- Added `SecurityMisconfigurationHealthCheckProvider` and its `security-misconfig` kind, reporting what a deployed site hands to an anonymous visitor (05/08/2026)
- It probes `/_profiler` and `/_wdt/latest`, the `X-Debug-Token` header, `/.env`, `/composer.json`, `/composer.lock`, `/.git/config`, and the listings of `/vendor/` and `/var/` (05/08/2026)
- A path is only reported as served when the response carries a string its real content holds, a site answering 200 to everything otherwise being reported as serving the lot (05/08/2026)
- A session cookie missing `Secure`, `HttpOnly` or `SameSite` is a warning, `X-Powered-By` too, and a detailed `Server` banner is named without weighing on the status (05/08/2026)
- Added `SecurityProbeClient`, fetching one url without ever following a redirect and keeping the first bytes of its body (05/08/2026)
- The `security-misconfig` row shows in the Health check page's site-wide section (05/08/2026)
- `ContentQualityClient` reads the canonical url a page declares and the indexing directives it carries, `googlebot`'s merged into `robots`' own (05/08/2026)
- `ContentQualityAnalyzer` reports a canonical naming another url than the one checked, and a page declaring none at all (05/08/2026)
- A `noindex` on a url the site declares to search engines is an error rather than a warning: no amount of content quality makes up for a page absent from the results (05/08/2026)
- That check only runs on an entry the caller marks `indexable`, a page meant to stay out of the results carrying those directives on purpose (05/08/2026)
- `DeclaredUrlsHealthCheckProvider` marks its own entries so, being the urls a bundle hands to search engines through its sitemap (05/08/2026)
- `DeploymentHealthCheckProvider` checks that the site isn't served a second time under the other spelling of its host, `www` against the apex (05/08/2026)
- A variant host redirecting within itself weighs the same as one not redirecting at all, only the host it lands on settling which of the two is the site (05/08/2026)
- Nothing answering on the variant host is a pass, there being no page to deduplicate (05/08/2026)
- `seo-robots-block-ai` ships on: the crawlers that harvest pages to train a model give a site nothing back, where the answer engines that read a page to cite it - the very readers `llms.txt` is written for - were never in the blocked list to begin with (05/08/2026)
- The generated `robots.txt` names those answer engines in a comment under the blocked group, read from `AiCrawlerListUpdater::ANSWER_ENGINES` rather than fixed in the template, minus any a site added to its own blocked list by hand (05/08/2026)
- They are named rather than given a `User-agent:` group of their own, which would take them out of the `User-agent: *` rules and so out of `seo-robots-disallow` (05/08/2026)
- A config already in the database keeps its value, `c975l:config:load-all` never overwriting one: a site installed before this stays off until it is switched on, see UPGRADE (05/08/2026)
- Added `seo-robots-private`, for a site meant to stay out of search engines: `robots.txt` holds nothing but a global `Disallow: /`, no `llms.txt` is written and no `Sitemap:` declared (05/08/2026)
- Putting `/` in `seo-robots-disallow` could not say it, the `Allow: /` alongside winning the tie under RFC 9309 and leaving the site open (05/08/2026)
- `SeoFilesHealthCheckProvider` reads it too: the global `Disallow: /` it reports as the worst misconfiguration there is turns `ok` on such a site, and what it warns about instead is a `robots.txt` still open, the command not having run since the config was set (05/08/2026)
- `seo-robots-extra` is written inside the `User-agent: *` group instead of at the end of the file, a blank line closing no group under RFC 9309 (05/08/2026)
- Written last, its lines bound to the AI crawlers already blocked rather than to everyone (05/08/2026)
- A private site, declaring nothing besides its own rule, leaves those extra lines out (05/08/2026)
- Added the `label.health_check_security_misconfig_*`, `label.health_check_host_variant_*`, `label.health_check_content_quality_noindex`/`_canonical_*` and `seo_robots_private` translations (05/08/2026)
- Added the `SecurityMisconfigurationHealthCheckProviderTest` and `SecurityProbeClientTest` cases (05/08/2026)
- `ContentQualityAnalyzerTest`, `ContentQualityClientTest`, `DeploymentHealthCheckProviderTest`, `DeclaredUrlsHealthCheckProviderTest`, `SeoFilesWriterTest` and `SeoFilesHealthCheckProviderTest` cover the new cases (05/08/2026)
- Updated the readme's health check and SEO files sections, and the UPGRADE notes (05/08/2026)

## v1.2.2

A config shows the label its own site wrote, not a translation key

### ConfigBundle

- Added `ConfigLabelResolver`, falling back to the label stored by the import when a config's `label.xxx` key has no `site_config` translation (04/08/2026)
- The dashboard alert, the Configuration list, its edit page and its cross-group search all displayed that raw key instead (04/08/2026)
- `Config::$label` defaults to an empty string rather than staying uninitialized, no longer fataling when read back for display (04/08/2026)
- A menu entry can point at a plain controller carrying an `#[AdminRoute]`, not only at a CRUD one (04/08/2026)
- `MenuBuilder` names the action each entry opens (`index` unless the entry sets its own `action`) instead of leaving EasyAdmin to guess it (04/08/2026)
- `OnboardingStepBuilder` reads that same `action`, the tour highlighting a step by matching its url against the sidebar's own href (04/08/2026)
- `ManagementTargetsTestCase::testEveryMenuEntryPointsToACrudController()` becomes `testEveryMenuEntryPointsToAResolvableController()`, accepting either shape (04/08/2026)
- `ManagementTargetsTestCaseTest` runs that case over the entry shapes no bundle here declares yet (04/08/2026)

### UiBundle

- The `.blocks > .cards` row, synthesized around consecutive `card` blocks, carries the same `--section-space` step as every other page-level block instead of sitting flush against the one above it (04/08/2026)
- `SectionRhythmTest` locks that step, the row being the one page-level block the reset names no kind for (04/08/2026)

## v1.2.1

The deployment workflow answers to the same suite as the code

### ConfigBundle

- The scaffold ships `tests/Deploy/DeployWorkflowTest.php`, resolving every `bin/console` command its `.github/workflows/*.yml` call against the commands this site actually has - through the console's own resolution, so an abbreviation like `doctrine:migration:migrate` passes (04/08/2026)
- A workflow command a bundle renamed, or one written against a version `composer.lock` does not carry yet, fails the suite instead of stopping a deployment halfway through, workers already restarted (04/08/2026)
- The same test fails on any `vendor/` package installed as a symlink to a local working copy: what a suite proves about a working copy says nothing about the versions the deployment will install (04/08/2026)
- The health check keys `label.health_check_*` are asked for in the `config` domain, the catalogue shipping them, instead of SiteBundle's `site` where the translator found nothing and the raw key was displayed (04/08/2026)
- `TranslationDomainTest` reads every `trans()` and `HealthCheckErrorRow::build()` of `src/` and `scaffold/src/` and fails on a key asked from a domain other than the one it is shipped in (04/08/2026)

### UiBundle

- `PaginatorPageSize` reads a `pageSize` query parameter, whitelisted to 20, 50 or 100 rows, and `management/paginator.html.twig` offers the three as links under the paginator - wired once in `DashboardController::configureCrud()`, so every CRUD of every c975L bundle inherits it (04/08/2026)
- A thumbnail of the media library always opens a form: a site-wide graphic (favicon, logo, og-image, error-image) now goes to `SiteGraphicCrudController`, the only screen editing it, instead of the detail page EasyAdmin fell back to once its Edit action was hidden - a page showing neither the image nor a single action (04/08/2026)
- That link is only handed to an admin holding the `site-role-editor` permission, the one `SiteGraphicCrudController` gates itself with: without it the thumbnail is no longer a link at all, rather than one landing on a 403 (04/08/2026)
- `MediaCrudController` disables Detail altogether, `media_usages.html.twig` (its only content) going with it - the same summary already ships as the edit form's own "used in" widget (04/08/2026)

## v1.2

The three files a site hands to crawlers come from its own configs

### ConfigBundle

- `c975l:seo:files:create` writes `public/robots.txt`, `public/humans.txt` and `public/llms.txt`, from the new `seo` config group and from the urls every `SitemapProviderInterface` already declares - static files like the sitemaps, so they keep answering `200` during a maintenance where a controller would `503` (04/08/2026)
- The "Create the SEO files" dashboard shortcut runs the same writer, `ROLE_SUPER_ADMIN` like the sitemaps one (04/08/2026)
- `ConfigMaintenanceTaskProvider` schedules it nightly on the hours after the sitemaps, nothing to add to a site's own `MaintenanceSchedule` (04/08/2026)
- Seven `restricted` configs behind them, `seo-robots-block-ai` being off by default: blocking the models that train on the web while publishing an `llms.txt` for them to read contradicts itself (04/08/2026)
- The blocked crawlers are a config rather than a hardcoded list, that list ageing every few months (04/08/2026)
- `SitemapProviderInterface::getUrls()` accepts an optional `title` and `description`, ignored by the sitemap and listed in `llms.txt` - an url with no title is left out, and a provider with no titled url contributes no section, so it never becomes the sitemap in Markdown (04/08/2026)
- A file the site hand-wrote before this existed is copied to `existingFiles/public/` before being replaced, being told from a generated one by the marker each carries (04/08/2026)
- `c975l:scaffold:install` adds the three to the app's `.gitignore`, they are rewritten from this environment's own configs on every deploy - a site that used to commit them untracks them once, see UPGRADE (04/08/2026)
- `SeoFilesHealthCheckProvider` checks `humans.txt` too (missing or unrewritten for a month, the date it states having quietly started lying) and `llms.txt` when one is deployed, an absent one being a normal state that yields no row (04/08/2026)
- Added `AiCrawlersHealthCheckProvider`, monthly and only on a site that blocks them: it reports the crawlers that appeared in the community list at `seo-robots-ai-crawlers-source` since this site last updated its own (04/08/2026)
- `c975l:seo:crawlers:update` and its "Update the AI crawlers" dashboard tile merge them in, additively, never importing the answer engines the upstream list carries alongside the harvesters - it marks them with a free-text field no rule can sort reliably, so applying stays a `ROLE_SUPER_ADMIN` decision (04/08/2026)
- Added the `textarea` config kind, for a value ending up verbatim in a `.txt` file where the `html` kind's rich editor would add markup (04/08/2026)
- `TaggedInterfacePass` drops the two variables it never reads (04/08/2026)

### UiBundle

- `prependExtension()` declares the `ui_form` rate limiter itself, a site that never configured one having served every public Form unlimited (04/08/2026)
- A site declaring its own `ui_form` still decides, its config being merged over the prepended default (04/08/2026)
- `symfony/rate-limiter` moves to `require`, the prepended limiter being worth nothing without it (04/08/2026)
- The accent field's help and its empty option no longer describe a rule across the block's top edge, the accent having become the header band - an unset accent leaves the header on the site's own `--primary` (04/08/2026)
- An accented card is outlined in its own hue, the band's separator taking it too rather than cutting that outline in two - an unaccented card, and the `.card-header` the management accordion reuses outside any card, keep the neutral border (04/08/2026)
- A card's `.card-data` tail is pinned to the bottom of its body, a row of cards carrying images and texts of unequal heights lining every button up on one line (04/08/2026)
- That pinning is scoped to `.card-body:has(> .card-data)`, a body holding free content staying in the block flow where adjacent margins collapse (04/08/2026)
- Added `CardTailAlignmentTest`, locking the four rules the chain needs in the compiled stylesheets (04/08/2026)
- A form whose action emails the submission flashes "your message has been sent", the generic wording fitting neither a registration nor a password reset (04/08/2026)
- `SendEmailFormAction`'s default subject is translated, an admin reading their inbox got an English subject over a French email (04/08/2026)
- The assets' JS reads optional chaining and template literals rather than `&&` chains and concatenations (04/08/2026)
- The two CSS cache warmers return from their `catch` rather than falling through it (04/08/2026)
- The last French comment left in `src/` is in English (04/08/2026)

## v1.1.2

The bundle's own words come from its own catalogues

### The package

- The workflow's `GITHUB_TOKEN` is read-only, only the checkout needing it and the coverage going up on the Codacy secret (03/08/2026)
- Codacy leaves `tests/` out of its analysis (03/08/2026)
- The README's tree block is fenced as `text` (03/08/2026)

### ConfigBundle

- `c975l:scaffold:install` deletes a scaffold file its bundle has withdrawn, when this site never touched it - the hashes of every version ever delivered being declared in the bundle's own `scaffold/removed.json` (04/08/2026)
- A withdrawn file this site customized is left exactly where it is and reported with the bundle that withdrew it, `--force` deleting it into `existingFiles/` (04/08/2026)
- A withdrawn path some installed bundle still ships is never deleted, having moved between bundles rather than gone (04/08/2026)
- A guided project step navigates to a same-origin url only, a value the back-office edits reaching `location.href` unchecked otherwise (03/08/2026)
- `label.invalid_json`, `label.invalid_theme_color` and `label.slug_exists` move from `config` to the new `validators.{en,es,fr}.xlf`, the catalogue the validator reads (03/08/2026)
- Added `ConstraintMessageCatalogueTest`, covering the bundle's own `src/` and the scaffold's (03/08/2026)

### UiBundle

- The form's flash carries the translated sentence, the site layout rendering it as-is and showing the raw key otherwise (03/08/2026)
- The form component reads the `warning` flash too, the rate limiter's message vanishing otherwise (03/08/2026)
- It renders a flash escaped, the bag being shared with every other bundle and app (03/08/2026)
- `receiveCopy` is mapped, an unmapped child never reaching the data the action is handed - the copy a visitor asked for was never sent (03/08/2026)
- The Audio, Video, Slider, Readmore and authenticated-form components translate in `ui` rather than in the app's `site` domain (03/08/2026)
- The GDPR checkbox drops its own `site` override too (03/08/2026)
- The fifteen keys they ask for are declared in `ui.{en,es,fr}.xlf` (03/08/2026)
- `label.block_media_required`, `label.fixed_icon_invalid_format` and `text.password_mismatch` move to the `validators` catalogue (03/08/2026)
- An email's background is plain white rather than the site's own, a message being read in a client's own reading pane (03/08/2026)
- Its text is fixed black rather than themed, a dark palette resolving `--text` to a light color and sending white on white (03/08/2026)
- The Stimulus controllers brace the arrow bodies whose returned value is discarded (03/08/2026)
- Each raw `innerHTML` assignment names the server-rendered source it carries (03/08/2026)
- `ConstraintMessageCatalogueTest` reads the three other ways a message key reaches the validator, a named argument alone missing them (03/08/2026)
- `EmailStylesheetTest` skips a `var()` carrying a fallback, a deliberate override hook being no undeclared token (03/08/2026)
- It locks the blanket text color as fixed, the white background making a themed one unreadable (03/08/2026)
- Added `FormFlashMarkupTest` and `TemplateDomainCatalogueTest` (03/08/2026)
- `FormSubmissionTypeTest` covers the mapped `receiveCopy` over a real submission and the GDPR label's domain (03/08/2026)

## v1.1.1

The CI's checks are one list, replayable on fresh dependencies

### The package

- `composer qa` runs the five checks of the CI in its order, the list living in `composer.json` alone (03/08/2026)
- The workflow calls those scripts rather than the commands, its steps kept apart to name the one that failed (03/08/2026)
- Added `bin/ci.sh`, replaying `composer qa` on a copy whose dependencies are resolved from Packagist (03/08/2026)
- The local `vendor/` symlinks the sibling repositories, exposing code no tag published yet, which the CI never sees (03/08/2026)
- Added `scripts-descriptions`, `composer run -l` naming what each check covers (03/08/2026)
- `failOnPhpunitNotice` turns a PHPUnit notice into a failure, a green suite carrying none (03/08/2026)

### ConfigBundle

- The scaffold's `FunctionalTestCase` casts the admin role it reads, keeping the roles array typed at level 6 (03/08/2026)
- `BackupCommand`, `BackupDigestCommand` and `MessengerCleanupCommand` send through `EmailService` rather than `MailerInterface`, the last three emails of the ecosystem that didn't (03/08/2026)
- They gain the From/To resolution on the `email-*` config keys and the `email-debug` preview, and stop being the only emails a site can't see coming (03/08/2026)
- The messenger alert's marker only moves on a digest that actually left: `EmailService` swallows a transport failure, and touching it anyway buried the alert for good (03/08/2026)
- `c975l:config:backup:digest` reports a send that failed instead of letting the exception through, the digest itself having already been printed (03/08/2026)
- `c975l:config:messenger-cleanup` exits non-zero on a digest that was owed and never left, a green run having said nothing about the alert still pending (03/08/2026)
- `c975l:config:backup` says so too when its report doesn't leave, `--report` exiting non-zero then, `EmailService` returning false where `MailerInterface` used to throw (03/08/2026)
- The four operational emails carry their own sender as `Reply-To`, replying to an alert reaching the site's public contact address otherwise (03/08/2026)

### UiBundle

- Added `Service\GalleryShowcaseProvider`, putting `flex_columns`, `section_cards` and `collection` in the block showcase - the three built-in kinds no fixture can express (03/08/2026)
- Each stands in for its own kind, so the showcase draws one card per kind rather than an empty one next to it (03/08/2026)
- The two containers go through `BlockExtension::renderBlock()` with never-persisted slots, the collection through the `Collection:Grid` component with never-persisted `collection_item` blocks (03/08/2026)
- Added `GalleryShowcaseProviderTest` (03/08/2026)
- `EmailSendRequest` takes a `text` body, sent as plain text with no template and no layout - what an operational digest written by a command needs (03/08/2026)
- Its debug preview shows that text monospaced, there being no markup to re-render and an empty page to show otherwise (03/08/2026)
- A hero grid tile paints an opaque plate, `--hero-media-grid-background` (`#fff`), rather than the section's chip tone, so a mark's own colors never mix into the hero background (03/08/2026)
- It has no `:hover` left, lighting a tile up reading as if these marks were links (03/08/2026)
- `--hero-media-grid-padding` carries the inset of a mark inside its tile, a logo already margined needing far less of it than a bare glyph (03/08/2026)
- The twelve `--block-accent-*` take the c975L logo palette, muted and of an even weight, so a row of accented cards reads as one set (03/08/2026)
- A card's accent colors its header band rather than a rule across its top edge, `.card-header` reading `--card-accent` and a card without a header showing none (03/08/2026)
- Orange, yellow, lime and teal carry dark text and a non-inverted icon, white falling under 4.5:1 on them (03/08/2026)
- The header band rounds one pixel under `--radius-card`, the card's own background showing through as a white sliver otherwise (03/08/2026)
- Added `CardAccentTest` and `HeroMediaGridTileTest`, locking both looks in the compiled stylesheets (03/08/2026)
- `EmailService` refuses a request carrying more than one body, two of them going out as whichever its chain tested first (03/08/2026)
- The block fixtures' example company sits in Annecy rather than in Paris (03/08/2026)

## v1.1

Nothing deleted that no one asked to delete

### ConfigBundle

- `c975l:scaffold:install` never overwrites a file the site customized: it records what it delivers in `.c975l-scaffold.json` and only refreshes what is still identical to it (03/08/2026)
- A customized file is named in the output next to its scaffold source instead, the upgrade it needs being a decision only its author can make (03/08/2026)
- Added `--force` to take the new version anyway, backing the site's own copy up to `existingFiles/` first - `--path` narrows it (03/08/2026)
- A file still identical to its source is recorded on the way past, so a site predating the manifest is not read as having customized everything (03/08/2026)
- Refreshing an untouched file no longer writes a backup, `existingFiles/` keeping only what the site actually wrote (03/08/2026)
- `c975l:config:check-importmap` repoints an entry whose file is gone at the path its provider resolves, which is what the ConfigBundle+UiBundle merge did to every `vendor/` path (03/08/2026)
- An override is still left alone as long as it resolves; a dead path no provider claims is reported rather than left to answer 500 on the first page loading it (03/08/2026)
- Whether it resolves is answered by the importmap's own config reader: spelled out by hand, a plain filesystem path lost its leading slash and was looked for under the project root, so an override really there was reported missing and repointed (03/08/2026)
- The three retention configs now read a `0` the same way, as "keep everything", and an entry no row carries as "use the default" (03/08/2026)
- A field cleared at the back-office is read as that `0`, not as an unset entry: the three are declared `int`, so an emptied row comes back cast to zero and no fallback can tell it apart from a typed one - said so in the code and in the health check's own description (03/08/2026)
- `site-backup-retention-days` set to `0` now keeps every archive instead of purging at the 15-day default (03/08/2026)
- `site-messenger-cleanup-retention-days` set to `0` now keeps every failed message, a value `purgeOlderThan()` must never be handed (03/08/2026)
- `site-messenger-cleanup-retention-days` is declared `int` rather than `text`, as the two other retentions are - `c975l:config:load-all` aligns the stored rows (03/08/2026)
- `BackupRetentionPurger`'s "keep everything" guard was unreachable, `?: DEFAULT` reading a zero as an unset entry (03/08/2026)
- Added the two retention-reading cases to `BackupCommandTest` and said so in the config's own description (03/08/2026)
- Added `HealthCheckRetentionPurger`, bounding a `site_health_check_result` table that only ever grew (03/08/2026)
- Added the `site-health-check-retention-days` config, 90 days by default, `0` keeping everything (03/08/2026)
- Added the `health_check` config group and its three translations (03/08/2026)
- `c975l:health-check:run` purges before running, so an install with no provider gets its backup rows purged too (03/08/2026)
- Each check's latest row survives the purge whatever its age, the dashboard reading exactly those (03/08/2026)
- Added `HealthCheckResultRepository::findLatestIdPerUrlAndKind()` and `deleteOlderThan()` (03/08/2026)
- Added `HealthCheckRetentionPurgerTest` and the command's two purge cases (03/08/2026)

### UiBundle

- The page's vertical rhythm is one token, `--section-space`, with `--section-space-tight` for `text_section`, both offered by the scaffolded `ui.css` (03/08/2026)
- A section-level block now declares its step on its top edge only, so any two blocks are parted by exactly one instead of by one or two depending on the pair (03/08/2026)
- `flex_columns` was in no rhythm rule at all and sat flush against the block above it (03/08/2026)
- `hero` and `cta_band` no longer pad their bottom edge, which was added to the next block's top one (03/08/2026)
- A `hero` carrying a flat - an image or a color - keeps that bottom padding, its content sitting on the flat's own edge otherwise (03/08/2026)
- A `hero` showing a stat card gets back the 26px that card hangs below the grid, which it used to take off the step under it (03/08/2026)
- `feature_bar` carries its step as a `margin-block-start`, a padding parting the band's own hairlines from its items instead, and is left out of the margin reset for it (03/08/2026)
- A `flex_columns` column drops the theme's bottom margin off the element it ends on, an `image` slot's bare `<p>` pushing the section below it 16px lower (03/08/2026)
- `section_cards`, `section_features` and `flex_columns` render no row at all rather than an empty one, the heading's own bottom margin hanging under it otherwise (03/08/2026)
- Added `SectionRhythmTest`, locking the step on every kind the margin reset names, the bottom edge to flats alone, and the value to the token (03/08/2026)
- A card header's icon is whitened by the stylesheet, an `<img>` painting the SVG file's own fill on the colored band otherwise (03/08/2026)
- Added `CardHeaderIconTest` (03/08/2026)
- `BlockType` no longer reads an absent `slots` key as "the editor removed every slot": a marker rendered beside the collection tells that apart from a form that never carried it (03/08/2026)
- A submission PHP truncated at `max_input_vars` removes nothing at all, the marker proving nothing on a body cut wherever it landed (03/08/2026)
- Either way the collection is taken off the form rather than left declared unfed: an absent key reaches a declared child as a null, which `CollectionType` reads as "every row removed" (03/08/2026)
- Added `Form\Util\SubmissionIntegrity`, answering whether a request body arrived complete (03/08/2026)
- Added `SubmissionIntegrityTest` and `BlockSlotsGuardTest`, the latter reproducing over a real form the deletion the guard closes (03/08/2026)

## v1.0.0

ConfigBundle and UiBundle ship as a single package

### The package

- `c975l/config-bundle` and `c975l/ui-bundle` merged into `c975l/core-bundle`, two bundles in one package (03/08/2026) [BC-Break]
- Both bundles keep their namespace, services, templates, translations and `bundles.php` entry (03/08/2026)
- `replace` declared for both old package names, at the versions this package supersedes (03/08/2026)
- No PHP change at all: `getPath()` already anchors on each bundle class' own file, not on the package root (03/08/2026)
- One CI run, one PHPUnit run and one PHPStan run over the two bundles, catching the cross-breaks the two separate pipelines could not see (03/08/2026)
- First one caught: ConfigBundle left libxml's internal-error setting on for the whole process, and UiBundle's rasterizer then accepted a malformed SVG (03/08/2026)
- Both bundles' `phpstan-baseline.neon` are gone, the 27 errors they hid corrected in the two bundles rather than carried over (03/08/2026)
- The one exception is declared in `phpstan.dist.neon` instead: a trait a bundle ships for the consuming application's entities is used nowhere in the bundle itself (03/08/2026)
- The second PHPStan pass, level 6 on the scaffold alone, is kept and reads `ConfigBundle/scaffold` (03/08/2026)
- `nelmio/security-bundle` moved from `require-dev` to `require`: UiBundle's minimal layout calls `csp_nonce()` on every page (03/08/2026)
- `COMPOSER_ROOT_VERSION` dropped from the CI, the cross-requirement it worked around being gone (03/08/2026)
- `.stylelintrc.json` excludes `**/public/**` rather than `public/**`, as `.codacy.yaml` and `eslint.config.mjs` already did (03/08/2026)
- Both bundles' readme and header image say `composer require c975l/core-bundle`, the package the two are installed through (03/08/2026)
- Three UiBundle tests no longer skip themselves on a ConfigBundle class they could not see: it is now in the same package, and a skip there would be a test silently not running (03/08/2026)
- The nine translation files carrying hand-written `trans-unit` ids are normalised, their ids recomputed the way Symfony's own dumper writes them (03/08/2026)

### ConfigBundle

Accounts, scaffolding and shared plumbing move to the ecosystem root

- Added `Service\BundleLocator`, answering where the registered c975L bundles sit, off `%kernel.bundles_metadata%` (03/08/2026)
- `ConfigDeclarationLocator`, `ScaffoldInstaller`, `ImportmapSpecifierLocator` and `c975l:deprecations:check` read it instead of globbing `vendor/c975l/*`, a guess the merge invalidated (03/08/2026) [BC-Break]
- `ImportmapRegistry` prefixes each entry with the declaring bundle's own directory, so no bundle spells out its place under `vendor/` (03/08/2026) [BC-Break]
- `ImportmapProviderInterface` takes a path relative to the declaring bundle, not to the project root (03/08/2026) [BC-Break]
- `c975l:config:prune` and the "Obsolete configs" page keep an entry whose bundle is installed but not registered, reporting it apart instead of offering it for deletion (03/08/2026)
- Added `BundleLocator::unregisteredDirectories()` and `ConfigDeclarationLocator::findUnregisteredSlugs()`, read off Composer's installed-package registry (03/08/2026)
- Added the `label.config_prune_unregistered` translation in the three locales (03/08/2026)
- Added `BundleLocatorTest`, the registry's own prefixing cases and the prune command's kept-entry case (03/08/2026)
- `ContentQualityClient` restores libxml's internal-error setting after parsing a page, instead of leaving it on for the whole process (03/08/2026)
- Added `ContentQualityClientTest::testAnalyzeRestoresLibxmlInternalErrors`, locking that restore in both directions (03/08/2026)
- `ContentQualityClient` reads its attributes off elements only, an XPath node list carrying nodes that have none (03/08/2026)
- `PasswordResetter` and `UserRegistrar` say which entity is missing `setPassword()` instead of fataling on the call (03/08/2026)
- `UserCrudController` passes through a field yielded as a bare property name rather than configuring it (03/08/2026)
- `DeclaredUrlsHealthCheckProvider` reads a declared url defensively, the implementations being other bundles' code (03/08/2026)
- Failed Messenger dates are read as UTC, the digest no longer staying silent after an alert (03/08/2026)
- `ContentQualityAnalyzer` releases each batch's responses instead of holding them for the whole run (03/08/2026)
- `seo-files`, `deployment` and `redirect-chains` read the site root through `SiteUrlResolver` (03/08/2026)
- A `Redirect` row without a destination is skipped instead of erroring the whole path (03/08/2026)
- `RedirectImportProvider` no longer imports such a row (03/08/2026)
- `c975l:config:export-tables` judges mysqldump on its exit code rather than on stderr (03/08/2026)
- Its `--prefix` and `site-backup-database` must be plain identifiers (03/08/2026)
- `site_copyright()` ignores a `site-first-online-date` it cannot read (03/08/2026)
- `svg-fonts` added to the health check's site-wide kinds (02/08/2026)
- `Redirect` moved here from SiteBundle: entity, `RedirectSubscriber`, CRUD, export/import and the `redirect-chains` check (02/08/2026) [BC-Break]
- The `ssl-certificate`, `security-headers` and `seo-files` checks moved here too, none of them needing a Page (02/08/2026) [BC-Break]
- Added `SiteUrlResolver`, the one spelling of the site root every site-wide check groups on (02/08/2026)
- `security-headers` reads that root instead of resolving the home Page (02/08/2026)
- The content-quality machinery moved here: `ContentQualityAnalyzer`, `ContentQualityClient`, the `urls-<bundle>` check and its pass (02/08/2026) [BC-Break]
- Added `ContentOffenceLocatorInterface`/`ContentOffenceLocatorRegistry`, tracing an offence back to the screen holding it (02/08/2026)
- Added `SelfCheckedSitemapProviderInterface`, opting a sitemap out of the generic urls check (02/08/2026)
- SiteBundle's `PageExistenceChecker` landed here as `UrlStatusChecker` (02/08/2026) [BC-Break]
- `SessionNonceGenerator` moved here from SiteBundle, with its conditional Nelmio wiring (02/08/2026) [BC-Break]
- `site_copyright()` moved here too, with `site-author` and `site-first-online-date` (02/08/2026) [BC-Break]
- `MenuProvider` contributes the "Redirects" entry (02/08/2026)
- Added `ProcedureProvider` and its `procedures.json`, holding the redirect and account procedures (02/08/2026)
- The six legal identity configs moved here from SiteBundle: `site-owner`, `site-producer`, `site-hosting-provider`, `site-dpo`, `site-director-location`, `site-contact-phone` (02/08/2026)
- The account layer moved here from SiteBundle: `UserCrudController`, `UserManagementVoter`, `UserRegistrar`, `EmailVerifier`, `PasswordResetter` (02/08/2026) [BC-Break]
- The account half of SiteBundle's scaffold moved here too, `App\Entity\User` included (02/08/2026) [BC-Break]
- `MenuProvider` contributes the "Users" entry, and `configs.json` the `user-roles-available` key (02/08/2026)
- `EmailVerifier::sendEmailConfirmation()` and `UserRegistrar::register()` lost their `$template` argument (02/08/2026) [BC-Break]
- Both compose their email from the `account_validation` EmailTemplate (02/08/2026)
- Both return `false` without sending when that template has been renamed or deleted (02/08/2026)
- The scaffolded `RegistrationController`/`ResetPasswordController` redirect to `app_login` instead of a SiteBundle Page (02/08/2026) [BC-Break]
- The scaffolded `login.html.twig` calls UiBundle's `form_url()` (02/08/2026) [BC-Break]
- It and the scaffolded `reset.html.twig` extend `layout.html.twig` (02/08/2026) [BC-Break]
- The scaffolded `layout.html.twig` is shipped here now instead of by SiteBundle, and resolves to whichever layout is installed (02/08/2026) [BC-Break]
- Added `UserFormSeeder`, seeding the `register`/`reset_password_request` Forms and their two emails (02/08/2026)
- Added `AdminUserCreator`, shared by `c975l:site:create` and the new command below (02/08/2026)
- Added `c975l:config:user-create`, bootstrapping an admin on an app without a site foundation (02/08/2026)
- `ScaffoldInstaller` and `c975l:scaffold:install` moved here from SiteBundle (02/08/2026) [BC-Break]
- The failed-Messenger stack moved here: service, alert provider, controller, receiver, cleanup command (02/08/2026) [BC-Break]
- `c975l:site:messenger-cleanup` is now `c975l:config:messenger-cleanup`, and its routes `management_config_messenger_failed*` (02/08/2026) [BC-Break]
- `ExportTablesCommand` moved here, `c975l:site:export-tables` becoming `c975l:config:export-tables` (02/08/2026) [BC-Break]
- `ConfigShortcutProvider` gained the "Export tables" and "Enable/disable registration" tiles (02/08/2026)
- The `deployment` health check and `DeploymentClient` moved here (02/08/2026) [BC-Break]
- `ConfigMaintenanceTaskProvider` declares the messenger cleanup, `SiteMaintenanceTaskProvider` being removed (02/08/2026)
- The scaffolded `App\Scheduler\MaintenanceSchedule` is shipped by this bundle now (02/08/2026)
- Added `Management\ArchiveFileRegistrar`, replacing SiteBundle's `ArchiveFileTrait` (02/08/2026) [BC-Break]
- Added a `phpstan-baseline.neon` and a second PHPStan pass on the scaffold (02/08/2026)
- Added `symfonycasts/{reset-password,verify-email}-bundle` and `symfony/password-hasher` to the requirements (02/08/2026)
- Added `UserFormSeederTest`, `AdminUserCreatorTest` and the moved services' own tests (02/08/2026)
- The address configs `EmailService` resolves moved here: `email-to`, `email-to-name`, `email-reply-to`, `email-reply-to-name`, `email-from-name` (02/08/2026) [BC-Break]
- `email-from` is no longer declared twice, SiteBundle's identical copy being dropped (02/08/2026)
- `site-name`, `site-contact-email`, `site-director`, `site-made-by-logo`, `site-made-by-url` moved here (02/08/2026) [BC-Break]
- `url-terms-of-use` is declared here, its copies in Site/Shop/Payment being dropped (02/08/2026) [BC-Break]
- Added `ConfigsJsonTest`, guarding slug uniqueness and the translation of every label/description (02/08/2026)
- Added `Management\HealthCheckErrorRow`, replacing SiteBundle's trait (02/08/2026) [BC-Break]
- Its translation domain is a parameter, no longer hardcoded to `site` (02/08/2026) [BC-Break]
- `Twig\CanonicalUrlExtension` moved here from SiteBundle (02/08/2026) [BC-Break]
- The Messenger failure transport is optional, an app declaring none still compiling its container (02/08/2026)
- The failed-messages screen tolerates a `messenger_messages` table the transport hasn't created yet (02/08/2026)
- A role the edited user holds but `user-roles-available` no longer lists stays in `UserCrudController`'s choices (02/08/2026)
- `c975l:config:export-tables` writes no dump at all rather than a partial one (02/08/2026)
- Added the failed-Messenger stack's tests, plus `c975l:config:user-create`'s and `HealthCheckErrorRow`'s (02/08/2026)
- Every command's console output is in English, the eight that still spoke French included (02/08/2026)
- Documented the whole move in the readme and in UPGRADE.md (02/08/2026)

### UiBundle

Fonts, generic Twig helpers and this bundle's own menu entries

- Duplicating a card, a step or a slide now carries its rich text over: `block-duplicate.js` rebuilds the new row's Trix editor after copying the values, Trix reading its textarea only once, when the editor is built (03/08/2026)
- `block-duplicate.js` skips Trix's own toolbar when lining up the two rows' fields, its link dialog carrying a named input that shifted every field after it (03/08/2026)
- Added `DuplicatedItemTrixContentTest`, locking both statically, the repository having no browser to run the controller in (03/08/2026)
- `ImportmapProvider` declares its two entrypoints relative to this bundle, the `vendor/c975l/ui-bundle/` path it wrote no longer existing (03/08/2026)
- `SvgTextDetector::textNodes()` guards on `is_array()`, `xpath()` answering `false` on an expression it cannot parse (03/08/2026)
- `HasBlocksInterface` declares `reorderBlocks()`, which `BlockRelocator` has always called on it (03/08/2026) [BC-Break]
- `EmailTemplateRenderer::render()`, `renderBody()` and `renderNamed()` declare the `fields` array their own documentation described, alongside the scalars (03/08/2026)
- `VichImageResizeListener::processFixedIcon()` takes a `VichImageResizableInterface`, the type the caller actually holds (03/08/2026)
- The ICO writer reads its colour components off Imagine's `ColorInterface` rather than off the RGB class' own getters (03/08/2026)
- `UiMediaNamer` reads the uploaded file off the Vich mapping instead of off the entity, and falls back to the mime type for a file carrying no original name (03/08/2026)
- `RequiredMediaValidator` refuses a constraint that is not a `RequiredMedia`, like `FixedIconFormatValidator` already did (03/08/2026)
- `AiRephraseClient::callAnthropic()` and `callOpenAiCompatible()` are typed `string`, neither ever returning null (03/08/2026)
- `FormController` writes its flashes through the request's own session, no longer reaching for the container's `request_stack` (03/08/2026)
- `Form::setLinks()` no longer nulls out the `actionConfig` it has just filled, its `links` key always being set by then (03/08/2026)
- `SvgTextWarningListener` reads the flash bag off a `FlashBagAwareSessionInterface`, staying silent on a session that carries none (03/08/2026)
- `SvgTextDetector::textNodes()` compares `xpath()`'s return against `null`, the value it actually answers when it finds nothing (03/08/2026)

- Added `templates/layout.html.twig`, the minimal page shell an app running without SiteBundle falls back to (02/08/2026)
- The minimal layout now carries the theme, the site graphics, the share tags, the font preloads and the cookie banner (02/08/2026)
- The theme compiler moved here from SiteBundle: `ThemeVariablesCssListener`, `theme_variables_css()` and the ten `theme-*` configs (02/08/2026) [BC-Break]
- Added `ThemeVariablesStylesheetProvider`, loading the compiled theme between the bundles and the app's own files (02/08/2026)
- The site graphics moved here too: `SiteGraphicCrudController`, its alert/export/import providers, `OgImageType` (02/08/2026) [BC-Break]
- Added `SiteGraphicMediaUsageProvider`, the role half of SiteBundle's own usage provider (02/08/2026)
- The cookie banner moved here, `<twig:c975LUi:Cookie:Consent />` carrying its own enabled guard (02/08/2026) [BC-Break]
- `site-enable-cookie-consent` and `url-cookies-policy` are declared here now (02/08/2026) [BC-Break]
- `MenuProvider` declares the "Site graphics" entry (02/08/2026)
- The `svg-fonts` health check moved here from SiteBundle, reading only Media rows (02/08/2026) [BC-Break]
- The legal models moved here from SiteBundle: catalog, renderer, placeholders, customizer, the 18 templates, the `legal_model` block and its customization screen (02/08/2026) [BC-Break]
- `site-other-copyright` and `site-other-cookies` are declared here now (03/08/2026) [BC-Break]
- Added `BlockLocationProviderInterface`, `BlockLocationRegistry` and their pass, telling a site-wide block screen where each block sits (02/08/2026)
- Added the `legal_model_html()` Twig function, rendering a model with no block at all (02/08/2026)
- Added `Service\LegalModelEditUrl`, called by every `BlockEditUrlProvider` before its own fallback (02/08/2026)
- Added `BlockRepository::findByKind()` (02/08/2026)
- The `legal_model` drift health check moved here, reading blocks rather than pages (02/08/2026) [BC-Break]
- `MenuProvider` declares the "Legal models" screen, on `/ui/legal-models` (02/08/2026) [BC-Break]
- Added `twig/intl-extra` to the requirements, the dated models formatting their date with it (02/08/2026)
- `.legal div` and `.legal-editable` moved here, `--scroll-offset` joining the scaffolded theme (02/08/2026)
- Added `Service\SvgTextDetector`, finding the `<text>` an SVG still draws with a font instead of with paths (02/08/2026)
- Added `Listener\SvgTextWarningListener`, flashing that warning on the upload itself, whatever screen it came from (02/08/2026)
- Added `MediaRepository::findSvgCandidates()`, the rows a check has to read to tell (02/08/2026)
- Documented the whole thing in the readme, under the site graphics (02/08/2026)
- Shortened the scaffolded `themes/ui.css` header to five one-line comments (02/08/2026)
- The Fonts stack moved here from SiteBundle: entity, repository, two controllers, three services, listener, Twig extension, export/import providers (02/08/2026) [BC-Break]
- `MenuProvider` now declares this bundle's own entries (media library, forms, email templates, fonts), which SiteBundle used to contribute on its behalf (02/08/2026)
- `nl2br`, `linkify`, `route_exists`, `template_exists` and `asset_exists` moved here from SiteBundle (02/08/2026) [BC-Break]
- `StylesheetProvider` contributes `bundles/build/site-fonts-uploaded.css`, alongside the listener writing it (02/08/2026)
- `EmailTemplateRenderer` gained an `EmailTemplateRepository` argument and a `renderNamed()` method (02/08/2026) [BC-Break]
- Added `Service\FormSeeder`, the shared `ensureForm()`/`ensureEmailTemplate()` seeding each bundle's own Forms (02/08/2026)
- Added `FormPageUrlProviderInterface`, `FormPageUrlRegistry` and the `form_url()` Twig function (02/08/2026)
- Added `Service\BuildFileWriter`, replacing SiteBundle's `BuildFileWriterTrait` (02/08/2026) [BC-Break]
- Moved the `label.fonts`/`label.font_*`/`flash.font_*` translations into the `ui` domain (02/08/2026) [BC-Break]
- `site-form-delay` and `site-form-gdpr` moved here from SiteBundle, this bundle's Form layer being what reads them (02/08/2026) [BC-Break]
- Added the eight missing `label.*` translations of the AI assistant and block showcase configs (02/08/2026)
- Added `ConfigsJsonTest`, which is what would have caught them (02/08/2026)
- `Form\VichImageOptions` moved here from SiteBundle, four satellite bundles hand-duplicating those five options in fifteen forms (02/08/2026) [BC-Break]
- Added `Service\UniqueSlug` and `Service\BlockFocusUrl`, replacing SiteBundle's traits of the same job (02/08/2026) [BC-Break]
- Added `Service\BlockMoveRowAttrBuilder`, a service rather than a trait reaching for the calling controller's members (02/08/2026) [BC-Break]
- `Listener\AbstractBlockCacheInvalidationListener` moved here, SocialBundle having written the same wiring by hand (02/08/2026) [BC-Break]
- `Management\BlockDataExporter`/`BlockDataImporter` moved here, blocks and their medias being this bundle's own (02/08/2026) [BC-Break]
- Added `FormBlockDependencyProviderInterface` + registry, decoupling the importer from SiteBundle's DefaultPagesImporter (02/08/2026)
- `Controller\DownloadController` moved here - deliberately not merged with `PrivateFileResponseFactory`, which serves purchased digital items and keeps its own access checks (02/08/2026) [BC-Break]
- `EmailSendRequest` gained `bcc`, a real blind copy rather than `copyToEmail`'s second message (02/08/2026)
- `EmailSendRequest` gained `wrapLayout`, rendering a bundle's body template and wrapping it through `EmailLayoutRegistry` (02/08/2026)
- `EmailService` gained an `EmailLayoutRegistry` argument (02/08/2026) [BC-Break]
- `require` now asks for `c975l/config-bundle` v6 (02/08/2026) [BC-Break]
- The `site_font` Vich mapping is declared here, alongside the entity reading it (02/08/2026)
- `nl2br` keeps the native filter's `pre_escape`, an unescaped value no longer reaching the page (02/08/2026)
- `FontRegistry` merges every provider instead of keeping the first one (02/08/2026)
- `FontCssListener` regenerates once per flush rather than once per font (02/08/2026)
- Documented the whole move in UPGRADE.md (02/08/2026)
- Documented the moved helpers, Twig filters and export/import in the readme (02/08/2026)
- Covered the moved helpers, registries and compiler passes with their own tests (02/08/2026)

---

Each bundle's published history stops at its last release and stays in its directory: [ConfigBundle/ChangeLog.md](ConfigBundle/ChangeLog.md) up to `v5.17.1`, [UiBundle/ChangeLog.md](UiBundle/ChangeLog.md) up to `v1.17.0`. Everything after them is here.
