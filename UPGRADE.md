# UPGRADE

## > v1.14.0

**The bundle no longer ships the block showcase's placeholder media.** The five `public/images/gallery-photo-*.webp`, `public/videos/gallery-video.mp4`, `public/videos/gallery-video-embed.html`, `public/audio/gallery-audio.mp3` and `public/documents/gallery-document.pdf` are gone, along with the `BlockFixtureMediaAttacher::PLACEHOLDER_IMAGES`/`PLACEHOLDER_VIDEO`/`PLACEHOLDER_VIDEO_EMBED`/`PLACEHOLDER_AUDIO`/`PLACEHOLDER_DOCUMENT` constants naming them - 13 MB every application downloaded for a page only a showcase site ever renders. Only the mechanism stays: `attach()` and `nextPlaceholderImage()` now read `Registry\PlaceholderMediaRegistry`, fed by whichever service implements the new `Contract\PlaceholderMediaProviderInterface` (auto-discovered, no tag needed - see the Readme's "Block gallery" section for the shape). **If your application renders a block showcase**, copy those files into your own `public/` and declare them in such a provider. **If it doesn't** - the overwhelming case - there is nothing to do: with no provider registered, `attach()` simply adds no media and the blocks render without one, instead of pointing at files that are no longer there. Note that `nextPlaceholderImage()` now returns `?Media` rather than `Media`, so a caller of your own chaining on it (e.g. `->setAlt(...)`) has to handle null.

**Favicon and apple-touch-icon now accept an SVG upload**, which the previous release turned down outright. `Service\SvgRasterizer` renders it to PNG before the icon pipeline (GD, which that pipeline runs on, has no SVG decoder), through `ext-imagick` - a new **suggest**, not a requirement: without the extension those two roles keep accepting raster images only, exactly as before. Three things move with it, none of which an ordinary application has to act on:

- `Entity\Media::validateFixedIconMimeType()` is gone, replaced by the `Validator\FixedIconFormat` constraint the entity now carries. Only a caller invoking that method directly, or a test asserting on it, has to follow.
- `Listener\VichImageResizeListener` takes a third constructor argument, `Service\SvgRasterizer`. Autowired, so nothing to do unless you instantiate or decorate that listener yourself.
- The `label.fixed_icon_invalid_format` translation now carries a `%formats%` placeholder, the message listing SVG only where it can actually be rasterized. If you override that translation in your own application, add the placeholder or your message loses the format list.

**A raster favicon is now actually converted.** `Listener\VichImageResizeListener` used to decide what it could process on the stored file's extension, which for the favicon role is always `.ico` whatever was uploaded (see `Namer\UiMediaNamer`) - and `.ico` is precisely what it excludes, GD having no decoder for it. So a PNG/JPG/GIF/WEBP favicon was stored untouched under `public/favicon.ico`: neither cropped to 48x48 nor wrapped as a real icon, only its name saying otherwise. It now decides on the file's own content, so any favicon uploaded from here on is converted as documented. Existing ones are not rewritten - browsers sniff the content and read them anyway - re-upload yours to get a real 48x48 icon.

## > v1.13.0

**The bundle now requires PHP 8.4 and Symfony 8.** It used to declare `"php": ">=8.0"` and `"symfony/*": "*"`, an unbound constraint that let Composer resolve Symfony against whatever PHP the application ran on - so an application on PHP 8.2 silently got Symfony 7 with a bundle only ever tested against Symfony 8. The requirements now say what is actually built and tested: `"php": ">=8.4"` and `"symfony/*": "^8.0"`. If your application is still on Symfony 7, stay on the previous release until you migrate - `composer update` will simply refuse to move rather than break anything.

**Your `App\Entity\User` must now implement `c975L\ConfigBundle\Contract\UserInterface`**, `Block::$user`, `Media::$user` and `VichMediaTrait::$user` being typed against it instead of `App\Entity\User`. See `c975l/config-bundle`'s own UPGRADE for the one-line change and why nothing else moves - no migration, no configuration, the column and the join stay identical.

## > v1.11.0

**The colored flats' full-bleed breakout is now three custom properties**: `--section-flat-offset` (`50%`), `--section-flat-width` (`100vw`) and `--section-flat-margin-x` (`-50vw`), read by `.section--bg-muted`/`-primary`/`-dark` and by `.hero--has-bg`, which share that breakout. Defaults are the previous hardcoded values, so nothing moves. They exist for a design that frames its page inside `--body-max-width` (navbar and footer included, see SiteBundle's `--navbar-width`/`--footer-width`): there the flat must paint its own box, not the viewport, and `auto`/`auto`/`0` does it. Overriding this from the app's stylesheet was not enough - a rule zeroing the margins left `left: 50%` in place and shifted the section half a page to the right.

