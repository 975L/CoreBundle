# ChangeLog

## v1.15.0

An e-mail is written once per language, and carries its documents

### ConfigBundle

- Added the `made-by-wording` config (`made`, `powered`), the "Made by" credit naming either the party that built the site or the one whose system it runs (23/08/2026)
- `CreditsExtension` gains `made_by_label()`, answering `label.made_by` or `label.powered_by` (23/08/2026)
- `DashboardController` words the sidebar credit through it, instead of `label.made_by` alone (23/08/2026)
- Added `label.made_by_wording` and `description.made_by_wording` to the three `site_config` catalogues (23/08/2026)
- The README documents the wording next to `credits_mode()` (23/08/2026)
- Extended `CreditsExtensionTest` and `DashboardControllerTest` (23/08/2026)
- Added `OAuthLoginProviderInterface`, `OAuthLoginClient`, `OAuthUserResolver` and `OAuthLoginController`: "sign in with Google" on the login page, without any new dependency (23/08/2026)
- Added `GoogleOAuthLoginProvider`, the only provider shipped - another one is a tagged class, two config keys and an icon, nothing else to change (23/08/2026)
- Added the `login-google-oauth-client-id` and `login-google-oauth-client-secret` configs, and the `connecter-google` procedure that tells where to get them (23/08/2026)
- Added the `c975LConfig:Security:OAuthLogin` component and `oauth_login_providers()`, rendering nothing until a site fills those credentials (23/08/2026)
- An OAuth account is linked by e-mail alone, and only on an address the provider vouched for: no per-provider column, hence no migration (23/08/2026)
- An account that never confirmed its e-mail has its password replaced before being enabled, so registering someone else's address grants nothing (23/08/2026)
- An account an admin disabled is refused at the OAuth door and told so, instead of being taken over and re-enabled (23/08/2026)
- An account created by an OAuth sign-in is announced to the site owner, as any registration is (23/08/2026)
- A closed registration form now closes the OAuth door too: an existing account still signs in, a new one is refused (23/08/2026)
- `/connect/{provider}` takes a `redirect` query parameter, a path of this site only, so a visitor comes back where they clicked (23/08/2026)
- The scaffold's `security/login.html.twig` renders the component, the only scaffold file this touches (23/08/2026)
- The account e-mails read their sentences from the `config` catalog instead of holding a copy in PHP (23/08/2026)
- Added `label.account_validation_*` and `label.password_reset_*` to the `config` catalogs (23/08/2026)
- `UserFormSeeder` declares its two email templates through `EmailTemplateProviderInterface` (23/08/2026)
- Added `Entity\NotFound` and `NotFoundRepository` (table `site_not_found`), one row per dead path with its last referer, whether that referer is the site's own, its hit count and its dates (23/08/2026)
- Added `NotFoundSubscriber`, recording the 404s that carry a `Referer` - the ones a link led to, scanners sending none - on `GET`, off static asset paths, on a `http`/`https` referer only, and never on a `410` (23/08/2026)
- The row is written in plain SQL on the connection: a flush failing inside `kernel.exception` would close the entity manager and take the error page down with it, and any failure at all is swallowed (23/08/2026)
- Added `NotFoundCrudController`, read-only but for deleting a row, with a *Create the redirect* action opening a new `Redirect` on that very path (23/08/2026)
- `RedirectCrudController::createEntity()` prefills `fromPath` from the query, which is what that action hands it (23/08/2026)
- Added `NotFoundAlertProvider`, alerting on the broken links the site's own pages carry - the external ones being a redirect to make when convenient, not something to interrupt anyone with (23/08/2026)
- Added `c975l:config:not-found-cleanup` and its weekly `MaintenanceTask`, deleting what nothing has followed for `site-not-found-retention-days` (90 by default, `0` keeping everything) (23/08/2026)
- Added the `site-not-found-retention-days` config, the *Broken links* menu entry (advanced tier, editor role) and the twelve `config`/`site_config` keys behind them in the three catalogues (23/08/2026)
- Added `NotFoundSubscriberTest`, `NotFoundCrudControllerTest`, `NotFoundAlertProviderTest` and `NotFoundCleanupCommandTest`, and extended `RedirectCrudControllerTest` with the prefill (23/08/2026)
- The README documents it next to the redirects, and says why Monolog is deliberately not that place (23/08/2026)
- `UserRegistrar::register()` returns `void`, an undelivered confirmation e-mail no longer reporting a registration as failed (23/08/2026) [BC-Break]
- The `register` scaffold action returns `true` once the account is persisted, and `UserRegistrarTest` covers the undelivered e-mail (23/08/2026)
- Added the `config-not-found` guided project, walking a dead link to the redirect that answers it (23/08/2026)
- Added its fifteen `label.guided_*`/`description.guided_*` keys to the three `config` catalogues (23/08/2026)
- `ResetPasswordRequestFormAction` refuses a `reset_password_request` form whose `email` field an admin renamed, instead of failing on an undefined key (23/08/2026)
- The `c975l-operations` skill carries the broken links, next to the redirects they become (23/08/2026)

### UiBundle

- The reviews left SocialBundle and live here, with what visitors write on the site: one `Review` entity, one `site_review` table, `source` telling an import from a submission (23/08/2026) [BC-Break]
- `Review` carries `ownerType`/`ownerId`, the same vocabulary as `Rating`: a book gathers a score and a text under one word (23/08/2026)
- Added `ReviewStatus` (`pending`/`published`/`rejected`): a submission is held until someone lets it through, an import is born published (23/08/2026)
- Only published reviews are ever served, every visitor-facing query going through the same filter (23/08/2026)
- Added `ReviewService`: what a submission is born as, what publishing does to the owner's average, and where an answer goes (23/08/2026)
- Publishing a scored review records it in `Rating` under a key derived from the author's e-mail, and rejecting it takes it back out (23/08/2026)
- Added `RatingService::record()` and `withdraw()`, which store and remove a score without the toggle `vote()` needs (23/08/2026)
- Added `GET|POST /review/{ownerType}/{ownerId}` and `ReviewType`: name, e-mail, optional score and text, honeypot, no account (23/08/2026)
- The submission form is a page of its own, the reviewed pages being served from a shared cache no session may travel with (23/08/2026)
- Added the `ui_review` rate limiter, three an hour per caller (23/08/2026)
- Added `ReviewReplyPublisherInterface` and `ReviewReplyRegistry`: a platform's answer is pushed by whoever brought the review (23/08/2026)
- The moderation screen publishes and rejects a local review, deletes it, and only ever answers an imported one (23/08/2026)
- Added `ui_reviews()` and `ui_reviews_enabled()`, plus `components/Review/List.html.twig` (23/08/2026)
- Added `ui-enable-reviews`, which replaces `social-enable-reviews` (23/08/2026) [BC-Break]
- Added `ReviewVerifierInterface` and its registry: a bundle says whether the person leaving a review got hold of what they are reviewing, and the "vérifié" badge is earned rather than assumed (23/08/2026)
- `ReviewService::submit()` settles `verified` once, at submission, and never recomputes it - an order archived years later must not un-verify a review (23/08/2026)
- `label.review_form_moderation_notice` says what makes a review verified, art. L111-7-2 asking the site to state its own check (23/08/2026)
- The French rating tally counts votes and no longer "avis", the word now standing for the written reviews on the same page (23/08/2026)
- Added the `credit-card` ratio to the flip card, the ID-1 format of a bank card - `FlipCardType::RATIO_CHOICES`, which is the slider's own list plus that one shape (23/08/2026)
- Added `label.ratio_credit_card` to the three `ui` catalogues (23/08/2026)
- Added `.flip-card-ratio-credit-card` to `sass/_flip-card.scss` and rebuilt the stylesheets (23/08/2026)
- `FlipCardAccessibilityTest` reads the closed list off the form's own choices rather than restating it, and checks every offered ratio has a class (23/08/2026)
- The README documents the shape and names `c975l/payment-bundle`, which draws a gift card with it (23/08/2026)
- Added `Contract\PdfGeneratorInterface` and `Service\DompdfGenerator`, so a page a site publishes can be handed over as a file (23/08/2026)
- The engine fetches nothing remote and is penned under the site's public directory, a document being drawn out of markup an admin typed (23/08/2026)
- A paper size may be stated in millimetres, which is what a document that is an object rather than a page needs (23/08/2026)
- `composer.json` requires `dompdf/dompdf` `^3.1`: pure PHP, so a bundle installed by anybody imposes no runtime on them (23/08/2026)
- Added `Model\EmailAttachment` and `EmailSendRequest::$attachments`, files travelling with a message (23/08/2026)
- The debug preview names the attached files without carrying their bytes into the session (23/08/2026)
- Added `Service\WeasyPrintGenerator`, the other engine - a binary shelled out to, no Composer dependency added (23/08/2026)
- Added `Service\PdfGenerator`, which picks: `ui-pdf-engine` takes `auto`, `dompdf` or `weasyprint` (23/08/2026)
- `auto` asks the binary rather than the configuration, so one codebase across a fleet renders as well as each server allows (23/08/2026)
- `PdfGeneratorInterface` is aliased to the picker in `config/services.yaml`, three classes implementing it (23/08/2026)
- Added the `ui-pdf-engine` and `ui-pdf-weasyprint-path` configs and their `site_config` labels (23/08/2026)
- Added `Management\PdfEngineHealthCheckProvider`, saying which engine a site landed on (23/08/2026)
- Added `label.pdf_engine_summary_available` and `label.pdf_engine_summary_missing` to the three `ui` catalogues (23/08/2026)
- Added `DompdfGeneratorTest` and `PdfGeneratorTest`, and three cases to `EmailServiceTest` (23/08/2026)
- The README gained `Generating PDFs`, and the `c975l-media` and `c975l-forms-emails` skills carry the engine and the attachments (23/08/2026)
- Added `Service\LegalDocument`, one truth per legal document: the `legal_model` block's version where the site rewrote it, the model as shipped otherwise (23/08/2026)
- Added the `legal_document_html()` Twig function, which every page, file and attachment carrying a legal document reads (23/08/2026)
- Added `LegalDocumentController` and the `ui_legal_document_pdf` route, any legal document as a file, on every site (23/08/2026)
- The file is cached under `var/pdf/` and keyed by a hash of the rendered text, so a clause rewritten or a date changed regenerates it and nothing else does (23/08/2026)
- Added `LegalDocumentTest` (23/08/2026)
- Added `Contract\EmailAttachmentProviderInterface` and `Registry\EmailAttachmentRegistry`: a bundle says which documents it can draw, and draws the one asked for (23/08/2026)
- `EmailTemplate` carries an `attachments` column, the kinds an admin ticked - which files an e-mail travels with is stored, not coded (23/08/2026)
- `EmailTemplateCrudController` shows those kinds as checkboxes, and nothing at all on a site whose bundles offer none (23/08/2026)
- Added `EmailTemplateRenderer::attachmentsFor()`, read off the site's own row and handed to `EmailSendRequest::$attachments` (23/08/2026)
- Added `Service\LegalDocumentAttachmentProvider`: every legal model the site publishes, attachable, named in the recipient's language (23/08/2026)
- Added `label.email_attachments` and `description.email_attachments` to the three `ui` catalogues (23/08/2026)
- Added `EmailAttachmentRegistryTest` and `LegalDocumentAttachmentProviderTest`, and four cases to `EmailTemplateRendererTest` (23/08/2026)
- `EmailTemplate` carries a `locale`, unique with its name: one e-mail, one row per language it is written in (23/08/2026) [BC-Break]
- Added `EmailTemplateRepository::findForRendering()`, trying the recipient's language, then the site's, then any (23/08/2026)
- `EmailTemplateRenderer::renderNamed()` takes the recipient's locale, which is neither the request's nor the site's (23/08/2026)
- `FormSeeder::ensureEmailTemplate()` seeds one row per enabled language, the site's own always getting one (23/08/2026)
- Added `EmailBlock::TYPE_SLOT`, a fragment the sending code renders and hands over, named by the block's label (23/08/2026)
- A slot holding nothing renders nothing, so an order with no gift card shows no empty row (23/08/2026)
- Added `EmailTemplateProviderInterface` and its registry: a bundle declares its e-mails once, and the seeder, the command and the health check all read that (23/08/2026)
- Added `c975l:ui:email-templates:ensure`, safe on every deployment, which is what reaches a site built before a bundle gained an e-mail (23/08/2026)
- Added `EmailTemplateHealthCheckProvider`, reporting the e-mails whose wording no admin can edit yet and the ones that would go out empty (23/08/2026)
- `EmailTemplateRenderer::renderNamed()` renders the declaration itself when the site has no row for it, so a template deleted in the back-office is an uneditable e-mail rather than a missing one (23/08/2026)
- Added `EmailTemplateFactory`, the one place a declaration becomes an `EmailTemplate` - shared by the seeder, which persists it, and the renderer, which does not (23/08/2026)
- A missing template row reads as a warning and no longer as an error, the e-mail still leaving (23/08/2026)
- Added `EmailBlock::DATA_TYPES` and `isDataBlock()`: a slot and a fields table are what an e-mail is for, not decoration (23/08/2026)
- A seeded template keeps its data blocks - they move, they are never deleted, and their kind and slot name are locked (23/08/2026)
- `c975l:ui:email-templates:ensure` adopts the templates a site had before e-mails carried a language, instead of seeding a duplicate beside each one - a generated migration brings the column and never the rows (23/08/2026)
- Added `FormEditUrl`: the pencil over a form block opens the Form's own screen, where its fields are (23/08/2026)
- The email preview carries a pencil back to the screen it is composed on (23/08/2026)
- Added `label.locale` and `label.email_block_type_slot` to the `ui` catalogs, and `error.email_template_name_locale_taken` to `validators` (23/08/2026)
- Added `tests/Management/EmailTemplateHealthCheckProviderTest.php`, and cases for the slot block and the recipient's language (23/08/2026)
- `EmailTemplate` carries `seededBlocks`, the data blocks it has already been offered - what tells a template that never received one from a template whose admin removed it (23/08/2026)
- `c975l:ui:email-templates:ensure` backfills the data blocks a declaration gained since a template was seeded, appended at its end, once each and never again (23/08/2026)
- Wording is never backfilled: a sentence is the admin's to write and has no identity to match on (23/08/2026)
- `FormSeeder::ensureEmailTemplate()` returns how many blocks it backfilled, and the command reports them (23/08/2026)
- `FormSeeder::ensureEmailTemplate()` adopts a template written before e-mails had a language instead of seeding a duplicate beside it, so `c975l:config:user-create` no longer has to wait for `c975l:ui:email-templates:ensure` (23/08/2026)
- UPGRADE.md says what the `locale` column costs a site already running: the `ALTER`, the unique index moving to `(name, locale)`, and the command to run once after the migration (23/08/2026)
- `EmailTemplateRenderer::attachmentsFor()` no longer overwrites a language the context carries with the one it was not given, so a document travels in the reader's (23/08/2026)
- Added `Review::SCALE`: a review is scored out of five, whatever `ui-rating-scale` says for the ratings, and the form no longer offers a note the reviews are not read on (23/08/2026)
- Added `PrivateFileResponseFactory::createInlineResponse()`, what a paywall needs and the download response cannot give: a private file drawn or played in the page instead of being saved (23/08/2026)
- That response is marked `private` and `nosniff`, and leaves `BinaryFileResponse` free to answer `Range` requests - without them a video plays from its start and cannot be moved through (23/08/2026)
- Extended `PrivateFileResponseFactoryTest` with the inline response, its private cache directive and its fallback to the file's own name (23/08/2026)
- The README and the `c975l-media` skill document it beside the download response, with the access check named as staying the caller's (23/08/2026)
- `layout.html.twig` becomes the single source for the `<head>`, SiteBundle's layout extending it instead of restating it (23/08/2026)
- Added the `header`, `main`, `heading`, `container`, `share`, `navigationBottom` and `footer` blocks, what a child layout fills without touching the shell (23/08/2026)
- A child layout's own values - `ogImageMedia`, `headingDisplayed`, `bodyClasses`, `bodyControllers` - are read here without this file knowing one of its entities (23/08/2026)
- The body carries `bodyClasses` and `bodyClass`, so a screen laid on its own background paints the navbar and the footer with it (23/08/2026)
- The `password` controller is declared on the body, one declaration covering a page carrying several forms (23/08/2026)
- Added `<link rel="alternate" hreflang>`, printed when the rendering template hands over the whole group (23/08/2026)
- The preconnect list merges `site-preconnect` with Matomo's origin, where the shell only ever printed Matomo's (23/08/2026)
- A flash label outside the four tinted variants falls back on `info`, instead of printing black ink on the dark page (23/08/2026)
- A flash message keeps the line breaks it was written with (23/08/2026)
- `og:image:width` and `og:image:height` read the media's intrinsic pair, a column an admin may have typed as a css length being no pixel count (23/08/2026)
- The style nonce is minted next to the script one and printed as `csp-nonce`, the value Turbo nonces its progress bar's own `<style>` with (23/08/2026)
- The share buttons band is included with `ignore_missing`, so a site not installing SocialBundle still compiles (23/08/2026)
- The scroll buttons and the table of contents point at a bare `#anchor` again, the `<base href>` they were working around being gone (23/08/2026)
- Added `ReviewCollectionSourceProvider`: the published reviews are a source of the generic `collection` block, so they need no block kind of their own (23/08/2026)
- Added `ReviewCacheInvalidationListener`, emptying the `ui_reviews` tag once per flush rather than once per row (23/08/2026)
- Added the `emailDataBlock` controller, which takes away the button that would drop a data block the save puts back (23/08/2026)
- Added `BodyClassBlockTest`, `CspNonceMetaTest`, `FlashVariantTest`, `FlashesSessionGuardTest`, `HeadAssetTrackingTest`, `RobotsMetaTest`, `ShareImageMetaTest`, `UrlMetadataFallbackTest` and `OptionalBundleTemplateTest` (23/08/2026)
- The README gained `The page layout` and `Visitor reviews`, the two subjects this lot left undocumented (23/08/2026)
- The `c975l-forms-emails` skill carries the declared e-mail, its language and its data blocks, and `c975l-ui-assets` the layout they render in (23/08/2026)
- Added `EmailTemplateFactoryTest`, `EmailTemplateProviderRegistryTest`, `EmailTemplateRepositoryTest`, `FormEditUrlTest`, `WeasyPrintGeneratorTest`, `PdfEngineHealthCheckProviderTest` and `LegalDocumentControllerTest` (23/08/2026)
- Added a test for each of the four new provider passes, as every other one carries (23/08/2026)
- The reviews index carries a line saying what the screen moderates, and `label.info_reviews` joins the three `ui` catalogues (23/08/2026)
- The README shows the block gallery as a picture (`.github/images/UiBlocks.png`) (23/08/2026)

