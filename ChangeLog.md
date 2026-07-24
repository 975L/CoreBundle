# ChangeLog

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

## v1.8.

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

- Corrected link https://975l.com/pages/blocks (17/07/2026)

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

## v1.4.5

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

## v1.4.1

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

## v0.1

- Creation of bundle (24/06/2026)