**The hero's typographic scale is now five custom properties**: `--hero-title-size`, `--hero-title-letter-spacing`, `--hero-title-line-height`, `--hero-sub-size` and `--hero-sub-max-width`. They are declared nowhere - each rule carries its previous value as its own fallback (`clamp(40px, 6vw, 66px)`, `-0.01em`, `1.03`, `19px`, `480px`), so a site setting none of them renders identically. A design going bigger and tighter than the bundle's scale sets them in its `theme.css` and can drop its `.hero__title`/`.hero__sub` overrides.

**The `text_section` block gained an optional `eyebrow` field**, the small uppercase line above the title the other section kinds already offered, rendered as UiBundle's own `.section-eyebrow`. Existing blocks store no such value and render exactly as before. Two things follow the field: with an eyebrow but no title the eyebrow becomes the section's `<h2 class="section-eyebrow">` (same rule as the `Section:*` components), and `TextSectionType` then derives the anchor slug from the eyebrow instead of leaving the block anchorless. A site whose stylesheet reshapes a `text_section`'s `<h2>` into an eyebrow by hand can move that text into the new field and drop most of the override.

**The `hero`, `feature_bar` and `text_section` blocks gained an optional `background` field** (light grey / primary color / dark), painting the section as a full-width flat. It is additive in every respect: an existing block has no such value stored, renders with no variant class, and every rule reading one of the new `--section-*` custom properties states its previous value as the fallback - a section with no flat is byte-for-byte the section you had. `.hero--has-bg` was rewritten on top of the same properties and renders identically.

**Inline formatting now inherits its color**: `sass/_rich-text.scss` declares `color: inherit` on the bare `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<s>`, `<del>`, `<ins>`, `<sub>`, `<sup>`, `<small>` and `<span>` elements, putting the browser default back where a theme coloring every element directly (`* { color: var(--text) }`) had taken it away - bolding a word in a white hero title turned it black. `<a>` is left out, keeping its own link color. It is a base-layer rule, so any class of yours setting a color on one of these still wins; but a site *relying* on the theme repainting a `<strong>` inside a colored section has to state that color itself now. The `.slider-title a strong`/`.slider-text a strong` rules were dropped, the base layer covering them.

Two `feature_bar` fixes do change what a bar of fewer than five entries looks like, both in the direction the design always intended: the row now takes exactly as many columns as it has items instead of trailing empty ones, and a three-entry bar no longer draws a divider hanging off its right edge from 1025px up. A site that had worked around either in its own CSS can drop that override.

## > v1.10

`Controller\Management\MediaCrudController` and `Form\MediaUploadType` both gained a `Service\MediaDimensionsFiller` constructor argument - first argument for `MediaUploadType` (until now constructor-less), inserted before `$projectDir` for the controller. Both services are autowired, so an app using them as-is needs no change; a subclass declaring its own `__construct()` (or a manual `new MediaUploadType()`) has to pass it along.

The `video` block's form (`Form\Block\VideoType`) gained `title`/`description`/`class`, the same fields the `video_iframe` block already had, and `templates/components/Video/Video.html.twig` now wraps its `<video>` in a `<figure class="video-figure">` (with an optional `<h3 class="video-title">` and `<figcaption class="video-description">`), mirroring `Video:Iframe`. Existing `video` blocks are unaffected - the three new keys are optional and simply absent from their data. If your CSS/JS targets that component's `<video>` as a direct child of its container, update the selector to go through the new `<figure>`.

Same change for the `audio` block: `Form\Block\AudioType`, until now deliberately field-less, gained `title`/`description`/`class` (no `width`/`height`, an `<audio>` element has no such attributes), and `templates/components/Audio/Audio.html.twig` now wraps its `<audio>` in a `<figure class="audio-figure">` and writes a `class` attribute on the `<audio>` itself. Existing `audio` blocks are unaffected.

`karser/karser-recaptcha3-bundle` is gone, replaced by UiBundle's own reCAPTCHA v3: `Service\CaptchaVerifier` (the one call that package was ever used for - a POST to Google's `siteverify`), `Form\CaptchaType`, `Validator\Constraints\Captcha`/`CaptchaValidator` and the `captcha` Stimulus controller. `Service\ReCaptchaFactory`, `Form\Extension\Recaptcha3TypeExtension`, `DependencyInjection\Compiler\RecaptchaPass` and `DependencyInjection\Compiler\CspListenerPass` (which only existed to nonce the inline script the old widget emitted) are removed with it.