## v1.14.1

Debug mode shows every email, whatever sent it

### UiBundle

- `EmailService` leaves its debug preview in the session, every email of the site being shown the same way whatever sent it (22/08/2026)
- `EmailService::consumeDebugPreview()` becomes `consumeDebugPreviews()`, returning one preview per email (22/08/2026) [BC-Break]
- Removed `Contract\DebugPreviewCapableInterface` and the `FormController` branch reading it (22/08/2026) [BC-Break]
- `SendEmailFormAction` no longer declares a preview of its own, a form action having nothing left to implement (22/08/2026) [BC-Break]
- Added `Email:DebugPreview`, rendered by the layout above the flashes (22/08/2026)
- Added `Twig\EmailDebugExtension` and its `ui_email_debug_previews()` function (22/08/2026)
- Added `label.email_debug_preview` to the three `ui` catalogues (22/08/2026)
- Added `.email-debug-preview` to `sass/_iframe.scss` (22/08/2026)
- The README documents the single circuit and the component showing it (22/08/2026)
- The `c975l-forms-emails` skill carries the session preview and the component drawing it (22/08/2026)
- Added `EmailDebugExtensionTest` and `DebugPreviewMarkupTest`, and extended `EmailServiceTest` (22/08/2026)

## v1.14.0

A visitor puts aside what the site publishes and finds it back

### ConfigBundle

- `Security\SessionNonceGenerator` renamed `Security\CookieNonceGenerator`: the nonce is held in a cookie rather than in the session, which opened a connection to the session store on every request of every visitor - 14 331 rows in ten days on a site nobody logs into (21/08/2026) [BC-Break]
- Added `EventSubscriber\CspNonceCookieSubscriber`, sending that cookie ahead of NelmioSecurityBundle's signing listener (21/08/2026)
- Added `EventSubscriber\ProfilerNonceSubscriber`, the web debug toolbar nonced with the visit's own nonce rather than one of its own, so it still runs after a Turbo navigation (22/08/2026)
- `csp_nonce` prepended to `nelmio_security.signed_cookie.names`, so only a value this server issued is ever read back (21/08/2026)
- That cookie is named `__Host-csp_nonce` over https, a subdomain no longer able to hand the parent domain a nonce it knows (22/08/2026)
- `hash_algo` stated on that prepended config, its default being deprecated since `nelmio/security-bundle` 3.4 and changing in 4.0 (22/08/2026)
- `RedirectSubscriber` returns before querying for a path that is a static asset, a missing file no longer turning into a database connection (21/08/2026)
- That guard covers `/assets` and `/bundles` only, a removed upload under `/medias` being redirectable again (22/08/2026)
- `Redirect` refuses a `fromPath` the web server answers itself, rather than storing a row that could never fire (22/08/2026)
- Added `CookieNonceGeneratorTest` and `CspNonceCookieSubscriberTest`, `SessionNonceGeneratorTest` removed (21/08/2026)
- Added `Command\SessionsCleanupCommand`, deleting the expired rows of the `sessions` table PHP's own garbage collection only prunes on a dice roll (22/08/2026)
- `ConfigMaintenanceTaskProvider` declares it nightly, so no site adds a cron line for it (22/08/2026)
- Added `SessionsCleanupCommandTest`, and the scaffold's `AnonymousSessionTest`, which fails a site starting a session on a plain visit (22/08/2026)
- `c975LConfigBundleTest` covers the prepended `signed_cookie`, and `ConfigMaintenanceTaskProviderTest` the session cleanup task (22/08/2026)
- Extended `RedirectTest` and `RedirectSubscriberTest` for the static path refused and skipped (22/08/2026)
- `SkillsTest` matches a constant quoted by a skill through its type, `const string FOO` answering no to the plain substring it looked for (22/08/2026)
- Added the `site-address` config, the seller's postal address the model withdrawal form asks for - left empty by a site run from a home (22/08/2026)
- Added `Security\Voter\BackOfficeAccessVoter`, the floor the back office is entered on: any of the two role configs or `ROLE_SUPER_ADMIN`, each held outright, no `role_hierarchy` being shipped (22/08/2026)
- The dashboard is gated by that attribute rather than by `site-role-admin`, an editor standing in the back office at last (22/08/2026)
- `WhatsNewController` and `GuidedProjectController` moved to that same floor (22/08/2026)
- `ConfigCrudController` states the admin bar on its own index and edit, the screen having been unreachable for want of a menu entry until now (22/08/2026)
- `MenuProviderInterface::getMenus()` takes an optional `role`, `MenuBuilder` falling back on the admin bar every entry used to be given (22/08/2026)
- `MenuProvider` and `SocialMenuProvider` name the editor bar on the redirects, "What's new" and url metadata entries (22/08/2026)
- The four alert providers of this bundle carry the role of the screen they link to, an editor no longer reading an alert about something they cannot open (22/08/2026)
- The four guided projects carry theirs too, none being offered to someone its very first step turns away (22/08/2026)
- `OnboardingStepBuilder` skips a menu the current user lacks the role for, as it already skipped a link (22/08/2026)
- The essential-actions checklist is built for an admin alone, its heading no longer standing above nothing (22/08/2026)
- `ConfigCrudController::index()` denies the admin bar itself, the "pick a group" screen never reaching the branch EasyAdmin enforces it in (22/08/2026)
- `CspNonceCookieSubscriber` marks the response carrying the cookie private, a shared cache no longer able to hand one visitor's nonce to everyone (22/08/2026)
- The "What's new" link is barred by `BackOfficeAccessVoter::ACCESS`, the very attribute its screen denies on (22/08/2026)
- `_shortcuts.html.twig` draws the "Shortcuts" heading itself, an editor no longer reading it above an empty grid (22/08/2026)
- Added `BackOfficeAccessVoterTest`, and extended `DashboardControllerTest`, `ConfigCrudControllerTest`, `MenuBuilderTest`, `MenuProviderTest`, `SocialMenuProviderTest`, `OnboardingStepBuilderTest`, `ConfigGuidedProjectProviderTest` and the four alert provider tests (22/08/2026)
- `ShortcutBuilder::getShortcuts()` renamed `getCategories()`, the dashboard drawing one titled row per category rather than a single grid where an export sat next to a toggle (22/08/2026) [BC-Break]
- `_shortcuts.html.twig` takes `categories` and `index.html.twig` passes it `shortcutCategories` (22/08/2026) [BC-Break]
- A category row is not drawn at all when the current user has the role for none of its tiles (22/08/2026)
- Added `ShortcutProviderInterface::CATEGORY_TOGGLE`, the row an admin scans to read what the site currently has switched on (22/08/2026)
- The registration and maintenance tiles moved to that category (22/08/2026)
- A tile whose `active` is true wears `shortcut-tile-warning`, on Bootstrap's subtle warning tokens (22/08/2026)
- The "Update the AI crawlers" tile no longer states `active`, being a one-shot regeneration rather than a toggle (22/08/2026)
- Added `.shortcuts-category` to `management.scss`, the row heading quieter than the "Shortcuts" one above it (22/08/2026)
- Added `label.shortcuts_category_toggle` to the three `config` catalogues (22/08/2026)
- Added `ShortcutTileWarningTest`, and extended `ShortcutBuilderTest`, `ConfigShortcutProviderTest` and `ManagementTargetsTest` (22/08/2026)

