# ChangeLog

## Unreleased

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

### ConfigBundle

Accounts, scaffolding and shared plumbing move to the ecosystem root

- Added `Service\BundleLocator`, answering where the registered c975L bundles sit, off `%kernel.bundles_metadata%` (03/08/2026)
- `ConfigDeclarationLocator`, `ScaffoldInstaller`, `ImportmapSpecifierLocator` and `c975l:deprecations:check` read it instead of globbing `vendor/c975l/*`, a guess the merge invalidated (03/08/2026) [BC-Break]
- `ImportmapRegistry` prefixes each entry with the declaring bundle's own directory, so no bundle spells out its place under `vendor/` (03/08/2026) [BC-Break]
- `ImportmapProviderInterface` takes a path relative to the declaring bundle, not to the project root (03/08/2026) [BC-Break]
- Added `BundleLocatorTest`, and the registry's own prefixing cases (03/08/2026)
- `ContentQualityClient` restores libxml's internal-error setting after parsing a page, instead of leaving it on for the whole process (03/08/2026)
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