In a consuming app:

- `composer remove karser/karser-recaptcha3-bundle`
- delete `config/packages/karser_recaptcha3.yaml` and the `KarserRecaptcha3Bundle` line in `config/bundles.php`
- delete the `RECAPTCHA3_KEY`/`RECAPTCHA3_SECRET` entries from `.env`/`.env.local`
- replace any `Karser\Recaptcha3Bundle\Form\Recaptcha3Type` in your own form types with `c975L\UiBundle\Form\CaptchaType` (which takes a single `action_name` option - no more `script_nonce_csp`)
- if you injected `Nelmio\SecurityBundle\EventListener\ContentSecurityPolicyListener` by FQCN, alias it yourself: UiBundle no longer registers that alias

The three ConfigBundle keys are unchanged (`recaptcha3-site-key`, `recaptcha3-secret-key`, `recaptcha3-score-threshold`), so nothing has to be re-entered in the backoffice, and the CSP directives stay the same (`www.google.com`/`www.gstatic.com` in `script-src`, `www.google.com` in `frame-src`). The visible difference is that Google's `api.js` is now only fetched once the visitor interacts with the form, instead of on every page load carrying one - worth ~765 KB and ~1.5 s of main thread, and it no longer drops a `_GRECAPTCHA` third-party cookie on visitors who never submit anything.

The `form` Block/`FormController`/`FormSubmissionType` now add the same honeypot/timing/GDPR/captcha protection contact/register/reset already had, plus an optional shared rate limiter (`limiter.ui_form`, configure it in `config/packages/rate_limiter.yaml` like `limiter.registration`/`limiter.reset_password` already are - a single shared one, since a Form built through the admin can't be bound to its own dedicated named DI service). `FormSubmissionType`'s constructor gained `FormBotProtection`/`ConfigServiceInterface`/`RequestStack`/`TranslatorInterface`/`CaptchaVerifier` - if you instantiate it directly (rather than via the form factory), update the call. `Form::$actionConfig`'s `receiveCopy` key (fixed admin choice) is renamed `offerReceiveCopy` (shows a checkbox, the visitor's own answer decides) - `SendEmailFormAction` now reads the submitted `receiveCopy` value, not the config flag.

## > v1.9

Added `FormField::$url` (nullable string) - run `doctrine:migrations:diff`/`doctrine:migrations:migrate` for the new `site_form_field.url` column. Optional, admin-editable from `FormFieldType` next to `placeholder`; when set, `FormSubmissionType` appends a translated, escaped link to the field's label instead of leaving it as plain text (e.g. a CGU checkbox's "J'accepte les conditions générales d'utilisation (lire)") - the label itself never becomes a link, so clicking the rest of it still toggles a checkbox as expected. Existing fields default to `url = null`, unaffected.

Added `Form::$enabled` (bool, default `true`) - run `doctrine:migrations:diff`/`doctrine:migrations:migrate` for the new `site_form.enabled` column. Lets an admin pause a Form (checkbox next to `action` on `FormCrudController`, or your own `Form::setEnabled(false)`) without unpublishing its Page or clearing `action`. `FormController::fragment()`/`submit()` now check it (after `loadForm()`, which keeps its own `null === $form->getAction()` 404) and render a new `@c975LUi/components/Form/FormDisabled.html.twig` notice instead of building the form when disabled - existing Forms default to `enabled = true`, nothing changes for them.

Added `Contract/BlockEditUrlProviderInterface`/`Registry/BlockEditUrlRegistry`, resolving a rendered Block's owning-entity edit URL across bundles (used by the new "Edit" hover button on `ROLE_EDITOR`+ - implement the interface on a tagged provider in whichever bundle owns your blocks, e.g. a Page). `Twig\BlockExtension`'s constructor gained a required `Registry\BlockEditUrlRegistry` argument (autowired, nothing to configure if you use the service container) - if you instantiate it directly, update the call.