### UiBundle

- `ThemeVariablesCssListener` derives the ink of a button from the theme colour behind it, dark mode included (22/08/2026)
- `--button-color`, `--button-link-color`, `--button-icon-invert` and the secondary pair read that derivation, their stated values becoming the fallback (22/08/2026)
- Added `--button-status-color`, `--button-grey-background` and `--button-grey-color`, the inks of the fixed-background variants (22/08/2026)
- `.btn-grey` writes dark (22/08/2026)
- `.slider-play-pause` takes `--button-color` in place of a stated white (22/08/2026)
- The scaffolded `ui.css` carries the three new tokens and the derived defaults (22/08/2026)
- Four cases added to `ThemeVariablesCssListenerTest` (22/08/2026)
- `infinite-scroll.js` stops growing the listing while a scroll heading for an anchor is under way, the button pulling to the bottom of the page having landed in the middle of a listing that kept loading under it (22/08/2026)
- It grows again on the visitor's own scroll - a wheel, a touch or a key - the footer they asked for not being a request for more items (22/08/2026)
- Added `assets/js/scroll-buttons.js` and `<twig:c975LUi:Scroll:Buttons/>`, the two buttons a long page is walked with and the scrolling of every same-page anchor, moved over from SiteBundle: a satellite bundle's listing paused its growth only on a site installing SiteBundle, which neither ShopBundle nor BookBundle requires (22/08/2026)
- `templates/layout.html.twig` renders them, carrying the `id="top"` and the `span id="bottom"` they point at, so an app running without SiteBundle gets them too (22/08/2026)
- Added `sass/_scroll-buttons.scss` and the `--back-pull-background-color` pair, the buttons laid out by the `fade-in`/`fade-out` classes rather than by an inline style a nonced `style-src` drops (22/08/2026)
- Added `@keyframes fadeOut` and the bare `.fade-in`/`.fade-out` classes, until now declared by SiteBundle for a fadeOut only ShopBundle shipped (22/08/2026)
- Added `label.top` and `label.bottom` to the three `ui` catalogues, the buttons' labels having been hardcoded in English (22/08/2026)
- Added `ScrollButtonsControllerTest`, `BottomBarOffsetTest` moved over from SiteBundle, and the pause added to `InfiniteScrollControllerTest` (22/08/2026)
- A compact rating row keeps a tap size that fits the card it sits in: five icons at the coarse-pointer size measured 191px in a 174px catalog card, whose overflow clipped the outer two (22/08/2026)
- The flashes of the layout and of the `Form` component are only read when the visitor can actually hold one, reading `app.flashes` being enough to start a session (21/08/2026)
- That guard is the `ui_can_hold_flash()` function, `{% block flashes %}` now wrapping it rather than the other way round: a theme overriding the block no longer loses it for every visitor without a session (22/08/2026)
- Added `Twig\FlashExtension` and its test (22/08/2026)
- A lit rating icon is painted in the color of its own sign - golden for a star, red for a heart, blue for a thumb, golden for a smile - where the four were the site's one accent and a heart read as a star drawn twice (22/08/2026)
- The color is held in `sass/_rating.scss` beside the glyph and offered to no theme: it belongs to the sign, not to the site, and `--rating-on` still takes every glyph back to one accent for a site wanting that (22/08/2026)
- `ScaffoldThemeTest` lists `--rating-icon-on` among the per-variant properties, with why a value in `:root` would undo the whole thing (22/08/2026)
- A row of cards flushes its last line to the start again, an item carrying a `.width-*` utility no longer keeping the `margin: 0 auto` that ate the line's free space (22/08/2026)
- Added `Entity\Favorite`, `Repository\FavoriteRepository` and `Service\FavoriteService`: a visitor puts anything the site publishes aside, the thing being named (`ownerType`/`ownerId`) rather than related (22/08/2026) [DB-Migration]
- Added `Contract\FavoriteItemProviderInterface` and `Registry\FavoriteItemRegistry`, what turns a stored name and id back into a `CollectionItem` a page can draw (22/08/2026)
- Added `FavoriteItemProviderPass`, so a provider is auto-discovered like every other one of this bundle (22/08/2026)
- Added `Controller\FavoriteController` and its three routes, none of them taking a csrf token nor letting the server open a session (22/08/2026)
- `ui_favorite` prepended to `framework.rate_limiter`, so those routes are never served unlimited (22/08/2026)
- Added `<twig:c975LUi:Favorite:Button>` and the `/favorites` page, an empty shell the list is fetched into (22/08/2026)
- A list started anonymously is handed over to the account on the next authenticated request carrying its token (22/08/2026)
- Added `assets/js/favorite.js`, `favorites.js` and `favorite-store.js`, the token minted on the click and never before (22/08/2026)
- Added `sass/_favorite.scss` and the `--favorite-*` tokens, commented out in `scaffold/assets/styles/themes/ui.css` (22/08/2026)
- The filled wishlist heart is painted in the red the rating heart is read in, rather than in the site's secondary color (22/08/2026)
- Added `Management\LinkableRouteProvider`, offering `/favorites` to SiteBundle's menus - the page was reachable by typing its url alone (22/08/2026)
- Added `assets/js/favorite-count.js`, how many things are put aside painted beside a menu's link to the list, from this browser's own store and never fetched (22/08/2026)
- `favorites.js` announces the count it just fetched under the heart's own event, a navbar whose browser was behind correcting itself on that visit (22/08/2026)
- Added the `favorite_link` block and `Form\Block\FavoriteLinkType`, the menu item leading to that page with the heart and the count - offered from here rather than declared in SiteBundle, the feature being this bundle's (22/08/2026)
- Added `label.category_navigation` to the `ui` catalog, the category that block is picked under (22/08/2026)
- Added `FavoriteLinkBlockTest` (22/08/2026)
- Added `Service\RatingSnippetBuilder`, the schema.org `AggregateRating` fragment a bundle nests in the node of the thing rated (22/08/2026)
- Added `FavoriteServiceTest`, `FavoriteItemRegistryTest`, `FavoriteRepositoryTest`, `FavoriteTest`, `FavoriteControllerTest`, `FavoriteItemProviderPassTest` and `RatingSnippetBuilderTest` (22/08/2026)
- Added `FavoriteButtonControllerTest`, holding the three scripts, the barrel and the component to what each of them assumes of the others (22/08/2026)
- `SkillsTest` matches a typed constant too, this bundle shipping its own copy of that check (22/08/2026)
- The `france/terms-of-sales` model carries a `delivery` section: shipping zone, costs left to the shop, and the thirty-day default of article L 216-1 (22/08/2026)
- Its `withdrawal` section is written for goods too, the fourteen days running from reception, with the digital-content exception of article L 221-28 (22/08/2026)
- Added the `withdrawal.form` sub-section, the model withdrawal form of the annex to article R 221-1 (22/08/2026)
- Added a `returns` section: return delay and costs, condition of the goods, refund of the standard delivery costs (22/08/2026)
- Added a `warranties` section, holding the two boxes of the annex to article D 211-2 verbatim, goods and digital content each in their own sub-section (22/08/2026)
- Added `.legal-boxed`, the frame those two notices and the withdrawal form are only compliant inside (22/08/2026)
- `site-address` added to the `legal_var()` whitelist, printed line by line as the hosting provider is (22/08/2026)
- The Spanish models are written with their accents, `terms-of-sales.es`, `copyright.es` and `privacy-policy.es` carrying none (22/08/2026)
- `MenuProvider` names the editor bar on the media library, the fonts and the site graphics, those screens being the editor's own (22/08/2026)
- `AiAlertProvider` carries the admin bar its own screen states (22/08/2026)
- `FavoriteService::toggle()` answers with the count the drawer will show, a favorited item since unpublished no longer making the badge alternate (22/08/2026)
- `infinite-scroll.js` resumes on `pointerdown` too, a listing paused by the anchor button no longer staying so for whoever drags the scrollbar (22/08/2026)
- Extended `MenuProviderTest`, `AiAlertProviderTest` for those bars, and `FavoriteServiceTest` and `InfiniteScrollControllerTest` for the two above (22/08/2026)
- Added `Controller\Management\EmailDebugShortcutController` and its tile, the `email-debug` config flipped from the dashboard rather than found on the Config screen (22/08/2026)
- That tile sits in `CATEGORY_TOGGLE` and stays warning-colored while the mode is on, nothing leaving the site for as long (22/08/2026)
- `UiShortcutProvider` reads that config for the tile's label and its state (22/08/2026)
- Added `label.email_debug_enable`, `label.email_debug_disable` and their two flashes to the three `ui` catalogues (22/08/2026)
- Added `public/images/up-arrow.png` and `down-arrow.png`, the glyphs `_scroll-buttons.scss` paints, moved over from SiteBundle with the buttons (22/08/2026)
- The `c975l-forms-emails` skill carries the debug preview mode and the tile switching it (22/08/2026)
- Added `EmailDebugShortcutControllerTest`, and extended `UiShortcutProviderTest`, `ManagementTargetsTest` and `LegalModelPlaceholdersTest` (22/08/2026)

## v1.13.4

A rating row is big enough to tap where the pointer is coarse

### UiBundle

- A rating row is drawn at `--rating-size: 2.2rem` under `pointer: coarse` (21/08/2026)
- The enlarged icons are spaced apart, two of them no longer reading as one target (21/08/2026)
- Added `RatingTouchTargetTest` (21/08/2026)

## v1.13.3

A catalog card carries the score without the sentence beside it

### UiBundle

- `<twig:c975LUi:Rating:Rating compact="true"/>` prints the score alone, what a catalog card has room for (21/08/2026)
- A compact widget nobody voted on says nothing, the empty row of icons saying it already (21/08/2026)
- On a scale of 1 the count stays, there being no average to drop it for (21/08/2026)
- The widget takes an `aggregate`, the tally a listing already read, so a row of cards runs no query of its own (21/08/2026)
- `ui_rating()` takes that tally as a fifth argument, reading its own only when none is handed over (21/08/2026)
- Added `.rating-vote--compact`, smaller and centered, its tally holding a line of its own height (21/08/2026)
- Added `<twig:c975LUi:Search:Busy/>`, the sign a live search gives while it is fetching (21/08/2026)
- It keeps its place when silent, so the results below it do not jump (21/08/2026)
- It goes visible through `data-loading="addClass(...)"`: `show` writes a style a `style-src` without `unsafe-inline` refuses (21/08/2026)
- Added `label.searching` and `sass/_search-busy.scss` (21/08/2026)
- Added `video_watch_url`, where a video is watched on its platform rather than played inside the page (21/08/2026)
- It carries the params the embed url does, a YouTube playlist opening at `/playlist?list=` and an unlisted Vimeo keeping its access token (21/08/2026)
- TikTok is addressed by its legacy mobile permalink, its own page needing an author handle nothing stores (21/08/2026)
- Added `--cards-gap`, read by the `.cards` row and by the card widths measured off it (21/08/2026)
- The buttons of a `.flex-center` row group at its center, their `margin: 1em auto` no longer absorbing the free space (21/08/2026)
- `a.btn` states its layout apart from the ink it restates in each state, so hovering a button in a row no longer moves it (21/08/2026)
- Extended `RatingRuntimeTest`, `RatingVoteMarkupTest`, `RatingVoteControllerTest`, `VideoExtensionTest` and `CardMeasureTest` (21/08/2026)
- Added `SearchBusyTest` and `ButtonRowLayoutTest` (21/08/2026)

