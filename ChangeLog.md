# ChangeLog

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