Added `Entity/EmailTemplate`/`EmailBlock` (`site_email_template`/`site_email_block` tables - run `doctrine:migrations:diff`/`doctrine:migrations:migrate`) and `Service/EmailTemplateRenderer`, a separate, email-safe (table layout, inline CSS, no JS) block-based email builder - not a reuse of the page `Block` system, see the bundle Readme. `Controller/Management/EmailTemplateCrudController` manages it from the admin (link it yourself, or via `c975l/site-bundle`'s "Email templates" menu entry, which already does). `EmailTemplateRenderer`'s constructor takes a `ConfigServiceInterface` (autowired) - it resolves a `TYPE_IMAGE` block's url against the `site-url` config parameter when it's a relative path rather than a full `http(s)://` URL, so the domain lives in one place instead of being hand-typed into every image block.

`c975L\UiBundle\Service\SendEmailFormAction`'s constructor gained `Repository\EmailTemplateRepository`/`Service\EmailTemplateRenderer` (autowired, nothing to configure if you use the form factory) - if you instantiate it directly, update the call. It now reads an optional `emailTemplate` key from `Form::$actionConfig`: when set and found, the referenced `EmailTemplate` (with a `fields_table` block receiving the submission's label/value pairs) is sent instead of the legacy `template` Twig path - falls back silently to `template`/the default when not set or not found, so existing Forms are unaffected.

`c975L\UiBundle\Model\EmailSendRequest`'s `template` is now nullable (defaults to `null`) and a new `html` property was added - exactly one of the two must be set (`EmailService::send()` throws otherwise). Existing code passing `template:` is unaffected.

`FormField::TYPES` gained `password`/`password_repeated`/`url`/`tel`/`number`/`date`, alongside the existing `text`/`textarea`/`email`/`checkbox` - all pickable from any Form's admin screen, no migration needed (the `type` column was already a plain string).

`FormField` also gained `$url` (nullable string) - run `doctrine:migrations:diff`/`doctrine:migrations:migrate` for the new `site_form_field.url` column. When set, `FormSubmissionType` appends an escaped link to the field's label (e.g. a checkbox's "I accept the [Terms of use]") instead of rendering it as plain text - existing fields default to `null`, nothing changes for them.

`c975L\UiBundle\Validator\Constraints\DnsEmail`/`DnsEmailValidator` are new - a live MX/A DNS lookup on top of format checking, ported from c975l/site-bundle's app-copied scaffold (`App\Validator\Constraints\DnsEmail`) so every bundle building a generic Form benefits, not just the register/reset-password-request scaffold. Requires `egulias/email-validator`, now an explicit UiBundle dependency (was already a transitive one via `symfony/mailer`/`symfony/validator`, nothing to install in practice). `FormSubmissionType` now attaches both this and `Assert\Email` to every `email`-typed field automatically - if an existing Form has an email field pointed at a domain that can't realistically resolve (internal testing setups etc.), submissions against it will now be rejected server-side, where before only the browser's own `type="email"` HTML5 check applied.

A required `checkbox`-typed field on `FormSubmissionType` now gets `IsTrue` instead of `NotBlank` - `NotBlank` doesn't consider a boolean `false` blank, so an unchecked required checkbox was silently accepted before (the GDPR field already worked around this with its own hardcoded `IsTrue`, now every required checkbox does). If any integration relied on that gap, update accordingly.

`FormFieldNamer::nameFields()` no longer re-derives `name` from `label` for a field that is `restricted` and already has one - previously, relabelling a restricted field (allowed, only `type`/deletion are locked) silently changed its `name` too, which is a stable key other code looks it up by (`SendEmailFormAction`'s `senderEmailField` config, or a seeding bundle's own by-name field lookups). No action needed - this only affects a restricted field whose label gets edited after seeding.

## > v1.8.1

`c975L\SiteBundle\Service\FormBotProtection` moved here as `c975L\UiBundle\Service\FormBotProtection`, merged with ContactFormBundle's own rotating honeypot (field name/label now rotate per session instead of the fixed `website` field SiteBundle used) - update any `use` referencing the old class. `addHoneypotField()` now takes the current `Request` as a second argument (needed to read/generate the rotated field name/label) - a `FormType` calling it needs `RequestStack` injected to obtain it, see `RegistrationFormType`/`ResetPasswordRequestFormType` in the scaffold for the pattern.

`c975L\ContactFormBundle\Service\EmailService`/`EmailServiceInterface` moved here as a generic `c975L\UiBundle\Service\EmailService`, no longer tied to `ContactForm`/`ContactFormEvent` - it now takes a `c975L\UiBundle\Model\EmailSendRequest` and exposes errors via `getLastError()` instead of mutating an event. Requires `symfony/mailer` and `symfony/security-bundle` (both new UiBundle dependencies - `symfony/security-bundle` was already pulled transitively by most apps, `symfony/mailer` is new). New built-in `c975L\UiBundle\Service\SendEmailFormAction` (`FormActionInterface` key `send_email`), configured per-`Form` via the new `Form::$actionConfig` JSON column (`to`/`from`/`replyTo`/`subject`/`template`/`senderEmailField`/`offerReceiveCopy`) - default template `@c975LUi/emails/form_submission.html.twig` if none set.