## v1.13.2

A card carries a mention at the end of its title band

### UiBundle

- `<twig:c975LUi:Card:Card>` takes a `titleAside`, a second mention at the end of the header band - a date, a price, a count (21/08/2026)
- A band holding one lays its two parts out in flex, the title at the start and the mention at the end (21/08/2026)
- Added `--card-header-aside-size` and `--card-header-aside-opacity`, the mention being read second on its line (21/08/2026)
- A card given no mention keeps the markup it had, its title still a direct child of `.card-header` (21/08/2026)
- Added `CardTitleAsideTest` and `CardHeaderAsideTest` (21/08/2026)

## v1.13.1

A guided tour opens the field catalog no menu entry names

### UiBundle

- Added the `ui-form-field-template` guided project, walking the catalog reached from the Forms and Email templates toolbars alone (21/08/2026)
- Added its labels and descriptions to the `ui` translations (21/08/2026)
- Added `RatingRepository::deleteForOwners()`, deleting the ratings of a whole set of things at once (21/08/2026)
- `AiAlertProvider` names its target through `AiAssistantController::INDEX_ROUTE` instead of the route string (21/08/2026)
- Extended `RatingRepositoryTest` and `UiGuidedProjectProviderTest` (21/08/2026)

## v1.13.0

A visitor rates anything the site publishes if desired

### UiBundle

- Added `Entity\Rating`, `Repository\RatingRepository`, `Service\RatingService` and `Controller\RatingController`: a visitor rates anything at all, the rated thing being named (`ownerType`/`ownerId`, the vocabulary `BlockOwnerResolverInterface` already round-trips) rather than related, so no bundle maps a collection it never reads and a listing prints its averages in one query (20/08/2026) [DB-Migration]
- Added `<twig:c975LUi:Rating:Rating ownerType="book" ownerId="…"/>`, `ui_rating()` and `ui_ratings()` — the read-only `Progress:Rating` row with buttons on it, the tally rendered server-side and the visitor's own vote painted over it by the browser, the rated page being public and shared (20/08/2026)
- Added `ui-rating-icon` (star, heart, thumbs-up, face-smile) and `ui-rating-scale` (1 to 10): a scale of 1 is a "like", where the count replaces the average and clicking the icon again takes the vote back (20/08/2026)
- Added `rating.js`, minting its 32-hex token on the click and never before — nothing is stored for a visitor who merely reads the page, which is what keeps the widget out of consent territory; an authenticated visitor is keyed on their account instead, and votes once across their devices (20/08/2026)
- `POST /rating/{ownerType}/{ownerId}` answers `no-store` and takes no csrf token, which would open a session whose `Set-Cookie` the shared cache would hand to the next visitor: a json body, an `Origin`/`Referer` of this site and the new `ui_rating` limiter stand in its place (20/08/2026)
- Added `public/icons/heart.svg` and `thumbs-up.svg`, and moved the `.rating*` rules out of `sass/_progress.scss` into `sass/_rating.scss`, where each offered glyph is masked and the row previews up to the icon under the pointer (20/08/2026)
- Added `RatingTest`, `RatingRepositoryTest`, `RatingServiceTest`, `RatingControllerTest`, `RatingRuntimeTest`, `RatingVoteMarkupTest` and `RatingVoteControllerTest` (20/08/2026)
- The bare `ui_form_submit` route now renders through a page shell (`form/page.html.twig`), the app's own layout first, then SiteBundle's, then this bundle's (19/08/2026)
- Served bare, it carried neither the stylesheet hiding the honeypot field nor the importmap the `captcha` Stimulus controller needs, so every submission was rejected as a bot (19/08/2026)
- That page is never indexed, whichever Page carries the matching "form" Block being the canonical address (19/08/2026)
- `ui_form_fragment` is unchanged and stays bare, being embedded in an already-rendered page (19/08/2026)
- Added `BlockRepository::preloadSlots()`, initializing the slots of a whole tree of blocks, and their medias, in one query per level of depth (19/08/2026)
- A container's slots being a lazy collection, every render walking the tree read one query per block, the leaves included - a joined level only moved the problem one step down (19/08/2026)
- `FontPreloadExtension` reads the fonts once for the whole computation instead of once per font family (19/08/2026)
- Added `BlockRepositoryTest`, and a `FontPreloadExtensionTest` case on the single read (19/08/2026)
- `ImportmapProvider` declares `@c975l/ui-bundle/handlers.js`, PaymentBundle's basket handlers importing the language reading and the translation from it rather than copying them (20/08/2026)
- `<twig:c975LUi:Audio:Audio>` takes a `sticky`, the player then resting against the bottom of the screen (`audio-figure--sticky`, `--audio-sticky-background`) (20/08/2026)
- Added `ea-index-sort.js`, reordering an EasyAdmin index by dragging its rows (20/08/2026)
- Added `infinite-scroll.js`, growing a paginated listing as the visitor scrolls, its next link left as an ordinary link (20/08/2026)
- Added `InfiniteScrollControllerTest` (20/08/2026)
- Added `sort-icon.js`, the move grip shared by both sortables (20/08/2026)
- Added `<twig:c975LUi:Text:Toc>`, a page's summary of anchors - sticky bar of chips on a phone, column from 1200px on (20/08/2026)
- Added `toc.js`, marking the section being read through an `IntersectionObserver` (20/08/2026)
- Added the `--toc-*` tokens, `sass/_toc.scss` and the `toc-target` room-leaving class (20/08/2026)
- Added `label.toc` to the `ui` translations (20/08/2026)
- Added `TocControllerTest`, `TocStyleTest` and `TocMarkupTest` (20/08/2026)
- `field-focus.js` reaches a `CollectionField`, which prints no name of its own, through its entries or its prototype (20/08/2026)
- Donovan's rephrase toolbar now reaches every EasyAdmin `TextEditorField`, not only `TrixEditorType` (20/08/2026)
- Added `AiRephraseThemeTest` (20/08/2026)
- `img.icon` reads `--icon-filter`, an icon laid on the page itself following its ambience instead of keeping its file's black (20/08/2026)
- `.btn .icon` is painted with the button's own ink through `--button-icon-invert`, a template no longer stating a color the site cannot retune (20/08/2026)
- `.slider-sized` states a width and drops its ratio, an `aspect-ratio` left beside the height the controller writes driving the width instead and overflowing the column (20/08/2026)
- A collection's sorting markers are `data-ui-*`, any field naming a group exchanging rows with another naming the same one - `data-block-collection` becomes `data-ui-sort-group`, `data-block-container-id` becomes `data-ui-move-target` (21/08/2026) [BC-Break]
- The drop zone of an empty block collection is visible again, its rule still naming `[data-block-collection]` (21/08/2026)
- A rating's scale is read from `ui-rating-scale` and no longer from the request body, a forged POST having stored a 10 on a site rated out of five and the public average reading "7.3/5" - `RatingService::vote()` drops its `$scale` argument (21/08/2026) [BC-Break]
- A double-click on the vote sends one request only (`rating.js`), and two simultaneous votes answer 409 instead of 500, `uniq_rating_owner_voter` now being caught (21/08/2026)
- A separator splits the average from the vote count under the icons, "4/5 1 avis" having read as "4/51 avis" (`Rating:Rating`, `rating.js`) (21/08/2026)
- The consent cover of a third-party video holds the width of the player it stands in for, the size being applied on connect and bounding the whole component (`video-iframe.js`) (21/08/2026)
- That cover takes a panel's radius and not a button's, a theme drawing its buttons as pills having made it a large oval (`--radius-panel`) (21/08/2026)
- Its message centers itself, a `div { text-align: left }` from another bundle of the stack being an element selector that wins over inheritance (21/08/2026)
- The `confetti` controller reads `document.readyState` instead of subscribing to a `DOMContentLoaded` already fired, a dynamic import mostly connecting after it (21/08/2026)
- The `confetti` controller downloads nothing at all for a visitor asking for less animation, the library keeping its `disableForReducedMotion` besides (21/08/2026)

### ConfigBundle

- The "pick a group" screen of the configs carries the "show sensitive data" button (20/08/2026)
- `ConfigAlertProvider` alerts on a sensitive config filled but undecipherable, until now only logged (20/08/2026)
- Added `ConfigRepository::findSensitiveWithValue()` and `description.config_unreadable` to the `config` translations (20/08/2026)
- `ConfigService::loadAll()` leaves the configuration empty on an unreadable database instead of throwing, a schema predating the current entity having stopped the very commands that would migrate it (19/08/2026)

## v1.12.5

A file the database declares is looked for on the server

### UiBundle

- Added `Management\AbstractDeclaredFilesHealthCheckProvider`, checking that every file a bundle's rows name is under `public/` (19/08/2026)
- Added `Management\MediaFilesHealthCheckProvider` (kind `files-ui`), covering the media library, the site graphics and the uploaded fonts (19/08/2026)
- A declared file missing from the server is an error, never a warning: nothing else says it is gone (19/08/2026)
- The ok row is kept for a file in place, so a re-upload takes its row back to green (19/08/2026)
- `AbstractDeclaredFilesHealthCheckProvider` and `SvgFontsHealthCheckProvider` now declare themselves exhaustive, retiring the url a re-upload renamed (19/08/2026)
- A media carrying a role links to the Site graphics screen, one without to the media library (19/08/2026)
- Added `MediaRepository::findWithFilename()` and `FontRepository::findWithFilename()` (19/08/2026)
- Added `label.health_check_declared_file_found` and `label.health_check_declared_file_missing` in the three locales (19/08/2026)
- Added `MediaFilesHealthCheckProviderTest` (19/08/2026)
- The README documents the check, the watermark section pointing at it (19/08/2026)
- The `c975l-media` skill documents the check and how a bundle extends it (19/08/2026)

### ConfigBundle

- Added `Management\HealthCheckExhaustiveInterface`, for a provider whose run lists the whole of its domain (19/08/2026)
- `HealthCheckRunner` now drops the rows of a kind whose url an exhaustive provider no longer returns (19/08/2026)
- Added `HealthCheckResultRepository::deleteByKindNotInUrls()` (19/08/2026)
- The `files-ui` rows now show in the Health check page's Site section instead of the per-page table (19/08/2026)
- Added five `HealthCheckRunnerTest` cases covering the exhaustive purge (19/08/2026)
- Added three `ConfigCrudControllerTest` cases covering the restricted config guard (19/08/2026)
- The README documents a provider listing the whole of its domain (19/08/2026)
- The `c975l-operations` skill names the shared file check next to the declared urls one (19/08/2026)

## v1.12.4

The CI stops downloading again what it downloaded an hour ago

### The package

- Composer's archive cache is carried from one run to the next (17/08/2026)
- The cache is keyed on `composer.json`, so it owes nothing to a `composer.lock` this package does not version (17/08/2026)
- The workflow watches a push to main and pull requests only, no longer running twice for the same commit (17/08/2026)
- A `concurrency` group cancels a run that the next push has superseded (17/08/2026)
- `COMPOSER_TOKEN` is gone from the setup-php step, the redirect serving the archives dropping the header anyway (17/08/2026)

## v1.12.3

The CI fetches its dependencies as itself rather than anonymously

### The package

- The setup-php step passes `COMPOSER_TOKEN`, so Composer authenticates the archive downloads (17/08/2026)
- The runner's shared IP no longer draws a 429 from codeload.github.com (17/08/2026)

## v1.12.2

The contact graph names the profiles of the same business

### UiBundle

- A flip card and its inner lay out on a track that can shrink, so the cap on the card is applied instead of merely read (17/08/2026)
- A row of flip cards no longer pushes a phone into a horizontal scroll, on any screen narrower than the width a card asks for (17/08/2026)
- `CardMeasureTest` locks the track both boxes of a flip card lay out on (17/08/2026)
- Added `SameAsProviderInterface`, letting a bundle publish the profiles naming the same business (17/08/2026)
- The `contact_details` graph now carries `sameAs`, de-duplicated across providers (17/08/2026)
- Added `SameAsRegistryTest` and `SameAsProviderPassTest` (17/08/2026)
- The README documents contributing the profiles of the same business (17/08/2026)
- The blocks skill documents the contact graph and its provider (17/08/2026)

## v1.12.1

A card says it can be turned where no pointer can hover

### UiBundle

- A flip card draws its turn mark from the start where the primary pointer cannot hover (17/08/2026)
- The query reads the pointer's own ability rather than sniffing the user agent (17/08/2026)
- `FlipCardAccessibilityTest` locks the query and the source order it depends on (17/08/2026)
- The README states the mark being drawn from the start on a touch screen (17/08/2026)
- The compiled emails stylesheet carries the big card tokens (17/08/2026)

## v1.12.0

A bundle explains itself to the agent installing it

### The package

- Eight skills ship in the package, four per bundle, for the coding agents of the sites installing it (17/08/2026)
- `SkillsTest` checks every path, route, config slug, command, class member, Twig function and block kind they quote (17/08/2026)
- Both READMEs document where an agent reads the skills from, the root one pointing at the two (17/08/2026)
- The eslint globals declare `DOMParser`, `FormData` and `XMLHttpRequest` (17/08/2026)

### UiBundle

- Added `TrashableInterface` and `TrashableTrait`, the flag an entity carries to be deleted in two steps (17/08/2026)
- The trait carries its own `isDeleted` column mapping (17/08/2026)
- Method names match SiteBundle's `Page`, which adopts the trait without renaming anything (17/08/2026)
- Added `TrashableTraitTest` (17/08/2026)
- The README documents deleting an entity in two steps (17/08/2026)
- Added the `upload-progress` controller, the bar shown while a form posts its files (17/08/2026)
- The transfer is counted in megabytes, the processing that follows shown as an indeterminate `<progress>` (17/08/2026)
- The submit is taken away for the whole wait, handed back if the network refuses the batch (17/08/2026)
- Added `UploadProgress`, arming the bar on a form and answering its submission (17/08/2026)
- `formAttr()` composes with the controller and the action a form already declares (17/08/2026)
- `redirect()` hands an XHR submission the url instead of a redirect (17/08/2026)
- A response holding the form again replaces the one on screen, errors included (17/08/2026)
- Added `sass/management/_upload-progress.scss` (17/08/2026)
- Added the three progress messages in the three locales (17/08/2026)
- Added `UploadProgressTest` and `UploadProgressControllerTest` (17/08/2026)
- The README documents arming the bar on a form posting files (17/08/2026)
- UiBundle's README named a `BlockOrphanListener`, which is `BlockRemovalListener` (17/08/2026)
- `PaginatorPageSize` remembers the chosen size in the session, one value for every list (17/08/2026)
- An edit link drops the parameter, EasyAdmin regenerating it through a whitelist of its own (17/08/2026)
- A size read from the url wins and becomes the remembered one, a rejected one never stored (17/08/2026)
- `PaginatorPageSizeTest` reads the remembered size, the url winning and a tampered session (17/08/2026)
- ConfigBundle's README states the size being remembered (17/08/2026)

## v1.11.6

A card is sized by how many of it a row holds

### ConfigBundle

- Added the `site-timezone` config entry, a select of the European identifiers plus `UTC`, defaulting to `Europe/Paris` (15/08/2026)
- Added `TimezoneListener`, applying it to Twig on every request and console command (15/08/2026)
- Only the reading moves, PHP going on writing in its own timezone (15/08/2026)
- An identifier `DateTimeZone` does not know is left alone rather than thrown (15/08/2026)
- Added the site timezone label and description in the three locales (15/08/2026)
- Added `TimezoneListenerTest` (15/08/2026)
- The README states the site timezone (15/08/2026)

### UiBundle

- Added `BlockCardSizeChoiceType`, the **Taille** field picking how many cards go to the row (15/08/2026)
- `card` and `flip_card` carry it, both reading the very same width tokens (15/08/2026)
- Added `--card-width-big` and `--card-title-size-big`, read off the page measure like the other two (15/08/2026)
- Added the `.card--big`, `.flip-card--compact` and `.flip-card--big` rules measuring themselves off those tokens (15/08/2026)
- `Card.html.twig` and `FlipCard.html.twig` match the stored size against the two named steps (15/08/2026)
- The scaffolded theme comments the two new tokens beside the others (15/08/2026)
- `BlockType` drops from a kind switch whatever key the new kind declares no field for, the save going through on the first try (15/08/2026)
- It takes the medias and the slots of the kind left behind off the form too, the block keeping them (15/08/2026)
- Added `BlockType::dropForeignEntryKeys()`, read off the collection's own prototype (15/08/2026)
- Switching a kind posts what the shared fields hold, the new sub-form coming back filled in (15/08/2026)
- Added `appendBlockField()` in `block.js`, `block-duplicate.js` reading it instead of its own copy (15/08/2026)
- `BlockClassChoiceType` holds widths alone, its `box-shadow` choice having changed no pixel (15/08/2026)
- It carries its own `label.block_width` pair rather than the media screen's (15/08/2026)
- `card` and `cta_band` carry the free **Site CSS classes** field (15/08/2026)
- A `text_section`'s content is optional, a title alone being a section heading (15/08/2026)
- `BlockFixtureProvider` shows the card in its three sizes (15/08/2026)
- Added the card size and block width labels in the three locales (15/08/2026)
- Added `BlockCardSizeChoiceTypeTest`, `CardSizeTest` and `BlockKindSwitchTest` (15/08/2026)
- `CardMeasureTest` reads the third width, `BlockClassChoiceTypeTest` the widths alone and its own labels (15/08/2026)
- `CardTypeTest`, `CtaBandTypeTest`, `FlipCardTypeTest` and `TextSectionTypeTest` read the fields added to each (15/08/2026)
- The README states the card size, the kinds carrying the site classes and the width list (15/08/2026)

## v1.11.5

A site now says what an intrusion would leave behind

### ConfigBundle

- Added `IntrusionHealthCheckProvider`, the weekly check looking for the traces an intrusion leaves (15/08/2026)
- Its uploads row reports an executable file under any directory declared for the backup (15/08/2026)
- Its code row compares the working tree to the repository it was deployed from (15/08/2026)
- Its accounts row counts the holders of `site-role-admin` against the previous run (15/08/2026)
- `IntrusionHealthCheckProvider` reads the working tree with `--untracked-files=no`, what the site writes no longer reading as modified code (15/08/2026)
- The upload directory walk now skips a sub-directory it cannot read instead of aborting (15/08/2026)
- The uploads row caps its stored file list at 50, as the code row does (15/08/2026)
- Added `EnvironmentProbe`, reading what the PHP process actually allows (15/08/2026)
- Added `PrivilegedAccountCounter`, counting the accounts holding a given role (15/08/2026)
- Added `CapabilitiesStatusProvider`, the `capabilities` section of the status report (15/08/2026)
- Added `RegistrationStatusProvider`, the `registration` section of the status report (15/08/2026)
- The registration shortcut and its toggle find the register `Form` by its action, not by its name (15/08/2026)
- Added `UserFormSeeder::REGISTER_ACTION` (15/08/2026)
- `ScaffoldInstaller` gitignores `public/sitemap*`, alongside the other files regenerated on every deployment (15/08/2026)
- `HealthCheckRunner` isolates each provider, one throwing no longer discarding the rows of the others (15/08/2026)
- Added `HealthCheckResultRepository::findLatestByUrlAndKind()`, read by the privileged accounts row instead of the whole table (15/08/2026)
- No hosting provider is named in the comments anymore, a capability standing on its own (15/08/2026)
- Added the nine intrusion check labels in the three locales (15/08/2026)
- `ConfigsJsonTest` locks every label on the key its own slug derives (15/08/2026)
- `ScaffoldInstallerTest` and `HealthCheckRunnerTest` read the gitignored sitemaps and the isolated provider (15/08/2026)
- Added `IntrusionHealthCheckProviderTest`, `CapabilitiesStatusProviderTest`, `RegistrationStatusProviderTest`, `EnvironmentProbeTest` and `PrivilegedAccountCounterTest` (15/08/2026)
- The README states the intrusion check, the two status sections and the lookup by action (15/08/2026)

### UiBundle

- Added `HasCssClassesFieldTrait`, the free "Site CSS classes" field a kind opts into (15/08/2026)
- `text_readmore`, `text_hook`, `text_section`, `article` and `alert` carry it (15/08/2026)
- `BlockExtension` wraps a block in the classes stored for it, keeping only valid names (15/08/2026)
- A `text_readmore` laid out as a page-level block takes the page's own step (15/08/2026)
- Added `PdfThumbnailHealthCheckProvider`, listing the PDF medias whose thumbnail is missing (15/08/2026)
- Added `MediaRepository::findPdfs()` (15/08/2026)
- Added `VichTranslationDomainExtension`, the Vich delete and download labels resolved in their own domain (15/08/2026)
- `Portfolio/Grid.html.twig` names its own loop variable, the section no longer closing on the last project's tag (15/08/2026)
- A project's link opens a tab of its own only when it leaves the site, in the grid and in the `collection` block (15/08/2026)
- `configs.json` labels are the keys their own slugs derive, renamed in the three locales (15/08/2026)
- `label.health_check_pdf_thumbnail_missing` no longer promises a thumbnail on a server that cannot generate one from the web, in the three locales (15/08/2026)
- No hosting provider is named in the comments and in the README anymore (15/08/2026)
- Added the site classes and PDF thumbnail labels in the three locales (15/08/2026)
- `ConfigsJsonTest` locks every label on the key its own slug derives (15/08/2026)
- `SectionRhythmTest` reads the fold's step and that it is not written as a child selector (15/08/2026)
- `BlockExtensionTest` reads the wrapper, its filtering and its absence (15/08/2026)
- Added `HasCssClassesFieldTraitTest`, `VichTranslationDomainExtensionTest`, `PdfThumbnailHealthCheckProviderTest` and `PortfolioLinkTargetTest` (15/08/2026)
- The README states the site CSS classes field, the PDF thumbnail check and the link rule (15/08/2026)
- The `collection` block prints its eyebrow and its "see all" link in both presentations, the default one no longer dropping them (15/08/2026)
- `Collection/Grid.html.twig` heads both variants the same way, `.collection-grid__head` joining the portfolio head's own rule (15/08/2026)
- Added the `collection` block's **Display order** field, drawing its items at random over the whole source before the limit applies (15/08/2026)
- `CollectionBlockCacheTagProvider` vetoes the entry of a block drawing at random, its items keeping their own (15/08/2026)
- Added the display order labels in the three locales (15/08/2026)
- Added `CollectionGridHeadTest`, and the random draw read by `CollectionRuntimeTest` and `CollectionBlockCacheTagProviderTest` (15/08/2026)
- The README states the display order and the head shared by both variants (15/08/2026)

## v1.11.4

A share image says what it shows, and its thumbnail keeps its room

### UiBundle

- `layout.html.twig` writes `og:image:alt`, from the media's own alternative text or from the template's `ogImageAlt` (14/08/2026)
- It writes `og:image:width`/`og:image:height` for a media holding both (14/08/2026)
- The og-image chain keeps the media it picked, the url being written once for its three branches (14/08/2026)
- `SiteGraphicCrudController` offers an alternative text on the `og-image` role alone (14/08/2026)
- `OgImageType` embeds that alternative text beside the upload (14/08/2026)
- `MinimalLayoutTest` reads the three tags and the order of the chain (14/08/2026)
- `SiteGraphicCrudControllerTest` reads the field on the og-image, its absence elsewhere, and an edit context carrying no row (14/08/2026)
- Added `OgImageTypeTest` (14/08/2026)
- The README states what the layout writes beside `og:image` (14/08/2026)