`Form` also gained `$restricted` (bool, same principle as `FormField::$restricted`) - a seeded Form (e.g. ContactFormBundle's "contact") gets its `name` locked in the admin, and `$actionConfig` (JSON, nullable) - free-shape config read by whichever action is configured. Run `doctrine:migrations:diff`/`doctrine:migrations:migrate` for the new `site_form` columns.

Added `Controller/Management/FormCrudController` - a generic "manage any Form" admin screen. Link it yourself (or via `c975l/site-bundle`'s "Forms" menu entry, which already does) if you built your own dashboard menu.

`FormController`'s constructor gained a required `Service/FormPrefillHelper` argument (autowired, nothing to do if you use the service container) - update the call if you instantiate it directly. Call `FormPrefillHelper::prefill($request, $formName, ['fieldName' => $value])` right before redirecting a visitor to a Form's page (e.g. a listing's "Contact us about this" button redirecting to the "contact" Form's page) - the matching field(s) get pre-filled and turned readonly, cleared automatically once the submission succeeds. Replaces the need for ContactFormBundle's `?s=...` query string.

## > v1.6

The `video_iframe` block's markup changed: `templates/components/Video/Iframe.html.twig` used to render a bare `<iframe>` directly, it now renders a wrapping `<div>` and creates the `<iframe>` client-side (gated behind cookie consent if a `window.CookieConsent`-exposing banner is present on the page, see the README's "Video:Iframe" section - otherwise it renders immediately, same as before). If your CSS/JS specifically targets that block's `<iframe>` element, update the selector to target the new wrapper instead.

## > v1.5

`Media` gained two columns (`credits`, `rights_reserved`) used by the Slider block - run `bin/console doctrine:migrations:diff` then `doctrine:migrations:migrate` in the consuming app. Slider slides no longer expose the `label`/`width`/`height`/`above` fields (they were meant for the standalone Image block); existing data in these columns is untouched, just no longer editable/displayed for Slider media.

`Media` also gained a nullable, unique `role` column (`Media::ROLE_FAVICON`, `ROLE_APPLE_TOUCH_ICON`, `ROLE_OG_IMAGE`, `ROLE_LOGO`) for site-wide graphics not attached to any `Block`, and its `block` FK is now nullable to allow that - run the same `doctrine:migrations:diff` / `doctrine:migrations:migrate` to pick up both changes. Fetch a role's `Media` anywhere in Twig with `site_media('favicon')` (returns `null` if none was uploaded yet).

The `<twig:c975LUi:Menu:Menu>` and `<twig:c975LUi:Menu:MenuItem>` components, and their sass, moved to `c975L/SiteBundle` - update any template still referencing them from UiBundle.

Front and admin Stimulus controllers from c975L bundles are auto-discovered: a bundle just implements `BundleScriptProviderInterface` (front) and/or `BundleScriptAdminProviderInterface` (admin), tags itself, nothing else to wire in `c975L/SiteBundle`'s layout or `c975L/ConfigBundle`'s Dashboard for that part. But AssetMapper only rewrites a file's internal relative imports (e.g. `import Foo from './js/foo.js'`) to their digested public path if that file has an entry in `importmap.php` - so **every bundle providing controllers still needs its own `importmap.php` line** (a Symfony/AssetMapper constraint, not something that can be avoided).

**`importmap.php`** - add one entry per bundle providing controllers, always with `'entrypoint' => true`:

```php
'@c975l/ui-bundle/controllers.js' => [
    'path' => './vendor/c975l/ui-bundle/assets/controllers.js',
    'entrypoint' => true,
],
'@c975l/ui-bundle/controllers-admin.js' => [
    'path' => './vendor/c975l/ui-bundle/assets/controllers-admin.js',
    'entrypoint' => true,
],
'@c975l/site-bundle/controllers.js' => [
    'path' => './vendor/c975l/site-bundle/assets/controllers.js',
    'entrypoint' => true,
],
'@c975l/site-bundle/controllers-admin.js' => [
    'path' => './vendor/c975l/site-bundle/assets/controllers-admin.js',
    'entrypoint' => true,
],
```

This is the **only** thing to remember per bundle from now on - front layout and admin Dashboard pick it up automatically.

**Layout** (already done in `c975L/SiteBundle`'s own `layout.html.twig` if you extend it - nothing to do):

```twig
{{ importmap(['app']|merge(bundle_scripts()), {'nonce': csp_nonce('script')}) }}
```

## v4.x > v5.x

Made use of database to store config parameters. Needs a databse migration.