### ConfigBundle

- Added `_sharing_debugger.html.twig`, the note on the preview a network caches from the first share on (14/08/2026)
- The url metadata edit screen carries it, linking Facebook's debugger on the row's own url (14/08/2026)
- Added `label.sharing_debugger_help` and `label.sharing_debugger_link` in the three locales (14/08/2026)
- Added `SharingDebuggerTest` (14/08/2026)
- The README states how another screen includes that note (14/08/2026)

## v1.11.3

A card's key figures stay centered when they come in an odd number

### UiBundle

- `Card.html.twig` gives a figure with no neighbour on its line the whole line (14/08/2026)
- The last figure of an odd count, and the one a `wide` entry follows, no longer flush right (14/08/2026)
- `CardStatVariantTest` reads both cases, and the even rows that must keep their two columns (14/08/2026)
- The README states which figures take the whole line on their own (14/08/2026)

## v1.11.2

A features section whose card carries no icon renders again

### UiBundle

- `Card.html.twig` reads its `icon` prop as a list, an empty one being no icon (14/08/2026)
- A card nested by `Section:Features` without an icon no longer reads `icon[0]` on an empty list (14/08/2026)
- Added `CardIconTest`, reading the card against `[]`, `""` and the four values of a real icon (14/08/2026)

## v1.11.1

A card with an image or a link shows its text again

### UiBundle

- `Card.html.twig` and `CollectionItem.html.twig` read their content under a name of their own (14/08/2026)
- The nested card's own empty `content` prop used to take its place, the card coming out with nothing but its title (14/08/2026)
- Added `BlockAdapterNestedContextTest`, reading what every block adapter nests against what the card declares (14/08/2026)
- The README states that a nested component's props shadow the caller's variables of the same name (14/08/2026)

## v1.11.0

Matomo lives where its legal text is written

### The package

- `.gitattributes` keeps the dev-only paths out of the dist archive Composer downloads, `README.md` and `UPGRADE.md` staying in (14/08/2026)

### UiBundle

- `<twig:c975LUi:Analytics:Matomo />` renders the tracking snippet, carrying its own `site-enable-matomo` guard (14/08/2026)
- The three `site-matomo-*` keys are declared here, next to `site-enable-cookie-consent` in the `analytics` group (14/08/2026)
- The cookies model offering Matomo's opt-out link no longer reads a key declared by SiteBundle (14/08/2026)
- `matomo` is registered as a lazy Stimulus controller (14/08/2026)
- The minimal layout renders the component and preconnects the instance's origin, under that same `site-enable-matomo` guard (14/08/2026)
- A flip card carries a turn mark in its corner, summoned by the pointer and by the keyboard, and only on a card whose toggle is live (14/08/2026)
- `.flip-card` is measured off `--card-width` like `.card`, capped on its own box, instead of a width written down (14/08/2026)
- The media library is opened to `site-role-editor`, `Action::NEW` staying at `ROLE_SUPER_ADMIN` (14/08/2026)
- A media still used by a block, a page or a site graphic is not deleted, the flash naming the "used in" list that says where (14/08/2026)
- The README states what refuses a media deletion, and where that list comes from (14/08/2026)
- Added the "Corriger ce qu'une image dit d'elle-même", "Ajuster un document légal" and "Faire reformuler un texte par Donovan" guided projects (14/08/2026)
- Each guided project states the role its own screen is gated by, so none is offered to someone its first step turns away (14/08/2026)
- The seven guided projects are renumbered at a step of 3, leaving the range's last two values free (14/08/2026)
- `AiAssistantController::INDEX_ROUTE` names the route `MenuProvider` and the guided project both point at (14/08/2026)

### ConfigBundle

- A sensitive value that cannot be decrypted is served empty and logged, instead of taking the whole configuration down with it (14/08/2026)
- `VaultEncryptor::decrypt()` tells a wrong key from a legacy value that did not come back as text, which no key will bring back (14/08/2026)

## v1.10.4

Sensitive values move to authenticated encryption

### ConfigBundle

- `VaultEncryptor` writes `aes-256-gcm`, whose tag is verified at decryption, where `aes-256-cbc` authenticated nothing (14/08/2026)
- A value altered in the database is refused rather than decrypted to whatever those bytes held (14/08/2026)
- Values written before this are read as they stand, under the same `C975L:` prefix and the same key (14/08/2026)
- The current format is tried first, the legacy one only as a fallback, a legacy payload being unable to satisfy the tag (14/08/2026)
- Added `VaultEncryptor::isLegacyEncrypted()`, which tells the two formats apart by reading them (14/08/2026)
- `c975l:config:encrypt-sensitive` converts a legacy value, keeping the key and the secret it holds (14/08/2026)
- It writes nothing on a site already converted, so it belongs in a deployment rather than in a manual pass (14/08/2026)
- A value the environment's key cannot read is reported and left alone, the command still exiting `0` (14/08/2026)
- Its summary counts conversions apart from encryptions (14/08/2026)
- The README states the conversion and `UPGRADE.md` what to run where, no key being generated and `.env.local` untouched (14/08/2026)

## v1.10.3

A wrong vault key can no longer decrypt to garbage

### ConfigBundle

- `VaultEncryptor::decrypt()` rejects a plain text that is not valid UTF-8, `aes-256-cbc` authenticating nothing and a wrong key satisfying its padding once in 256 (14/08/2026)
- Until now those random bytes were written over the encrypted value as the setting itself, its `sensitive` flag falling with them (14/08/2026)
- `VaultEncryptorTest` carries a payload picked for passing the padding under another key, the case having no other way to be reproduced (14/08/2026)
- `ConfigServiceTest::testLoadDefaultConfigKeepsSensitiveFlagWhenValueCannotBeDecrypted` no longer fails one run in 250 (14/08/2026)

## v1.10.2

A card's measure follows the page it is laid on

### UiBundle

- `--card-width` and `--card-width-compact` are read off `--section-wrap-max-width` and `--section-wrap-gutter` rather than written down, a page framed tighter than the default still holding three cards and six compact ones (14/08/2026)
- Both resolve to their former `380px` and `190px` on the `1440px` default measure (14/08/2026)
- `.card` takes `max-width: 100%`, a row nested in a slot narrower than the page measure no longer pushing past it (14/08/2026)
- A card no longer overflows its own wrap under a `420px` viewport, the floor the `clamp()` carried being gone with it (14/08/2026)
- The scaffolded `ui.css` offers both tokens in their computed form (14/08/2026)
- Added `CardMeasureTest`, the derivation, the gaps taken off the measure and the caps the default page still resolves to (14/08/2026)
- The README's "The `.cards` row" states what decides how many fit on a line (14/08/2026)

## v1.10.1

The suite runs the same on any machine, and skips nothing

### The package

- The scaffold suite leaves `tests/Deploy` out, its two tests answering for a deployed site rather than for this repository (14/08/2026)

### ConfigBundle

- `OffsiteSynchronizer::findRclone()` is `protected`, as `run()` already was (14/08/2026)
- `BackupOffsiteCommandTest` overrides it, a host without rclone no longer asserting on a command the guard clause never built (14/08/2026)
- `DeployWorkflowTest` drops its skip, the site it runs in always having a kernel to read the workflows with (14/08/2026)

## v1.10.0

The block cache reaches the containers and the collections

### The package

- `require` declares `symfony/twig-bundle`, `twig/twig` and `symfony/ux-twig-component`, until now only transitive (13/08/2026)
- Twig extensions declare their functions and filters with `#[AsTwigFunction]`/`#[AsTwigFilter]`, no `AbstractExtension` left (13/08/2026) [BC-Break]
- A site extending one of them to add a function of its own carries `getFunctions()`/`getFilters()` no longer, and declares its addition with the same attributes (13/08/2026)
- Their tests read what TwigBundle assembles, through `AttributeExtension` (13/08/2026)
- `#[Autowire]` names a container parameter with `param:` rather than with a `%…%` string (13/08/2026)
- A command's `setHelp()` moves into `#[AsCommand(help: …)]` (13/08/2026)
- A repository declares the entity it manages, `@extends ServiceEntityRepository<…>` (13/08/2026)
- `rector.php` binds its Symfony rules with `withComposerBased()`, the versioned sets being gone since Rector 2.6.2 (13/08/2026)
- Rector caches in `.rector.cache`, inside the repository rather than in the directory shared by every repository on the machine (13/08/2026)
- The `rector` script drops `--clear-cache`, `bin/ci.sh` leaving that cache out of its copy instead (13/08/2026)
- `bin/ci.sh` installs the four quality tools fresh, in the latest release the CI always takes (13/08/2026)
- It prints the versions it ran with, and runs on a private `TMPDIR` (13/08/2026)
- The README states that those tools are installed rather than borrowed from the machine (13/08/2026)
- `eslint.config.mjs` declares the `ResizeObserver` global (13/08/2026)

### ConfigBundle

- A `Redirect` whose `fromPath` and `toUrl` both end with `*` carries the end of the url over, `/character/*` → `/personnages/*` sending `/character/tuor` to `/personnages/tuor` (13/08/2026)
- A destination without the `*` keeps folding the whole tree onto one url, the two being what a renamed tree and a removed one respectively need (13/08/2026)
- A renamed url tree is a handful of rows rather than a redirecting route per old url, and it is edited in the back-office rather than deployed (13/08/2026)
- The help of "Chemin source" and "URL de destination" states the pairing, in the three catalogues (13/08/2026)
- Added five cases to `RedirectSubscriberTest`, the tail carried over, folded, left alone on an exact row, and the row requested as written (13/08/2026)

- Added `UrlMetadata`, what an url says of itself when no entity carries it — a listing, a filtered listing, a tool page (13/08/2026) [DB-Migration]
- Keyed by the path and not by the route name, one route serving many listings with different things to say (13/08/2026)
- Holds the `title`, the `summarySocialNetwork` and the `ogImage` a `Page` already holds, under the same names (13/08/2026)
- Added `UrlMetadataResolver`, which reads the whole table on the first lookup of a request rather than one query per path (13/08/2026)
- A table not created yet resolves to nothing rather than failing, a site updated before it migrates keeping its pages (13/08/2026)
- Added the `url_metadata()` Twig function, the row of the page being rendered or of a path stated explicitly (13/08/2026)
- Added `UrlMetadataCrudController`, listed under "Social" beside the social links rather than under "Gestion" (13/08/2026)
- No row is ever created by hand there: `Action::NEW` is disabled and the path is shown but not editable (13/08/2026)
- Added `UrlMetadataProviderInterface`, which a bundle implements to have its own urls listed, ready to be described (13/08/2026)
- What it declares is which urls exist and never what they say: the paths are structure, the sentences are content and live in the base (13/08/2026)
- Added `UrlMetadataSynchronizer` and the `c975l:url-metadata:sync` command, to run at deployment beside `c975l:sitemaps:create` (13/08/2026)
- It only ever creates empty rows, and a row whose url is no longer declared is reported rather than deleted (13/08/2026)
- Added `SocialMenuProvider`, which declares that section here too so it exists on an app running without SocialBundle (13/08/2026)
- Ships `label.social` in a `social` catalogue of its own for that case, SocialBundle's merging with it when installed (13/08/2026)
- Ships `label.management` in a `site` catalogue of its own too: the section it has always declared was named by SiteBundle alone, and showed as a raw key on an app running without it (13/08/2026)
- The layout fills only what the rendering template left unsaid, so an entity always speaks first (13/08/2026)
- Added `UrlMetadataExportProvider` and `UrlMetadataImportProvider`, so the rows travel with "export sync all" as the redirects do (13/08/2026)
- A row is matched on its path and never on an id, and its share image is carried into the archive beside it (13/08/2026)
- Added `UrlMetadataTest`, `UrlMetadataResolverTest`, `UrlMetadataSynchronizerTest`, `UrlMetadataExportProviderTest`, `UrlMetadataImportProviderTest`, `UrlMetadataCrudControllerTest` and `SocialMenuProviderTest` (13/08/2026)
- Added `UrlMetadataExtensionTest` and `UrlMetadataCrudControllerTest` (13/08/2026)
- Added the `config-url-metadata` guided project, one url's description walked from the screen to the save (14/08/2026)
- It highlights `.action-edit` and never `.action-new`, no row being created by hand there (14/08/2026)
- `ManagementTargetsTest` reads `SocialMenuProvider` and `LinkableRouteProvider` too (14/08/2026)
- The README states the `*` pairing, the `site_url_metadata` rows and `UrlMetadataProviderInterface` (14/08/2026)

- Added `UserCreationNotifier`, a plain-text notice to the site's own `email-to` whenever an account is created (13/08/2026)
- It is written in `kernel.default_locale`, the owner reading it rather than the visitor (13/08/2026)
- Its subject carries the site name, several sites otherwise sending one inbox the same line (13/08/2026)
- Added the `user-creation-notification` config, `bool` of the `email` group, on by default (13/08/2026)
- A notification that fails never fails the registration, its result being dropped (13/08/2026)
- Added `UserCreationNotifierTest`, and `UserRegistrarTest` covers the new call (13/08/2026)

### UiBundle

- `layout.html.twig` reads `summarySocialNetwork` where it read `description`, the name SiteBundle's own layout and `Page` column use (13/08/2026) [BC-Break]
- The two layouts being interchangeable, a template posing the old name lost its `<meta name="description">` and its `og:description` under one of them and kept them under the other, silently (13/08/2026)
- It falls back on the url's own `UrlMetadata` row for the title, the summary and the share image (13/08/2026)
- It reduces the summary with `plain_text` before writing the two metas, as SiteBundle's layout does (13/08/2026)
- Added the `MinimalLayoutTest` case pinning that reduction (13/08/2026)

- A `text_section` lays its copy on `--text-section-max-width`, the page's measure by default, a site whose sections are prose pointing it at `--reading-max-width` (13/08/2026)
- A `progress_tracker` takes the page's measure and the step above it, where it ran the full frame and sat flush against its neighbours (13/08/2026)
- `collection_entry` takes that same step, and drops it as a column slot like every other section-level kind (13/08/2026)

- Added the `collection_entry` kind, one item of a source under its own section head (13/08/2026)
- Its item is picked `first`, `last`, or by the slug it carries (13/08/2026)
- Nothing at all is rendered when nothing answers, a head standing over no item otherwise (13/08/2026)
- A source declares its own total with `count`, read into the `limit` field's help rather than built item by item (13/08/2026)
- A source names the template drawing its items with `itemTemplate`, the built-in card being the default (13/08/2026)
- That path renders live, the named template answering for its own caching (13/08/2026)
- A source declares the tags its items are cached under with `cacheTags`, none meaning rendered live (13/08/2026)
- `flex_columns`, `flex_column`, `block_group`, `section_cards`, `video_grid`, `collection` and `collection_item` are cacheable (13/08/2026)
- Added `BlockCacheTagResolver`, the one place answering whether a block is cached and under which tags (13/08/2026)
- A container's entry carries every slot's own `block_{id}`, its html holding theirs verbatim (13/08/2026)
- A slot that cannot be cached takes its whole container out of the cache with it (13/08/2026)
- `BlockCacheInvalidationListener` walks up the chain, a changed slot reaching every container above it (13/08/2026)
- Added `CollectionBlockCacheTagProvider`, vetoing a `collection` whose source declares no tag or whose block carries a `detailPage` (13/08/2026)
- Added `BlockRenderContext`, which takes a whole render out of the cache - an editor's preview (13/08/2026)
- `CollectionItem` takes a free `data` array, merged into the rendered item's own block data (13/08/2026)
- The runtime's own keys win over it, a source being unable to displace `title`, `detailUrl` or `variant` (13/08/2026)
- `CollectionItem.html.twig` opens the card's stat variant on `eyebrow`, `rating`, `stats` and `class` (13/08/2026)
- A stat card nests its item's own content bare, the card already carrying its alignment (13/08/2026)
- `_flip-card.scss` declares `.flip-card-face`, `.flip-card-back` and `.flip-card-inner` once each (13/08/2026)
- Added the `readmore` controller, taking the "read more" link away when the text ends inside the folded measure (13/08/2026)
- Added `.readmore--complete`, the class it adds after measure, and the `ReadmoreStyleTest` case locking it (13/08/2026)
- `Readmore.html.twig` carries that controller and its two targets, the fold itself staying the stylesheet's (13/08/2026)
- `BlockExtension` substitutes the csp nonce on the outermost render only, a slot's marker travelling into its container's cache entry intact (13/08/2026)
- Its cache tags are resolved inside the cache callback, so a hit no longer hydrates the whole slot subtree (13/08/2026)
- A block vetoed by `BlockCacheTagResolver` is rendered with `$save = false` rather than before the pool is reached (13/08/2026)
- `BlockCacheTagResolver` skips a slot already met on the way down, a cycle no longer spinning until the fatal (13/08/2026)
- `Card:Card` declares its props, what a caller writes on top reaching the card's own element through `attributes` (13/08/2026)
- `sass/_tokens.scss` declares its defaults on `:root, [data-theme]`, a derived token otherwise descending already computed (13/08/2026)
- The scaffolded `themes/ui.css` opens on that same pair, a value set on `:root` alone losing to the bundle's default (13/08/2026)
- A card carrying `data-theme` therefore opens a color ambiance of its own (13/08/2026)
- The block edit overlay is a round icon button, its translated label moving to `aria-label` (13/08/2026)
- Added `SvgFontsHealthCheckAdviceProvider`, naming the menu entry that vectorizes an SVG's text (13/08/2026)
- Added `LegalModelDriftHealthCheckAdviceProvider`, saying that a drifted document waits for a decision nobody takes in the reader's place (13/08/2026)
- `GalleryShowcaseProvider` shows the new kind, its own item drawn from the showcase's fixtures (13/08/2026)
- Added `ReadmoreControllerTest`, locking the barrel, the two targets and the class the controller alone adds (13/08/2026)
- Added `CollectionItemStatVariantTest` and `CollectionEntryMarkupTest` (13/08/2026)
- Added `CardAttributesTest`, `CollectionEntryTypeTest` and the two advice providers' own cases (13/08/2026)
- Added `BlockCacheTagResolverTest`, `CollectionBlockCacheTagProviderTest` and `BlockRenderContextTest` (13/08/2026)
- `CollectionSourceRegistryTest` covers `count()` and `cacheTags()`, `CollectionTypeTest` the total in the help, `BlockCacheTagRegistryTest` the resolver's veto (13/08/2026)
- Added `CacheInvalidatorInterface`, an app's own cache emptied along with the block render cache (14/08/2026)
- One failing invalidator no longer keeps the others from running, the failures being reported at the end (14/08/2026)
- The `ui-media` guided project becomes `ui-site-graphic`, the favicon and the logos being what nothing else puts in place (14/08/2026)
- Its second step highlights the button of the graphic still missing, `.action-new` pointing at the form that screen spares the user (14/08/2026)
- Added the `ui-font` guided project, the bulk import of a whole family (14/08/2026)
- `UiGuidedProjectProviderTest` reads the actions this bundle's own controllers declare too (14/08/2026)

## v1.9.1

A segmented tracker counts what the progress bar could only measure

### UiBundle

- Added the `progress_tracker` block kind, a segmented count against a known total (12/08/2026)
- Its eyebrow, title and note are optional, the two figures are not (12/08/2026)
- Both the form and the template clamp the count to `ProgressTrackerType::MAX_SEGMENTS` (12/08/2026)
- The segment is rectangular by default, retuned through `--tracker-segment-clip`, `--tracker-segment-on` and `--tracker-segment-off` (12/08/2026)
- Added the `Progress:Rating` component, a score read as a row of stars (12/08/2026)
- Its star is masked rather than served as an `<img>`, so it takes the theme's accent (12/08/2026)
- The row is retuned through `--rating-on`, `--rating-off` and `--rating-size` (12/08/2026)
- Added `public/icons/star.svg`, the same glyph as a file an editor can pick (12/08/2026)
- `Card:Card` takes a "stat" variant, opened by any of `src`, `eyebrow`, `rating` or `stats` (12/08/2026)
- A `rating` of `0` opens it too, an empty field being what means "not rated" (12/08/2026)
- The card's picture is linked to its own `titleUrl` when it has one (12/08/2026)
- The two figures of one line meet in the middle, the column being written on the cell by the component (12/08/2026)
- A figure marked `wide` takes the whole line and restarts the columns behind it (12/08/2026)
- A card nesting nothing no longer raises on its undefined `content` (12/08/2026)
- The eyebrow reads `--card-eyebrow-color` as a fallback, never as a `:root` token (12/08/2026)
- The card's width and title size move to `--card-width` and `--card-title-size` (12/08/2026)
- Added `.card--compact`, the same card at `--card-width-compact` (12/08/2026)
- The `.card-header` states its own `font-size` rather than inheriting the theme's `h1`-`h6` (12/08/2026)
- The `ThemeStylesheetProvider` the README hands a site contributes a sheet through its `.min.css` twin when one exists (12/08/2026)
- `Video:Video` now sets `playsinline`, as `Hero` and `Slider` already did (12/08/2026)
- Added the `progress_tracker` fixture to `BlockFixtureProvider` (12/08/2026)
- Added `ProgressTrackerTypeTest` and `TrackerSegmentsTest`, which lock the two clamps against each other (12/08/2026)
- Added `RatingScaleTest`, locking both ends of the star scale (12/08/2026)
- Added `CardStatVariantTest`, locking what opens the variant and what a plain card still renders (12/08/2026)
- Added `VideoPlaysInlineTest`, locking `playsinline` on every `<video>` this bundle writes (12/08/2026)

## v1.9.0

Rector joins the quality gate, and both bundles catch up

### The package

- Added the `rector` script, `rector process --dry-run` over both bundles' `src/`, `tests/` and `scaffold/` (11/08/2026)
- It closes `composer qa` before the tests, and the CI workflow installs the tool alongside phpstan and php-cs-fixer (11/08/2026)
- `rector.php` carries the same sets a site gets from `SymfonyMigrate.sh`, `scaffold/` included (11/08/2026)
- What the scaffold leaves unmodernised is rewritten in the application, where it stops matching the hash `ScaffoldInstaller` recorded (11/08/2026)
- `withPhpSets()` reads its target from `composer.json`, the bundles and the sites both requiring `>=8.4` (11/08/2026)
- `ReadOnlyClassRector` is skipped, a readonly class closing the override door these bundles leave open (11/08/2026)
- `AddParamBasedOnParentClassMethodRector` too, the CrudControllers' variadic `$args` being deliberate (11/08/2026)
- Class constants carry their type (11/08/2026)
- A method redeclaring a parent's carries `#[\Override]` (11/08/2026)
- `new` is chained without wrapping parentheses (11/08/2026)
- A string callable passed to `array_map()` becomes a first-class callable (11/08/2026)
- A property assigned only in the constructor is `readonly`, an anonymous test class too (11/08/2026)
- A `foreach` returning on the first match becomes `array_any()` (11/08/2026)
- A class holding `__toString()` declares `\Stringable` (11/08/2026)
- An unused `catch` variable is dropped (11/08/2026)
- Nested `dirname()` calls take a depth argument (11/08/2026)
- Updated the README's quality checks section (11/08/2026)

### ConfigBundle

- The stale-import reminder points at `App\Service\ThemeStylesheetProvider` to be checked, instead of stating what it holds (11/08/2026)
- A sheet named under `assets/styles/` counts as imported only when a quote or a closing parenthesis ends it (11/08/2026)

### UiBundle

- The README's `ThemeStylesheetProvider` example globs `assets/styles/*.css` and `assets/styles/themes/*.css` rather than listing two files (11/08/2026)
- `StylesheetCacheWarmer` rewrites the relative `url()` of every concatenated sheet, an app asset through AssetMapper (11/08/2026)
- It hoists the `@import` rules to the head of the compiled stylesheet (11/08/2026)

## v1.8.4

The scaffold writes PHP 8.4, dropping the parentheses around `new`

### ConfigBundle

- `new User()`, `new Schedule()`, `new UserChecker()` and `new MaintenanceSchedule()` are chained without wrapping parentheses (11/08/2026)
- The scaffold's promoted constructor properties are `readonly` (11/08/2026)
- `ChangePasswordFormTypeTest::getExtensions()` carries `#[\Override]` (11/08/2026)

## v1.8.3

White ink stays white on a dark ground, whatever the color mode

### UiBundle

- A hero opening on a background media writes a stated `#fff`, where `var(--white)` left its title near-black in dark mode (11/08/2026)
- The "primary" and "dark" section flats, and the derived tones mixed out of them, do the same (11/08/2026)
- The primary CTA sitting on one of those flats keeps its white background, its label being picked to read on white (11/08/2026)
- A card's header band and the paginator's current page chip write that same stated `#fff` (11/08/2026)
- `CardAccentTest` reads the band's new fallback, the four light hues keeping their own `#000` (11/08/2026)
- README states the rule under "Colored backgrounds" and under the card accents (11/08/2026)
- Added `DarkGroundInkTest` (11/08/2026)
- A hero's background media and a banner's picture are dropped when printing, black ink not being readable on a photograph (11/08/2026)
- The room a background video was given goes with it, or the printed page opens on a blank half-page (11/08/2026)
- Added `PrintedBackgroundMediaTest` (11/08/2026)

## v1.8.2

A minified sheet keeps its rules, "*/*" no longer read as a comment

### UiBundle

- `StylesheetCacheWarmer` no longer drops everything between a license header and the next comment (11/08/2026)
- A comment is matched whole and kept or dropped in a callback, instead of being excluded by the pattern (11/08/2026)
- Added `StylesheetCacheWarmerTest::testWarmUpKeepsARuleStartingWithTheUniversalSelectorRightAfterAHeader` (11/08/2026)

## v1.8.1

The banner writes no CSS: its picture is an image, its height a step

### UiBundle

- `banner_title` paints its picture as an `<img>` instead of a `background-image` (11/08/2026) [BC-Break]
- The picture carries the media's own `alt`, replacing the `role="img"`/`aria-label` the banner wore (11/08/2026)
- Its height is one of three steps, on top of the "automatic" default it keeps (11/08/2026) [BC-Break]
- A step raises the banner's floor and caps nothing: a title needing more room gets it (11/08/2026)
- The field is `height` where it was `maxHeight`, so a stored pixel value renders as "automatic" (11/08/2026)
- Added `BannerTitleType::HEIGHT_CHOICES` (11/08/2026)
- Added `--banner-title-height-small`, `--banner-title-height-medium` and `--banner-title-height-large` (11/08/2026)
- No block template writes a `<style>` element any more, invalid anywhere but the `<head>` (11/08/2026)
- `banner_title` takes its step from `--section-space-tight`, on its top edge only (11/08/2026)
- `BlockEditUrlRegistry` skips a provider that throws, where it used to 500 the page holding the blocks (11/08/2026)
- The `banner_title` fixture seeds a step, where it seeded the pixel value the field no longer takes (11/08/2026)
- `BannerTitleLayoutTest` locks each height step to a rule and the picture laid over the banner (11/08/2026)
- `BlockFixtureProviderTest` locks the banner fixture to a key and a value the FormType still has (11/08/2026)
- `BannerTitleStyleTest` locks the markup carrying neither a `<style>` element nor a `style` attribute (11/08/2026)
- UPGRADE.md documents the step, the picture and the pixel values left unmigrated (11/08/2026)
- README says the flip card's fold is undone by `@media (scripting: none)`, not a `<noscript>` (11/08/2026)
- README lists `banner_title` among the kinds reading the tight step, and carrying it as a margin (11/08/2026)
- A flip card's face, title and illustration are spaced tighter, the values coming back from a site (11/08/2026)
- A Trix editor keeps its `trix-change` to itself, EasyAdmin rebuilding a form-wide tracker on each one (11/08/2026)
- It re-runs the validity of its own required field instead, the one thing those handlers did for it (11/08/2026)
- Added `TrixEditorChangeScopeTest`, locking the scope and the validity it takes over (11/08/2026)

## v1.8.0

The flip card divorces the card, and both pick their own surface

### ConfigBundle

- `c975l:config:load-all` seeds a row holding nothing with the value its declaration carries (11/08/2026)
- A stored value is still never rewritten, empty being the only state written to (11/08/2026)
- A value seeded into a `sensitive` entry is encrypted on the way in (11/08/2026)
- `createConfig()` and the seeding share one `storableValue()` for that encryption (11/08/2026)
- `seo-robots-ai-crawlers-source` reads `none` as "keep this list by hand" (11/08/2026)
- An emptied source row is reseeded with its declared url by the next load-all (11/08/2026)
- Added `AiCrawlerListUpdater::NO_SOURCE` (11/08/2026)
- Added `RedirectRepository::findByFromPathPrefix()`, which sweeps the rows an url tree left behind (11/08/2026)
- Its LIKE wildcards are escaped rather than trusted, these rows being removed and not read (11/08/2026)
- README documents the seeded defaults and what an entry whose emptiness means something must do (11/08/2026)
- Added the `ConfigServiceTest` seeding cases and the `AiCrawlerListUpdaterTest` provider (11/08/2026)

### UiBundle

- `flip_card` is an object of its own: it reuses none of `.card`'s markup or rules (11/08/2026) [BC-Break]
- Its faces are outlined and rounded, and its title sits inside the face instead of a header band (11/08/2026)
- The card *is* the control: clicking it anywhere turns it, the toggle filling the whole face (11/08/2026)
- The content is lifted over the toggle and made pointer-transparent, its own links excepted (11/08/2026)
- The sway moved to `.flip-card-front`: on `.flip-card-inner` it swallowed every turn (11/08/2026)
- The accent moved to `flip-card--accent-*`, painting the outline and the two titles (11/08/2026) [BC-Break]
- Added `--flip-card-radius`, `-border-width`, `-front-background`, `-back-background` (11/08/2026)
- Added `--flip-card-title-size`, `--flip-card-text-size` and `--flip-card-media-max-width` (11/08/2026)
- A list on a face clears `list-style` whole, SiteBundle pointing every `ul` at an image (11/08/2026)
- `FlipCardAccessibilityTest` locks the title-as-control, its focus ring and the `.card` divorce (11/08/2026)
- The fold is the stylesheet's own: the card is painted in shape instead of flashing both faces (11/08/2026)
- `@media (scripting: none)` undoes it for a browser running no JS, where the toggles never appear (11/08/2026)
- Said from the stylesheet rather than from a `<style>` in a `<noscript>`, invalid outside the `<head>` (11/08/2026)
- The illustration takes the card's own ratio instead of a fixed 3/2 (11/08/2026)
- `flip_card`'s edit form groups its fields into a "Recto" and a "Verso" fieldset (11/08/2026)
- A FormType groups its fields through `row_attr`'s `data-block-fieldset`, any kind may (11/08/2026)
- A FormType hoists the block's media collection through `media_after`, any kind may (11/08/2026)
- Both are themed on the shared `ui_block_data` block prefix (11/08/2026)
- So the EasyAdmin screen and the fragment the kind picker loads lay a kind's fields out identically (11/08/2026)
- Added `BlockDataThemePrefixTest`, which locks that prefix on both entry points (11/08/2026)
- `flip_card` drops its bulk file input: it takes two images, each belonging to a named face (11/08/2026)
- Added `.block-fieldset` / `.block-fieldset-legend`, and `label.face_front` / `label.face_back` (11/08/2026)
- `card` and `flip_card` offer a "Coins arrondis" and an "Ombre" field, on one shared scale (11/08/2026)
- Added `BlockRadiusChoiceType` and `BlockShadowChoiceType`: theme / none / small / medium / large (11/08/2026)
- "Thème" is the placeholder, not a choice, so a block stored before the fields renders unchanged (11/08/2026)
- Added `sass/_block-surface.scss`, whose `block-radius-*` / `block-shadow-*` classes only set a token (11/08/2026)
- Added `--block-radius-small`, `-medium`, `-large` and `--block-shadow-small`, `-medium`, `-large` (11/08/2026)
- Each kind reads those through a default of its own, so "Thème" keeps the look it always had (11/08/2026)
- A card carries no shadow until a step is picked, where a flip card's step retunes the one it had (11/08/2026)
- The card's shadow is stated on two classes, so it never loses to `.box-shadow` on sheet order (11/08/2026)
- Added `BlockSurfaceTest` and `BlockSurfaceChoiceTypeTest` (11/08/2026)
- A `.cards` row resets a card's own margin as a descendant, not through the child combinator (11/08/2026)
- The `display: contents` wrappers keep the card the flex item but stay nodes a `>` is read against (11/08/2026)
- So an animated card kept its `1em` and sat a step below the row it belongs to (11/08/2026)
- `.flip-card` is a grid, so its inner fills the height the row gave the card (11/08/2026)
- Without it a row painted as many box heights as it held contents, one title wrapping being enough (11/08/2026)
- Added `CardRowAlignmentTest`, which also locks the two wrappers' `display: contents` (11/08/2026)
- Added `ChoiceAutocompleteExtension`, which decides on list length alone whether a choice field searches (11/08/2026)
- Below ten options a native `<select>` is rendered, at or above it EasyAdmin's TomSelect widget (11/08/2026)
- A `multiple` field always gets the widget, however short its list (11/08/2026)
- It overrides EasyAdmin, which handed the widget to every non-expanded choice field there is (11/08/2026)
- An expanded field and a list fed by an endpoint stay exempt, neither being countable (11/08/2026)
- The four block FormTypes writing `data-ea-widget` by hand drop it, the rule being one place now (11/08/2026)
- The widget's clear cross shows as soon as the field holds a value, not on hover alone (11/08/2026)
- Added `ChoiceAutocompleteExtensionTest` (11/08/2026)
- `CspNonceProvider` is wired on `nelmio_security.csp_listener`, the only id that listener is registered under (11/08/2026)
- Left to autowiring, it brought a consuming app's whole container down on its first `cache:clear` (11/08/2026)
- It takes that listener optionally, so a site that configured no `csp:` section still compiles (11/08/2026)
- A block's nonce marker is dropped altogether when there is no nonce, rather than left as `nonce=""` (11/08/2026)
- That marker is only substituted on a `<style>` tag, the string in a rich text field no longer nonced (11/08/2026)
- Added `CspNonceProviderTest`, and `c975LUiBundleTest` guards the wiring (11/08/2026)
- `createNoncedStyleElement()` copies the nonce off a stylesheet `<link>`, not off the first `[nonce]` (11/08/2026)
- That first one is importmap's own `<script>`, whose nonce style-src does not carry (11/08/2026)
- So every `<style>` the sliders, the image comparison, the video sizing and the overlay build was rejected (11/08/2026)
- `block-edit-overlay.js` gives up on a rejected element instead of throwing on its null sheet (11/08/2026)
- Its button measures past every transparent wrapper stacked around a block, not one fixed child (11/08/2026)
- The honeypot hides itself with `.ui-field-aside`, not a `style` attribute a nonce cannot cover (11/08/2026) [BC-Break]
- That class name states nothing: it ships in the stylesheet, where a telling one is a grep away (11/08/2026)
- It was being shown to every visitor as a "Department" field on any page with a nonced style-src (11/08/2026)
- Added `NoncedStyleSrcTest` coverage for the carrier, the overlay guard and the honeypot (11/08/2026)
- A slot's entrance animation plays: the wrapper moved from `Blocks:Block` to `renderBlock()` (11/08/2026)
- A slot of any container kind is rendered by `render_block()` straight, never through that component (11/08/2026)
- So its animation was stored, offered on the edit screen and read by nothing (11/08/2026)
- `recaptcha3-score-threshold` falls back on the 0.05 it is seeded with, instead of Google's 0.5 (11/08/2026)
- `site-form-delay` is seeded with the 7 seconds `FormBotProtection` falls back on, instead of 3 (11/08/2026)
- The four base `theme-color-*` ship with the color their fallback paints (11/08/2026)
- The three `theme-font-family-*` ship with the generic family their fallback names (11/08/2026)
- The two `-dark-mode` keys stay empty, their fallback being the light key rather than a color (11/08/2026)
- README documents the seeded palette and type, and why the dark-mode keys are not (11/08/2026)
- README documents the `.cards` row, the surface scale and the choice widget rule (11/08/2026)
- Prose comments spread over several lines are collapsed to one, across both bundles (11/08/2026)
- UPGRADE.md documents the `.card` divorce and the honeypot class a site theme has to pass on (11/08/2026)

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
