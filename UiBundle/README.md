# UiBundle

Symfony bundle providing the c975L ecosystem's shared front-end foundation — dynamic blocks, media library, and reusable CSS/JS/font/form registries used by every satellite bundle.

[![GitHub](https://img.shields.io/github/license/975L/CoreBundle)](https://github.com/975L/CoreBundle/blob/main/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/core-bundle)](https://packagist.org/packages/c975l/core-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/core-bundle)](https://packagist.org/packages/c975l/core-bundle)

> This bundle ships inside **[`c975l/core-bundle`](../README.md)**, alongside
> [ConfigBundle](../ConfigBundle/README.md) — one package, two bundles, one release. `composer
> require c975l/ui-bundle` is superseded; see the package [README](../README.md) and
> [UPGRADE.md](../UPGRADE.md). The namespace `c975L\UiBundle\` is unchanged.

## Why UiBundle

![UiBundle](../.github/images/UiBundle.svg)

The shared foundation every c975L satellite bundle builds on, alongside [ConfigBundle](../ConfigBundle/README.md): the Block system, media library, shared CSS/JS registries, Forms and Emails all live here so [SiteBundle](https://github.com/975L/SiteBundle), [ShopBundle](https://github.com/975L/ShopBundle), [BookBundle](https://github.com/975L/BookBundle), [GalleryBundle](https://github.com/975L/GalleryBundle) and [SocialBundle](https://github.com/975L/SocialBundle) don't each reinvent them. A satellite bundle adds its own block kind (`BlockRegistryPass`), stylesheet (`StylesheetRegistryPass`) or form action (`FormActionRegistry`) without ever touching UiBundle's code — just tag a service.

See it in action at [bundles.975l.com/pages/ui-bundle](https://bundles.975l.com/pages/ui-bundle), and browse every block kind live in the [block gallery](https://bundles.975l.com/pages/blocks).

---

> **TL;DR** — The Block system: any entity implementing `HasBlocksInterface` carries an ordered collection of typed blocks, each with its own form, template and render cache, edited by drag-and-drop in EasyAdmin. A satellite bundle registers its own block kinds, stylesheets and form actions by tagging a service, never by touching UiBundle. Also here: the media library, the font picker, forms with shared anti-spam, email templates, and the back-office AI assistant.

## Contents

- **Blocks** — [attaching to an entity](#attaching-blocks-to-an-entity) · [built-in kinds](#built-in-block-kinds) · [container kinds](#container-kinds-blocks-made-of-other-blocks) · [registering a custom kind](#registering-a-custom-block-kind) · [block gallery](#block-gallery) · [moving between collections](#moving-a-block-between-collections) · [anchors](#anchors-in-page-navigation) · [colored backgrounds](#colored-backgrounds) · [render cache](#block-render-cache)
- **Media** — [Media Library](#media-library) · [satellite media entities](#satellite-media-entities) · [site-wide media](#site-wide-media-favicon-logo-og-image) · [PDF thumbnails](#pdf-thumbnails)
- **Styling** — [automatic CSS injection](#automatic-css-injection) · [same, for EasyAdmin pages](#automatic-css-injection-for-easyadmin-management-pages) · [fonts](#fonts) · [font picker](#font-picker) · [reusable Twig components](#reusable-twig-components) · [generic Twig filters and functions](#generic-twig-filters-and-functions)
- **Forms, emails, AI** — [Forms](#forms) · [reCAPTCHA](#recaptcha) · [email builder](#email-builder) · [AI Assistant](#ai-assistant)
- **Admin** — [EasyAdmin integration](#easyadmin-integration) · [drag-and-drop sortable for other collections](#drag-and-drop-sortable-for-other-collections)
- **For satellite bundles** — [shared building blocks](#shared-building-blocks-for-satellite-bundles) · [exporting and importing blocks](#exporting-and-importing-blocks) · [forcing a download](#forcing-a-download)
- **Quality** — [checking a page's layout](#checking-a-pages-layout)

## Features

- Dynamic block system with per-kind forms and templates
- Media uploads per block via VichUploader (auto-configured)
- Multi-file upload for kinds that opt in (`slider`, `article` out of the box) - select several files at once instead of adding them one by one
- Drag-and-drop position ordering for blocks and media
- One-click duplication of a block or a media row in EasyAdmin, including its files
- Live preview of a newly picked image in EasyAdmin, before saving
- Site-wide media roles (favicon, apple-touch-icon, og-image, logo, error-image pool) with their own admin screen, retrievable anywhere via `site_media()`
- Admin-editable theme (colors, fonts, light/dark mode) compiled to CSS custom properties, and inlinable into emails
- GDPR cookie banner (`vanilla-cookieconsent` v3, self-hosted), carrying its own enabled/disabled guard
- A minimal page layout with the theme, the site graphics, the share tags and the banner, for an app running without SiteBundle
- Media Library in EasyAdmin: browse every `Media` regardless of how it's attached, and see where it's used
- AJAX kind-switcher in EasyAdmin
- Extensible: register your own block kinds via a service tag
- Automatic CSS injection: bundles declare their stylesheets via a service tag, rendered by `bundle_stylesheets()` in Twig
- Reusable drag-and-drop sortable script for any EasyAdmin `CollectionField`
- Font-family provider contract (`FontProviderInterface`/`FontRegistry`) plus a generic `FontChoiceType` select, reused by ConfigBundle's font-kind config fields
- Reusable building blocks for a satellite bundle's own Vich-uploaded media entity (`VichMediaTrait`, `MediaFileRemoveListener`) and for serving private downloads (`PrivateFileResponseFactory`)
- Shared plumbing every satellite bundle needs without needing SiteBundle: unique slugs, block edit URLs, sortable row attributes, generated-stylesheet writing, Vich upload options, block cache invalidation, block export/import
- Generic Twig helpers (`nl2br`, `linkify`, `route_exists`, `template_exists`, `asset_exists`)
- Layout invariants checked without a browser (`Testing\StylesheetCascade`, shipped for the bundles depending on this one), plus `c975l:ui:layout-audit` for what only a rendered page shows

---

## Requirements

- PHP >= 8.0
- Doctrine ORM
- EasyAdmin
- VichUploader Bundle
- The application's `App\Entity\User` must implement `c975L\ConfigBundle\Contract\UserInterface` — `Block::$user` and `Media::$user` are typed on that contract rather than on the app's own class, which lives in app-space and a bundle cannot reference. ConfigBundle's `prependExtension()` maps the two together through Doctrine's `resolve_target_entities`, so there is nothing else to declare; but a `User` not implementing it makes both stop recording who last edited a row, silently.
- [Ghostscript](https://www.ghostscript.com/) (`gs` binary) installed on the server — required for automatic PDF thumbnail generation (see [PDF thumbnails](#pdf-thumbnails)). Optional otherwise: without it, PDF uploads still work, but no `.webp` thumbnail is generated.
- `chrome-php/chrome` and a local Chrome — only for `c975l:ui:layout-audit` (see [Checking a page's layout](#checking-a-pages-layout)), a development tool. Nothing else needs them, and the command exits cleanly saying so when they're missing.

---

## Installation

### Download

```bash
composer require c975l/core-bundle
```

### Run migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Register Stimulus controllers

`controllers.js` (front-end) and `controllers-admin.js` (back-office: `block`, `eaSortable`, Trix editor integration) each start their own Stimulus app and are loaded as their own `<script type="module">` tag — auto-discovered and injected into the layout/dashboard, nothing to wire by hand there.

Only `animateScroll` and `menu` are imported eagerly. `blockEditOverlay`, `captcha`, `confetti`, `imageCompare`, `password`, `slider` and `videoIframe` are imported dynamically, and registered only when the current document actually contains a matching `data-controller` — AssetMapper treats a dynamic `import()` as lazy, so they get an importmap entry but no `<link rel="modulepreload">`, and a page carrying none of them downloads none of them. The check is re-run on `turbo:load`, so a page reached by Turbo navigation gets its own controllers too. Registering a new front-end controller means adding it to `LAZY_CONTROLLERS` rather than to the imports at the top of the file, unless it genuinely runs on every page.

The `confetti` controller loads its `canvas-confetti` library from the copy vendored in this bundle (`public/js/confetti.browser.min.js`) rather than from a CDN. Point it elsewhere with `data-confetti-script-value="/your/own/path.js"` on the same element.

The `videoIframe` controller never injects its iframe before consent has been given, and then only once the element nears the viewport. It looks for a consent banner registered as either `cookie-consent` (what this bundle's `<twig:c975LUi:Cookie:Consent />` writes) or `cookieConsent`; with no banner in the page at all it treats the content as unrestricted and loads it on approach.

#### The `password` controller

Put `password` in the `data-controller` of any element wrapping your forms — the `<body>` is the usual place — and every `<input type="password">` under it gets a show/hide eye (the `.has-toggle` wrapper and its icon are built client-side, styled by `sass/_forms.scss`, icons served from `public/icons/`). Revealing a password sets its `autocomplete` to `off`, so no password manager writes into a field shown in clear; hiding it back restores whatever the form declared — the `new-password` a sign-up form sets to keep managers from offering the existing credentials is not clobbered.

Two optional checks run on `blur`, driven by data attributes rather than by field names, so any form can opt in:

| Attribute | On | Effect |
| --- | --- | --- |
| `data-password-pattern` | the password field | Validates against that regex. Empty value falls back to the built-in "8 chars, one upper, one lower, one digit, one special" |
| `data-password-confirm` | the confirmation field | Value is the **id** of the field it must match |
| `data-password-message` | either | Overrides the message shown. Defaults come from `assets/js/translations.js` (en/fr/es), read through `assets/js/handlers.js`' `translate()`, which picks the language off the document's `lang` attribute |

An invalid field gets `.error` plus an `.error-message` paragraph after it; a valid one gets `.success`. The form's submit button is disabled as long as **any** field the controller watches is in error, not just the last one blurred — a confirmation matching a password that doesn't pass its own pattern leaves the button closed. Set the attributes through your form type:

```php
->add('plainPassword', PasswordType::class, [
    'attr' => ['data-password-pattern' => ''],
])
->add('confirmPassword', PasswordType::class, [
    'attr' => ['data-password-confirm' => 'registration_form_plainPassword'],
])
```

> Until UiBundle 2.0, a form whose fields are named `registration_form_plainPassword` / `registration_form_confirmPassword` still gets both checks with no attribute at all — that pair of ids was hardcoded in SiteBundle's `basic` controller before this moved here. Opting into the attributes above disables the fallback for that field.

Their `importmap.php` entries are added automatically the first time you `composer update` after installing UiBundle — see [Contributing importmap entries from other bundles](../ConfigBundle/README.md#contributing-importmap-entries-from-other-bundles) in ConfigBundle's README, nothing to add by hand.

### Making these controllers available in EasyAdmin (blocks editor, sortable, kind-switcher)

Blocks are managed through EasyAdmin at `/management`, provided by `c975l/config-bundle`. Its dashboard does **not** load your site's main `app` AssetMapper entry — that would drag your front-end stylesheet (and unused front-end controllers) into the back-office and break EasyAdmin's own Bootstrap/AdminLTE styling. Instead, it loads a dedicated entry, `@c975l/ui-bundle/controllers-admin.js` (same automatic `importmap.php` registration as above).

That's it — `eaSortable`, `block`, and Trix are then available on every `/management` page.

Any `/management` form can also be opened straight on one of its fields by adding a `focusField=<property>` query param to its URL — the `fieldFocus` controller opens that field's own tab, scrolls to it and focuses it, instead of dropping the user at the top of a form holding dozens of fields. SiteBundle's page health check advice links this way.

---

## Attaching blocks to an entity

### How block attachment works

Blocks are linked to their owner via a **ManyToMany join table**. The `Block` entity itself has no FK back to any specific owner — this keeps UiBundle fully decoupled from your domain entities. Each owner entity defines its own join table, and the `BlockOrphanListener` (auto-registered by the bundle) removes detached blocks on flush.

### 1. Implement the interface and trait

```php
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Page implements HasBlocksInterface
{
    use HasBlocksTrait;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'site_page_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }
}
```

Key points:

- Use `ManyToMany` (not `OneToMany`) — `Block` has no FK back to the owner.
- Name the join table explicitly (e.g. `site_page_block`) to avoid collisions.
- `cascade: ['persist', 'remove']` ensures blocks are saved and deleted with the owner.
- Do **not** add `orphanRemoval` — the `BlockOrphanListener` handles that automatically when you call `removeBlock()`.

### 2. Run migrations

After adding the mapping, generate and run the migration to create the join table:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### How block removal works

When you call `$page->removeBlock($block)`, the trait queues the block in a `pendingBlockRemovals` list instead of immediately removing it. The `BlockOrphanListener` (Doctrine `preFlush` listener) then calls `$em->remove($block)` for each queued block before the flush completes. This ensures blocks are properly deleted from the database even though the relationship is ManyToMany.

### Editable block overlay

`ROLE_EDITOR` users (and above) see a small "Edit" hover button on each rendered block, jumping straight to that block on its owning entity's EasyAdmin edit screen. Implement **`Contract\BlockEditUrlProviderInterface::getEditUrls(array $blocks): array`** (tagged automatically, picked up by `BlockEditUrlProviderPass`) and return an edit URL keyed by `Block::$id` for every block your bundle owns (e.g. SiteBundle's `Page`) - `Registry\BlockEditUrlRegistry` merges every provider's map, and edit URLs are resolved once per `Blocks.html.twig` collection (one query), not per block. Nothing to do if you don't own blocks or don't want them editable this way.

Call `Service\LegalModelEditUrl::build()` first in your own implementation, as SiteBundle does: a `legal_model` block is edited on its own customization screen (see [Legal models](#legal-models)), not on its row in your form, and that helper answers `null` for everything else.

### Where a block sits

The screens listing one kind across the whole site - the "Legal models" one - show where each block lives, which the bundle owning it is the only one to know. Implement **`Contract\BlockLocationProviderInterface::getLocations(array $blocks): array`** (auto-discovered by `BlockLocationProviderPass`) and return `['label' => …, 'url' => …, 'published' => …]` keyed by `Block::$id`, `url` being the public address the block is read at and `null` when it has none. Nothing implementing it simply means those screens list the blocks with no location - which is what an app running this bundle without any page management gets.

---

## EasyAdmin integration

Add a `CollectionField` using `BlockType` as entry type. The AJAX kind-switcher and drag-and-drop are handled automatically by the Stimulus controllers registered via `@c975l/ui-bundle/controllers-admin.js` — no manual `configureAssets` call is needed:

```php
use c975L\UiBundle\Form\BlockType;

class PageCrudController extends AbstractCrudController
{
    public function configureFields(string $pageName): iterable
    {
        return [
            // ...
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex(),
        ];
    }
}
```

---

## Drag-and-drop sortable for other collections

Drag-and-drop reordering is handled automatically by the `eaSortable` Stimulus controller registered via `@c975l/ui-bundle/controllers-admin.js`. No `configureAssets` call is needed.

**Requirement:** each collection item must contain a hidden `position` field whose `name` ends with `[position]`. The script detects it automatically.

Expose a hidden `position` field in your collection entry type and order the collection by position on the entity side — the grip handle and drag behaviour are added automatically.

---

## Built-in block kinds

The bundle ships the following kinds out of the box (see `config/services.yaml` for the exact service definitions):

| Kind | Category | Form type | Template |
| --- | --- | --- | --- |
| `alert` | Elements | `AlertType` | `blocks/Alert.html.twig` |
| `article` | Elements | `ArticleType` | `blocks/Article.html.twig` |
| `audio` | Media | `AudioType` | `blocks/Audio.html.twig` |
| `banner_title` | Media | `BannerTitleType` | `blocks/BannerTitle.html.twig` |
| `button` | Elements | `ButtonType` | `blocks/Button.html.twig` |
| `card` | Elements | `CardType` | `blocks/Card.html.twig` |
| `collection` | Page sections | `CollectionType` | `blocks/Collection.html.twig` |
| `contact_details` | Elements | `ContactDetailsType` | `blocks/ContactDetails.html.twig` |
| `cta_band` | Page sections | `CtaBandType` | `blocks/CtaBand.html.twig` |
| `document_download` | Elements | `DocumentDownloadType` | `blocks/DocumentDownload.html.twig` |
| `expertise_banner` | Page sections | `ExpertiseBannerType` | `blocks/ExpertiseBanner.html.twig` |
| `feature_bar` | Page sections | `FeatureBarType` | `blocks/FeatureBar.html.twig` |
| `form` | Forms | `FormPickerType` | `components/Form/FormBlock.html.twig` |
| `hero` | Page sections | `HeroType` | `blocks/Hero.html.twig` |
| `image` | Media | `ImageType` | `blocks/Image.html.twig` |
| `image_compare` | Media | `ImageCompareType` | `blocks/ImageCompare.html.twig` |
| `legal_model` | Legal | `LegalModelType` | `blocks/LegalModel.html.twig` |
| `portfolio_grid` | Page sections | `PortfolioGridType` | `blocks/PortfolioGrid.html.twig` |
| `process_steps` | Page sections | `ProcessStepsType` | `blocks/ProcessSteps.html.twig` |
| `progress_bar` | Elements | `ProgressBarType` | `blocks/ProgressBar.html.twig` |
| `flex_column` | Page sections | `FlexColumnType` | `blocks/FlexColumn.html.twig` |
| `flex_columns` | Page sections | `FlexColumnsType` | `blocks/FlexColumns.html.twig` |
| `section_cards` | Page sections | `SectionCardsType` | `blocks/SectionCards.html.twig` |
| `section_features` | Page sections | `SectionFeaturesType` | `blocks/SectionFeatures.html.twig` |
| `slider` | Media | `SliderType` | `blocks/Slider.html.twig` |
| `text_hook` | Text | `TextHookType` | `blocks/TextHook.html.twig` |
| `text_readmore` | Text | `ReadmoreType` | `blocks/TextReadmore.html.twig` |
| `text_section` | Text | `TextSectionType` | `blocks/TextSection.html.twig` |
| `video` | Media | `VideoType` | `blocks/Video.html.twig` |
| `video_iframe` | Media | `VideoIframeType` | `blocks/VideoIframe.html.twig` |

> **Maintenance note:** update this table whenever a kind is added, renamed, or removed in `config/services.yaml`.

### Contact details (`contact_details`)

One kind, two outputs: the panel a visitor reads, and the schema.org graph a search engine reads - both built from the same fields, so a phone number is typed once. **Every field is optional**, which is why the graph is published as a `<script type="application/ld+json">` rather than as microdata: a field left empty is simply dropped from it, where an `itemprop` pinned to a displayed element would have left an empty node behind, and the layout is free to change without touching the structured data. It also lets the block publish what it doesn't display - the absolute logo URL, and the GPS coordinates.

`ContactSnippetBuilder` assembles that graph (and `ContactSnippetBuilder::TYPES` holds the schema.org types the form offers, all `LocalBusiness`/`Organization` subtypes). The one field that isn't fully optional in effect is the **name**: without it no graph is published at all, a business node without a name indexing nothing, and the block renders as a plain display block.

**Opening hours** are a collection of ranges, not one line per day: a business closing for lunch fills in two rows over the same days (`Monday…Friday 9:00-12:00`, then `Monday…Friday 14:00-18:00`), each becoming its own `OpeningHoursSpecification`. A day no row names is closed. Both times are native time pickers (`TimeType`, `input_format: 'H:i'`), so the value stored in the block's data is already the `HH:MM` schema.org publishes - `ContactSnippetBuilder` drops anything else rather than guessing at it, a misread `6pm` being enough to publish a closing hour before the opening one. On the display side, consecutive days collapse into a range through the `contact_day_runs()` Twig function, so those two rows read as `Monday - Friday` twice rather than as ten lines.

The **website** and **map** fields are `UrlType`s with `default_protocol: 'https'`, and carry a `Url` constraint (the e-mail an `Email` one): a bare `example.com` would otherwise render as a relative `href` - resolved against SiteBundle's sitewide `<base href>`, so pointing back at the site - and reach the graph non-absolute, which schema.org takes no notice of.

An attached image is used as the logo, shown beside the name and published as the graph's `image` - the block declares no `media_required`, so it stays optional like everything else.

The **e-mail is displayed as plain text, never as a `mailto:` link** - a linked address is the first thing a harvester follows. It is still published in the graph, which is read by consumers rather than crawled for addresses. The phone numbers keep their `tel:` links.

The panel lays its fields out on an `auto-fit` grid rather than in one label/value column, so it fills whatever width it is given: a row of fields across a full-width slot, a single column inside a `flex_column`, with no breakpoint of its own (`--contact-details-col-min`, `210px`, being the narrowest a field column gets before the grid drops one). Its colors read the `--section-*` tokens first, each with the bundle's own neutral as the fallback, so dropping it inside a colored flat inverts it along with everything else sitting there - see "Colored backgrounds" above.

Dropped straight on a page, the block has no `.section-wrap` around it to hold it on the page measure, so `sass/_contact-details.scss` gives it one of its own: it takes the room it is given and stops at `--section-wrap-max-width` / `--body-max-width` (`1440px`), gutters included, which lines its fields up with any section above or below it. Those gutters are **margins**, not the padding `.section-wrap` uses - the panel paints a border and a background, which have to stay inside them - and the measure is read through a `calc()` taking them off it. Inside a `flex_column` or a `section_cards` slot none of it applies: the panel is not a child of `.blocks` there, and goes on filling its cell. `SectionWrapMeasureTest` locks the rule.

### The lead-in paragraph (`text_hook`)

`text_hook` holds one rich-text field and exists for a reason the editor itself creates: Trix writes no class, so a paragraph meant to read as an introduction - larger, looser, over a shorter measure - cannot be produced from inside a `text_section`'s content. The kind *is* that class. Being a block of its own, it drops anywhere a paragraph would go: under a `hero`, at the top of a `flex_column`, between two sections.

An `article`'s own **Hook phrase** field (`ArticleType::$hook`) shares that look, so it doesn't matter which of the two an editor used - but only the base of it. `sass/_text-hook.scss` holds three rules:

- `.text-hook`, worn by both: size, line height, measure and color (`--text-hook-size`, `--text-hook-line-height`, `--text-hook-max-width`, `--text-hook-color`, `--text-hook-margin-bottom`);
- `.text-hook--standalone`, worn by the block alone: the primary-colored bar down its left side (`--text-hook-bar-width`, `--text-hook-bar-gap`, `--text-hook-standalone-margin`). The block drops between two sections with nothing around it to be read against, where an article's hook already sits under a title and above a body of text - marking that one too would only over-decorate it;
- `.text-hook--article`, worn by the article's hook alone: the accent color in place of that bar (`--text-hook-article-color`, `--text-hook-article-margin`), already placed by the title above it. It also drops the base rule's own measure (`--text-hook-article-max-width`, `none` by default) so the hook is laid out on the article's, a 62ch box having otherwise left a centered hook reading off-center against the text it introduces. Set `--text-hook-article-color` on a theme whose `--primary` *is* its text color, where the accent would mark out nothing.

Every one of those tokens is read with the bundle's own value as its fallback and declared nowhere, so a site setting none of them renders exactly as before. The bar follows `--section-accent` before `--primary`, so it inverts along with a colored flat. `TextHookStyleTest` locks both rules in the compiled stylesheets, `TextHookMarkupTest` locks the modifier to the component.

Note that this only touches how an article renders. `c975L/SiteBundle`'s `articles_slider` reads that very same stored hook as a plain `striptags`'d excerpt, and keeps the slider's own text style.

---

## Container kinds (blocks made of other blocks)

`flex_columns` is a **container** kind: instead of holding plain data, its "slots" are real, independently-editable `Block` rows (`Block::$slots`, a self-referencing relation - `Block::$parentBlock` on the child side), each picked through the exact same kind-picker + form + media upload as any top-level block. Use it whenever a design lays several existing blocks (a paragraph, a `document_download` card, a `progress_bar`...) side by side, instead of inventing a one-off kind per layout.

- Each of `flex_columns`' own slots is a `flex_column`, and nothing else: that context is **exclusive** (`BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT`, see "Registering a block kind" below), the column being what carries the width option - a bare block used as a slot has nowhere to store it. A column's own slots (added the same way, via its own "+ Add a slot" button) are then any pickable kind, stacked vertically inside that one column - e.g. two `document_download` cards one above the other, next to a `text_section` paragraph in the other column.
- Each column picks how wide it sits in the row (`columnWidth`), in **twelfths** - the same scale Bootstrap's grid made everyone fluent in, 12 dividing by 2, 3, 4 and 6. Leaving it empty keeps the column sharing the row evenly, exactly as before the field existed. Each unit hands back the share of the row's gutter (`--flex-columns-gap`) it doesn't need, so any set of units adding up to 12 fills the row exactly. Widths only apply from 861px up - below that every column spans the full width whatever was picked. A row whose units don't total 12 still renders, it just doesn't close: under 12 leaves the remainder empty on the right, over 12 sends the column that no longer fits onto its own line (`flex-wrap`), never overflowing the page. Nothing forbids it - the editor sees it immediately, and a row deliberately left short (a single 8-unit column) is a legitimate layout.
- A slot saved before that restriction existed holds a kind the picker no longer lists. It is put back in that one slot's own choices, carrying a help text telling the editor to move it into a `flex_column` - without it `ChoiceType` would render the select unselected and reject the value on submit, locking them out of a page they can still see.
- Nesting is bounded to exactly this: a `flex_columns` slot can't be another `flex_columns`, and a `flex_column` slot can't be another `flex_column` (or a `flex_columns`) - see `BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT`/`SLOT_CONTEXT`/`NESTED_SLOT_CONTEXT` and `getSlotContext()` below.
- Not cacheable itself (`cacheable: false`, same for `flex_column`): each leaf slot still caches independently through its own `render_block()` call (see "Block render cache" below) - only the wrapper(s) are re-rendered every time, which is cheap.
- To make your own kind a container, tag it `container: true` in its `ui.block` service tag, and mirror `FlexColumnsType`/`FlexColumns.html.twig` (or `FlexColumnType`/`FlexColumn.html.twig` for a chrome-less nested one) - the "slots" field itself is added automatically by `BlockType`, not by your kind's own form. By default its slots are offered every OTHER container kind's own choices too, minus containers (`BlockRegistry::SLOT_CONTEXT`); to let your container nest one level inside another specific container instead (like `flex_column` does inside `flex_columns`), declare `contexts: 'that_containers_slot_context'` and give your own slots a distinct `slot_context: 'something_else'` so nothing can nest inside *it* in turn.
- A `contexts`-restricted kind is only hidden from pickers that actually pass a context, which every real one does (`PageCrudController` passes `'page'`, `MenuCrudController` `'menu'`, `BlockFormController` the container's own slot context) - so `flex_column` never leaks into a page's or a menu's own picker. A context-less caller would still list it: harmless, it just renders its slots with no wrapper when picked directly.

`section_cards` is a second, simpler container built the exact same way: eyebrow/title/anchor + slots, no `contexts`/`slot_context` override, so unlike a `flex_columns` row its slots stay open to every pickable kind (the shared `BlockRegistry::SLOT_CONTEXT`). Its slots are meant to be `card` blocks - each keeping its own full schema (image, link, button...) - but the difference from just using `flex_columns` for that isn't enforcement, it's rendering: `section_cards` wraps its slots in `.cards` (`sass/_cards.scss`), the same fixed-width flex row bare consecutive `card` blocks already get in the page flow (see `Blocks.html.twig`), instead of `flex_columns`' own generic flexible-width `.flex-columns__col` layout. Use it whenever a design calls for that exact "row of cards" look but with a section eyebrow/title/anchor around it, which bare consecutive `card` blocks can't have on their own.

Upgrading to a UiBundle version that introduces `flex_columns`/`flex_column`/`section_cards` (or your own container kind) adds a new `parent_block_id` column to `site_block` - re-run "Run migrations" above after `composer update`.

### Why a container's slots can't vanish any more

`Block::$slots` is `cascade: ['persist', 'remove'], orphanRemoval: true`, and the collection is `allow_delete` — so anything reading the slots as "gone" **deletes the rows**, in cascade, irreversibly. An HTML form can't represent an empty array, only an absent key, so `BlockType` used to read an absent `slots` key as "the editor removed the last slot" and prune accordingly. That's right for exactly one of the ways a key goes missing. The others — a kind just switched to a container client-side, a body PHP truncated at `max_input_vars`, a DOM something rewrote — arrive identically, and each one silently emptied the container. A live page lost the cards of a `section_cards` block that way, with nothing left in the database to say why.

Two guards close it, and `BlockSlotsGuardTest` reproduces the deletion each one prevents:

- **A marker**, `BlockType::SLOTS_RENDERED`, unmapped and rendered *beside* the collection rather than inside its rows, so deleting every row leaves it standing. Read back on submit, it is what separates "the collection was on screen and was emptied" from "this form never carried it". The submitted collection itself counts as its own proof, so an edit page opened before the marker existed goes on saving normally instead of failing as an extra field. Only **neither** is read as "never offered", and there nothing touches the collection at all.
- **A completeness check**, `Form\Util\SubmissionIntegrity::isTruncated()`. PHP stops parsing a request body at `max_input_vars` (1000 by default) and drops the rest with no warning, no error and no log entry — and it can cut between the marker and the very collection the marker vouches for, which is why the marker alone isn't enough. A whole page is one form (every block, every nested slot, every media in a single POST), so it is the form that reaches that limit first. On a short body nothing is pruned and the absent key is deliberately **left absent** — that is what makes Symfony skip add/remove handling, i.e. keep every slot, where normalizing it to `[]` would delete them all. SiteBundle's `PageCrudController` applies the same check one level up, for the page's own `blocks`, and refuses the submission outright with a message naming the limit rather than half-saving the page.

If an editor ever sees that message, the fix is on the server: raise `max_input_vars` in `php.ini`, or split the page. Nothing in PHP can recover fields it never parsed.

---

## Moving a Block between collections

Reordering (drag-and-drop, `assets/js/ea-sortable.js`) only ever rearranges rows *within one collection* (a page/menu's own top-level `blocks`, or one container's own `slots`) - a plain form resubmit has no way to reparent an existing row into a *different* collection: `CollectionType` recognizes an existing entity by its position in the form as originally built, not by an id, so a row moved to an index outside its original collection is treated as brand new (the real one gets deleted, a fresh one created with none of its media - see `BlockMoveController`'s own doc comment).

Dragging an already-saved Block onto a *different* Block-collection field (another container's slots, or back out to top-level) instead persists the move immediately, via `BlockMoveController`/`BlockRelocator`, bypassing the open edit form entirely:

- Both fields (the drag's origin and destination) must carry `row_attr`'s `data-block-collection: '1'` marker for the cross-field drop to be offered at all - every other sortable collection in this bundle (medias, form fields, email blocks...) is untouched. `BlockType::addSlotsSubForm()` already marks a container's own `slots` field this way (only once the container itself has an id - a not-yet-saved container can't be a move destination yet).
- Your own `HasBlocksInterface` owner's top-level `blocks` field needs the same marker plus `data-block-owner-type`/`data-block-owner-id` (a short type string of your choosing, e.g. `"page"`) and a `BlockOwnerResolverInterface` implementation resolving that type back to your entity - see `c975L\SiteBundle\Management\SiteBlockOwnerResolver`/`Controller\Management\PageCrudController`'s own "blocks" field for a full example, auto-discovered the same way as `MediaUsageProviderInterface` (no tag needed, see `BlockOwnerResolverPass`).
- The move itself never crosses two different owners (a Page's blocks can only move within that same Page) - `BlockMoveController` re-verifies ownership server-side regardless of what the client sends.

---

## Anchors (in-page navigation)

Every "Page sections" kind above (`hero`, `feature_bar`, `section_features`, `flex_columns`, `section_cards`, `expertise_banner`, `process_steps`, `portfolio_grid`, `cta_band`, `collection`) has an optional **Anchor** field, letting an editor build a one-page nav (a `menu_link` block - see `c975L/SiteBundle`'s README - pointing straight at a section of the same page).

- Typing an anchor (e.g. `Services`) slugifies it (`services`). Leaving it empty falls back to slugifying the block's own title.
- The final HTML `id` rendered on the section is always `{slug}-{block.id}` (e.g. `services-42`) - the trailing block id is added at render time, not stored, so two blocks of the same kind on the same page (or the same title reused elsewhere) never collide.
- In `SiteBundle`'s Menu admin, a `menu_link` block's target select lists every page's anchored sections alongside its pages/routes (`Home → Services`), decoded by `MenuExtension::getMenuLinkUrl()` into `/home#services-42`.
- That list is built by `c975L\UiBundle\Service\BlockAnchorCollector` (`fragment => label`), which walks a container's nested slots too (a `text_section` inside a `flex_columns` is listed just like a top-level one) and knows the two id conventions in use: an `anchor` renders as `{slug}-{block.id}`, an auto-derived `slug` (`text_section`, `article`) renders as the slug itself. `MenuExtension` labels a saved anchored target through the very same collector, so picker and menu never disagree.
- Every `url`-style field on `button`, `card`, `cta_band`, `hero` and `portfolio_grid` (e.g. `primaryUrl`, `ctaUrl`, `linkUrl`) is a plain `TextType`, not Symfony's `UrlType` — so an editor can point one straight at an in-page anchor (`#services-42`) or a relative path, not just an absolute URL.

Implemented by `c975L\UiBundle\Service\BlockAnchorSlugger` (the slug logic) and `c975L\UiBundle\Form\Block\HasAnchorFieldTrait` (the reusable field + `FormEvents::SUBMIT` listener). To add the same anchor field to a new "section" kind, in any bundle (own or third-party) that requires `c975l/ui-bundle`:

```php
use c975L\UiBundle\Form\Block\HasAnchorFieldTrait;
use c975L\UiBundle\Service\BlockAnchorSlugger;

class MySectionType extends AbstractType
{
    use HasAnchorFieldTrait;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger); // 2nd arg: title field name, defaults to "title"
        // ...your own fields...
    }
}
```

Then, in the kind's template (`{'block' => $block} + $block->getData()` is what `render_block()` passes it - see "Registering a custom block kind" below), compose the final `id` and pass it to your section's outer tag:

```twig
<section{% if anchor %} id="{{ anchor }}-{{ block.id }}"{% endif %}>...</section>
```

No `services.yaml` entry is needed for `BlockAnchorSlugger` itself: it's autowired like any other service, from any bundle whose `services.yaml` scans its own `src/` (the convention already used by every c975L bundle) - the same way `SocialBundle`'s `SocialLinkEntryType` already reuses `UiBundle\Form\IconPickerType` across bundles.

---

## Colored backgrounds

The `hero`, `feature_bar` and `text_section` kinds carry an optional **Background** field, painting the section as a full-width flat: **light grey**, the site's **primary color**, or **dark**. It exists because a colored band can't be expressed as a token: a section painted with one has to invert everything it holds - title, muted text, eyebrow, dividers, translucent chips, and the primary CTA, which is itself a `--primary` flat and turns white-on-color over one.

Each variant redefines a handful of custom properties, and every section rule reads them with its own neutral value as the fallback:

| Property | Read by | Neutral fallback |
|---|---|---|
| `--section-background` | the section's own background | its usual one (page background, `--surface-alt`…) |
| `--section-text` | titles, `<b>` figures, the blanket color of everything inside a flat | `--text` |
| `--section-text-soft` | subtitles, legends, muted copy | `--label-color` |
| `--section-accent` | eyebrow, emphasized word of a title, ghost button's rule | `--primary` |
| `--section-border` | dividers and hairlines | `--border-color` |
| `--section-overlay` | badges and translucent chips | `--surface-accent` |

A flat bleeds full-viewport-width past `--body-max-width`, else it paints a centered stripe between a full-width navbar and footer. That breakout is itself three tokens, each read with its own value as the fallback: `--section-flat-offset` (`50%`), `--section-flat-width` (`100vw`) and `--section-flat-margin-x` (`-50vw`) - `.hero--has-bg` reads the same three. A design framing its whole page inside `--body-max-width` (navbar and footer included, see SiteBundle's `--navbar-width`/`--footer-width`) sets them to `auto`/`auto`/`0` in its `theme.css`, and the flats paint their own box like any other section.

The **vertical rhythm** is one token, `--section-space` (`clamp(48px, 8vw, 84px)`), plus `--section-space-tight` (`clamp(24px, 4vw, 48px)`) for `text_section`, which commonly opens a page right under its own `<h1>`. The rule behind it is worth stating, because every defect it replaced came from breaking it: **a section-level block declares its step on its top edge and nothing on its bottom one**, so any two blocks are parted by exactly one step whichever pair they are. Two consequences follow. A block padding its bottom too — `hero` and `cta_band` did — parts its pair by *two*, and the page's spacing stops being uniform; only a **flat** may pad its bottom edge, because it paints down to that edge and its content would otherwise sit right on the color. And `feature_bar` carries its step as a `margin-block-start` rather than a padding: the band paints its own background between its own hairlines, so a padding would part *those* from its items instead of parting the band from the block above it — which is also why it is the one kind left out of the margin reset. `SectionRhythmTest` locks all of it: every kind the reset names owns a step, none but a flat pads its bottom, and the value is never written out beside the token.

One page-level block is named by no kind: the `.cards` row `Blocks.html.twig` synthesizes around consecutive `card` blocks (`.blocks > .cards`). Its cards drop their own `margin` inside the row, so without a step of its own it sat flush against the block above it — plainly so under a colored flat, whose background ran straight into the first card, and it carries the same `--section-space` on its top edge as every other page-level block. Inside a `section_cards` container the row is not a child of `.blocks`, and the section around it owns the step instead.

One leak is closed alongside, a block handing the page a margin the rhythm never declared: `.flex-columns__col > :last-child` drops the theme's own bottom margin off whatever element a column ends on — a slot rendering a bare `<p>`, as the `image` kind does, would otherwise push the section below it that much lower. In the same spirit `section_cards`, `section_features` and `flex_columns` render **no row at all** rather than an empty one when they have no slot, so the heading's own bottom margin doesn't hang in the void under them.

The measure every section is laid out on is two tokens read the same way: `--section-wrap-max-width` and `--section-wrap-gutter` (`clamp(20px, 5vw, 64px)`). The first falls back to the page's own frame — `--body-max-width`, declared at `1440px` by SiteBundle and restated as the same `1440px` here for UiBundle used on its own: one measure for the whole page, a section capped narrower than the body it sits in being inset inside its own page. The same chain is read by `.section-wrap`, by the bare `.blocks > .cards` row and by a flat `.feature-bar`'s grid, neither of the last two having a wrap of its own, so the three follow the same measure whatever it is set to. The gutter is read by the first two only: an uncolored `.feature-bar` has no wrap either and spans that measure edge to edge, the flat rule just putting a colored one back on the very same geometry after its full-bleed.

So a section with no background set renders exactly as it did before the option existed - that's what `SectionBackgroundTest` locks. Only the three backgrounds themselves are tokens (`--section-bg-muted`, `--section-bg-primary`, `--section-bg-dark`, declared in SiteBundle's `sass/_variables.scss` and restated in a site's own `theme.css`); every tone a variant derives is mixed back into whichever color is set there, so retuning one line retunes the whole variant.

Note the blanket `.section--bg-primary *` rule: SiteBundle's `sass/_typography.scss` writes `color` on **every** element rather than letting it inherit, so a flat has to repaint its descendants instead of just setting its own color. The per-kind rules come later in the file and refine it on equal specificity.

That same `*` rule is why `sass/_rich-text.scss` exists: it puts `color: inherit` back on the inline formatting tags a rich text editor produces (`<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<s>`, `<del>`, `<ins>`, `<sub>`, `<sup>`, `<small>`, `<span>`), so bolding a word inside a white hero title no longer turns it black. `<a>` is deliberately left out - a link keeps its own color. `RichTextInheritColorTest` locks those rules in the compiled stylesheets.

To offer the same field on another section kind, `use HasBackgroundFieldTrait` and call `addBackgroundField($builder)` from `buildForm()`:

```php
use c975L\UiBundle\Form\Block\HasBackgroundFieldTrait;

class MySectionType extends AbstractType
{
    use HasBackgroundFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addBackgroundField($builder);
        // ...your own fields...
    }
}
```

Then have the component match the stored value against the known variants before turning it into a class - never interpolate it as-is, it comes from a Block's `data` column:

```twig
{% set background = background|default('') in ['muted', 'primary', 'dark'] ? ' section--bg-' ~ background : '' %}
<section class="my-section{{ background }}">...</section>
```

---

## Card accents

A `card` can be marked with one of twelve fixed hues, which colors its header band and its own outline — red, orange, yellow, lime, green, teal, cyan, blue, indigo, violet, pink, grey (`BlockAccentChoiceType`). The field is optional and an unset value leaves the header on `--primary` and the border on `--input-border-color`, which is what every card stored before it existed shows. A card with no header still carries the accent on its outline.

They are named after colors on purpose. A free color input would put arbitrary CSS in the database, and a per-site vocabulary (`family-socle`, `promo`, `sale`…) would tie a look to a subject: the day those cards are about something else, the stored value lies. A hue names itself and stays true whatever the cards end up being about — what it *means* on a given page is that page's business, not the block's.

Each hue is a `--block-accent-*` token defaulted in `sass/_tokens.scss`, so a site retunes any of them from its own `theme.css` without a single stored value changing:

```css
:root {
    --block-accent-blue: var(--primary);
    --block-accent-indigo: color-mix(in srgb, var(--primary) 70%, var(--secondary));
}
```

The stored value is matched against those twelve in `blocks/Card.html.twig` rather than interpolated, the same rule the `background` field follows — a hand-edited database row can never write a class name of its own. Each `.card--accent-*` class does nothing but point `--card-accent` at its own token; `.card-header` reads it with `var(--card-accent, var(--primary))`, and `.card`'s `border` — plus the band's own `border-bottom`, which disappears into the band rather than cutting the outline in two — with `var(--card-accent, var(--input-border-color))`. That is what puts the accent on the band and around the card while leaving an unaccented one where it always was, the same fallback covering the `.card-header` the management accordion reuses outside any card.

Four of the twelve — orange, yellow, lime and teal — are light enough that white text falls under 4.5:1 on them, so they also set `--card-accent-color: #000` and `--card-accent-invert: 0`, which darkens the header's text and stops its icons being inverted to white. A site retuning one of the other eight towards a light hue has to carry those two along:

```css
:root {
    --block-accent-blue: #7dd3fc;
}

.card--accent-blue {
    --card-accent-color: #000;
    --card-accent-invert: 0;
}
```

Another kind offers the same picker with one line, `->add('accent', BlockAccentChoiceType::class)`, plus its own `--accent-<hue>` rules and the matching list in its template.

---

## Registering a custom block kind

Run `bin/console c975l:ui:block:create` (requires `symfony/maker-bundle` in `require-dev`) to scaffold the FormType, template and test below for a new kind - it prints the `services.yaml` snippet to add once it's done. The rest of this section describes what that snippet means and the manual steps if you'd rather write them yourself.

This command runs in a **consuming app** (it needs a booted kernel with `symfony/maker-bundle` installed) and generates into that app's own `App\` namespace - it's meant for a one-off kind specific to that app, not for adding to UiBundle's own built-in catalog above. A new built-in kind (living in `c975L\UiBundle\Form\Block\`, wired into UiBundle's own `config/services.yaml`, with translations in all 3 `translations/ui.*.xlf` and a fixture entry in `BlockFixtureProvider`) is still written by hand, matching the existing kinds' conventions.

Declare a service with the `ui.block` tag in your bundle's `services.yaml`:

```yaml
services:
    ui.block.booking:
        class: stdClass
        tags:
            - name: ui.block
              kind: booking
              label: Booking
              description: A short reservation form  # optional, shown under the label in the kind picker
              category: Reservations
              form: App\Form\Block\BookingType
              template: '@App/blocks/booking.html.twig'
              pickable: true  # required - see below
              priority: 80  # optional, defaults to 0 - see below
              cacheable: true  # required - see below
              contexts: booking  # optional, comma-separated, defaults to none - see below
```

Create the form type to define the `data` sub-fields, and the Twig template to render the block on the front end. The form data is stored as JSON in the `Block::$data` column.

`pickable` and `cacheable` don't have a functional default in practice - declare both explicitly on every kind, so the behavior is readable in `services.yaml` without having to check `BlockRegistryPass`'s fallback logic.

Set `pickable: false` for a **singleton** kind: one meant to be managed through its own dedicated EasyAdmin entry (see `c975L/SocialBundle`'s `SocialLinksCrudController` for an example) and rendered wherever needed via `BlockRepository::findOneByKind()`, rather than attached per-page. This hides it from the generic per-page block picker (`BlockRegistry::groupedByCategory()`), so editors can't accidentally create independent, separately-filled copies of it on individual pages. Regular, repeatable kinds (`card`, `text_section`, `contact_form`...) should use `true`.

`priority` controls the kind's position within its category in the picker (higher shows first, same convention as the `ui.stylesheet`/`ui.script` tags); kinds sharing the same priority (default `0`) fall back to alphabetical order.

The originating bundle of a kind is derived automatically from its `template`'s `@c975LXxx/...` Twig namespace (no tag attribute to fill in) and exposed via `BlockRegistry::getBundle()`/`groupedByBundle()` - the latter mirrors `groupedByCategory()` but groups by bundle instead, for building a showcase page per bundle rather than the kind-picker's functional grouping.

`contexts` restricts a kind to one or more named contexts (e.g. `menu`) instead of it being offered everywhere `BlockType` is used — useful for a kind that only makes sense on one entity, not on every `blocks` collection in the app (e.g. SiteBundle's `menu_link`, restricted to `contexts: menu` so it doesn't show up in a `Page`'s own block picker). Leave it unset (the default) for a kind meant to be usable anywhere, like the built-in kinds above. To make a `BlockType` field filter by context, pass the `context` form option:

```php
CollectionField::new('blocks')
    ->setEntryType(BlockType::class)
    ->setFormTypeOptions(['context' => 'menu'])
    // ...
```

A `CollectionField` that doesn't set `context` (the default, `null`) sees every pickable kind regardless of its declared `contexts` — existing integrations keep working unchanged until they opt in.

A few contexts are **exclusive** (`BlockRegistry::EXCLUSIVE_CONTEXTS`, currently `MENU_NAVBAR_CONTEXT` and `FLEX_COLUMNS_SLOT_CONTEXT`): there, the default rule is reversed and only a kind that explicitly declared that context is offered — a kind with no `contexts` at all, available everywhere else, is kept out. That's what keeps a navbar a plain list of links (`menu_link` alone, which opts in with `contexts: 'menu,menu_navbar'`) while every other menu location (`MENU_CONTEXT`) keeps the full picker for a footer's text, social links, logo… and what keeps a `flex_columns` row's slots to `flex_column` alone (see "Container kinds" above).

`media_required: true` rejects saving a block of that kind when it has no attached media at all (enforced by `RequiredMediaValidator` on the `Block` entity itself, not by the form) — use it for a kind whose media isn't optional decoration but the whole point of the block (e.g. `banner_title`'s background image). Defaults to `false`; only meaningful alongside `media_types`.

`media_multi_upload: true` adds a "select several files at once" input next to the usual one-file-per-row media collection, for a kind where editors routinely add many files at a time (e.g. `slider`, `article`) instead of clicking "Add" repeatedly. Each selected file becomes its own media entry, appended after the existing ones. Defaults to `false`; only meaningful alongside `media_types`.

**Un-registering a kind** is safe: a `Block` row outlives the tag that declared it, and `render_block()` skips a kind that is no longer registered rather than letting the registry throw. Dropping the tag - or uninstalling the bundle that declared it - blanks those blocks out of the pages holding them instead of taking the pages down with them. The rows themselves are left alone, so re-adding the tag brings them back.

---

## Block gallery

There is no EasyAdmin block gallery anymore - it was removed entirely, not just unlinked. Its preview variants needed inline scripts for interactivity (`slider`, `image_compare`...), and a hash/nonce-based CSP (e.g. `nelmio_security`'s `csp.hash` config) can never authorize a script trapped inside an `<iframe srcdoc="...">` attribute string - that class of CSP tooling scans a response's literal `<script>`/`<style>` elements, and content inside a `srcdoc` string is invisible to that scan. Not a bug in the gallery's own templates, a structural incompatibility.

The sidebar's "Links" section (see `c975L\UiBundle\Management\MenuProvider::getLinks()`) instead links out to <https://bundles.975l.com/pages/blocks>, the c975L ecosystem's own canonical showcase of every bundle's block kinds - rendered inline in a normal page, no iframe, no CSP conflict. That url is the `ui-block-showcase-url` config entry, pre-filled with the ecosystem showcase: an app hosting its own showcase page just changes the value, and one whose `configs.json` hasn't been reloaded yet (empty or missing key) falls back on `MenuProvider::BLOCK_SHOWCASE_URL`, the same address.

The fixture/showcase machinery the old gallery used (`BlockFixtureProviderInterface`, `GalleryShowcaseProviderInterface`, `BlockFixtureMediaAttacher`...) wasn't removed - it's what powers that `/blocks` page, and is available to any consuming app wanting to build its own equivalent showcase page (a plain controller/template, not an EasyAdmin/iframe one).

Kinds whose `media_types` (see above) start with `image/`, `video/` or `audio/` automatically get a placeholder attached (two images for `image_compare`, two images plus a video for `slider`, a video plus a cover image for `video`, one otherwise) - no fixture provider needs to handle media itself. This is done by `c975L\UiBundle\Service\BlockFixtureMediaAttacher`, a public service that any consuming app can reuse for its own showcase page - see e.g. bundles.975l.com's public `/blocks`, which calls `attach(Block $block, string $kind)` the same way instead of maintaining its own app-specific media mapping. Images are drawn in rotation from the declared pool rather than repeating a single one, so consecutive kinds on the same page don't all show the same photo - call `reset()` at the start of a request/loop building several blocks so the rotation restarts at the same photo every time. A kind with no fixture at all should show a "no example yet" placeholder instead of crashing, same as the old gallery did.

**The bundle ships none of those files itself** - they are only ever needed by the site actually hosting a showcase, and shipping them made every app installing UiBundle download 13 MB for a page it never renders. Declare your own by implementing `Contract\PlaceholderMediaProviderInterface` (auto-discovered the same way as `BlockFixtureProviderInterface`, no tag needed - see `Registry\PlaceholderMediaRegistry`/`DependencyInjection\Compiler\PlaceholderMediaProviderPass`), from wherever your app keeps them:

```php
use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;

class ShowcasePlaceholderMediaProvider implements PlaceholderMediaProviderInterface
{
    public function getPlaceholderMedia(): array
    {
        return [
            'images' => ['medias/showcase/photo-1.webp', 'medias/showcase/photo-2.webp'],
            'video' => 'medias/showcase/clip.mp4',
            'video_embed' => 'medias/showcase/clip-embed.html',
            'audio' => 'medias/showcase/loop.mp3',
            'document' => 'medias/showcase/brochure.pdf',
        ];
    }
}
```

Paths are web paths from the site root, no leading `/` - the same shape `Media::$filename` holds. Each media's mimetype follows its own extension, so a `.webm` video or an `.ogg` clip is fine. Every key is optional: leave one out, or empty, and that media is simply never attached, so a provider may cover only what it actually carries. With no provider registered at all, `attach()` adds nothing and `nextPlaceholderImage()` returns `null` - a block rendered without media rather than one pointing at files that aren't there.

### Providing sample data for your own kinds

Implement `BlockFixtureProviderInterface` (auto-discovered the same way as `BundleWhatsNewProviderInterface` - no tag needed, just register the service). Each kind maps to one or more named **variants** - use `''` as the only key when a single example is enough, or several labelled keys to show every visual style side by side (see `alert`'s info/success/warning/danger or `button`'s primary/secondary/success/danger/link in UiBundle's own `BlockFixtureProvider`):

```php
use c975L\UiBundle\Contract\BlockFixtureProviderInterface;

class BookingBlockFixtureProvider implements BlockFixtureProviderInterface
{
    public function getFixtures(): array
    {
        return [
            'booking' => [
                '' => ['title' => 'Réserver une table'],
            ],
        ];
    }
}
```

A kind with no registered fixture (empty array, or not returned at all) simply shows the "no example yet" placeholder - the gallery never breaks when a bundle hasn't caught up yet.

### Showcasing content that isn't a block kind

Some content is worth showing in the gallery but doesn't fit `BlockFixtureProviderInterface`, typically because rendering it through the real block/kind never reflects the fixture's own data anyway (e.g. SocialBundle's `social_links_display` always renders the site-wide singleton regardless of its own data, and `share_buttons()` isn't a block kind at all) or because the real template only renders something once resolved against live data this provider can't fabricate (e.g. a kind resolved against a real `Page`/route, or against an external source `CollectionSourceProviderInterface` supplies). For these, implement `GalleryShowcaseProviderInterface` instead (same auto-discovery, no tag needed) and render the underlying component/Twig function directly with made-up sample data, bypassing whatever real lookup it would otherwise do:

```php
use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use Twig\Environment;

class BookingGalleryShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(private Environment $twig) {}

    public function getShowcases(): array
    {
        return [
            'Booking widget' => [
                'description' => 'Available layouts for the standalone booking widget.',
                // "kind" ties this to "booking"'s own category and suppresses its own regular preview
                // card (which would otherwise show up empty right next to this one) - use null if there's
                // no real block kind at all (e.g. share_buttons()). "category" overrides the category
                // directly instead (no suppression) - for a kind-less showcase that still belongs next
                // to a related one, e.g. reusing a sibling kind's own category key.
                'kind' => 'booking',
                'variants' => [
                    'Compact' => $this->twig->render('@App/booking/widget.html.twig', ['layout' => 'compact']),
                    'Full' => $this->twig->render('@App/booking/widget.html.twig', ['layout' => 'full']),
                ],
            ],
        ];
    }
}
```

Each variant is already-rendered HTML (a plain string) rather than a `Block` - the gallery wraps it in the same isolated `<iframe>` a block preview gets. `'wide' => true` originally rendered a showcase's card wider, for a component whose real styles only apply above a CSS breakpoint (e.g. `share_buttons()` hides itself entirely below 768px). The gallery now renders every item full-width by default, so this flag is currently a no-op there - it's kept in the interface so an existing provider that sets it (e.g. SocialBundle's `share_buttons()`) doesn't break. See UiBundle's own `BlockFixtureProvider`'s class comment, and SocialBundle's `GalleryShowcaseProvider` for a real example.

UiBundle ships one of its own, `Service\GalleryShowcaseProvider`, covering the three built-in kinds no fixture can express: `flex_columns` and `section_cards` hold their content in `Block::$slots` (never-persisted children, rendered through `BlockExtension::renderBlock()`), and `collection` pulls its items live from a `CollectionSourceRegistry` a showcase has no reason to have (rendered instead straight through the `Collection:Grid` component, with never-persisted `collection_item` blocks). Each declares its own `kind`, so the showcase draws one card per kind rather than an empty one next to it.

---

## Forms

A generic, shared "form definition" system (`Entity\Form`/`Entity\FormField`, tables `site_form`/`site_form_field`) - any bundle can manage its own named row (e.g. ContactFormBundle's `"contact"`) in one place instead of keeping a private fields table, and an editor can also build a form entirely through the admin, with no bundle/code involved at all.

- **`Controller\Management\FormCrudController`** (menu entry under SiteBundle's "Forms", if installed) lists/creates/edits every `Form`. Each row's `fields` collection is a drag-and-drop `CollectionField` of `FormFieldType` entries (`text`/`textarea`/`email`/`checkbox`/`password`/`password_repeated`/`url`/`tel`/`number`/`date`), reordered the same way `slider`/`article` media already are (see `assets/js/ea-sortable.js`). A field's programmatic `name` (the HTML input name, the notification email key) is derived automatically from its `label` by `Service\FormFieldNamer` on save, scoped unique within the owning `Form` - skipped for an already-named `restricted` field, so relabelling it doesn't change the stable key other code looks it up by.
- **`FormField::$url`** (any field type, not just `url`-typed ones) attaches an optional link right after the field's label - e.g. a `checkbox` field's "I accept the [Terms of use]" - rendered by `FormSubmissionType::buildLabel()` as a real, escaped `<a target="_blank">`, the surrounding label text itself staying plain so clicking it still toggles the field.
- **`Entity\FormFieldTemplate`** (table `site_form_field_template`, managed by **`Controller\Management\FormFieldTemplateCrudController`**, linked from `FormCrudController`'s own toolbar) is a reusable catalog of ready-made fields (name, email, phone, subject, message, company, website, cgu, newsletter...) picked from a select right next to a `Form`'s `fields` collection instead of composing every field by hand - seed the defaults with `php bin/console c975l:ui:form-field-template:import-defaults`. Same `$restricted` principle as `FormField` locks a seeded template's name/deletion, every other property stays editable.
- **`FormField::$restricted`**/**`Form::$restricted`**: a field or a whole form seeded by its owning bundle (e.g. register's `email`/`plainPassword` fields, or ContactFormBundle's `"contact"` form itself) keeps its core identity locked (field type + deletion, or the form's own `name`) while staying reorderable/relabellable - enforced server-side, not just hidden by CSS.
- **`Form::$enabled`** (default `true`) lets an admin pause a form without unpublishing its page or clearing `action` - `FormController` renders `components/Form/FormDisabled.html.twig` instead of the actual form/submission handling while it's off.
- **The `form` block kind** (`Form\Block\FormPickerType`, template `components/Form/FormBlock.html.twig`) embeds any `Form` by name, anywhere a block can go. Rendering and submission handling is done by **`Controller\FormController`** (routes `ui_form_submit`/`ui_form_fragment`), not the block itself.
- **`Contract\FormActionInterface`**/**`Registry\FormActionRegistry`** let a bundle process a `Form`'s submission (tagged provider, one `getKey()` per implementation) without UiBundle knowing what that action actually does - `Form::$action` stores which key handles a given form, `Form::$actionConfig` (raw JSON, editable straight from `FormCrudController`) is free-shape config read only by that action.
- **`Service\SendEmailFormAction`** (key `send_email`) is the built-in provider: it lets a form built purely through the admin still notify someone by email, configured via `actionConfig`'s `to`/`from`/`replyTo`/`subject`/`template`/`senderEmailField`/`offerReceiveCopy` (all optional - unset ones fall back to the site-wide `email-*` config keys/a default template, and an unset `subject` to the translated "New message via *form name*", i.e. the same sentence the seeded notification `EmailTemplate` opens with), or `emailTemplate` (an `EmailTemplate` name, see "Email builder" below) to send a compiled `EmailTemplate` instead of `template`. It's backed by a generic **`Service\EmailService`** (`Model\EmailSendRequest` in, bool out, `getLastError()`/`consumeDebugPreview()` for the `ROLE_SUPER_ADMIN` + `email-debug` preview instead of a silent real send). A request carries exactly one body — `template` (a Twig path rendered with `context`, optionally wrapped in the site's layout via `wrapLayout`), `html` (already-rendered markup) or `text` (plain text, no template and no layout, what an operational digest written by a console command needs) — anything else is refused rather than sent as whichever the chain tested first. Implement `Contract\DebugPreviewCapableInterface` on your own action to get the same debug-preview behavior.
- **Protection**, shared with every other c975L public form (contact/register/reset): a rotating honeypot + submission-timing check (`Service\FormBotProtection`, merges what used to be separate SiteBundle/ContactFormBundle implementations), site-wide GDPR checkbox and reCAPTCHA v3 (`Service\CaptchaVerifier`/`Form\CaptchaType`, a no-op unless the `recaptcha3-site-key`/`recaptcha3-secret-key` ConfigBundle keys are both filled in - see [reCAPTCHA](#recaptcha)), and a shared rate limiter (`Service\RateLimiterGuard` consuming `limiter.ui_form`, which this bundle prepends itself - `sliding_window`, 5 attempts per 10 minutes - so every public Form is limited with nothing to configure; a site declaring its own `ui_form` in `config/packages/rate_limiter.yaml` still decides, its config being merged over that default). That limiter counts a caller rather than an address (`isAcceptedForIp()`): an IPv4 address is counted whole, an IPv6 one by its /64, the block a subscriber holds otherwise opening as many fresh buckets as it holds addresses. Every `email`-typed field also gets a live MX/A DNS check (`Validator\Constraints\DnsEmail`) on top of format/`Assert\Email` validation, and every required `checkbox`-typed field uses `IsTrue` (an unchecked box isn't `NotBlank`).
- **`Form::$links`** (`Form\FormLinkType`, its own label/url collection on `FormCrudController`) are the links shown right under a form's submit button - typically the way out of a dead end (register's "already have an account, sign in", a reset-password form's way back). They live on the `Form` itself, inside `actionConfig` rather than in a column of their own, so they follow it everywhere it's rendered: through the `form` block, through the bare route, and through the standalone re-render a failed submission goes through.
- **`Service\FormPrefillHelper`** lets app code pre-fill (and lock) a `Form`'s field(s) from session right before redirecting a visitor to it (e.g. a listing page's "Contact us about this" link setting the `subject` field) - no query string needed, cleared automatically once the submission succeeds.
- **`Service\FormSeeder`** is how a bundle gets its own `Form`/`EmailTemplate` rows in place out of the box: `ensureForm(name, coreFieldsByLocale, action, actionConfig, linksByLocale)` and `ensureEmailTemplate(name, blocksByLocale)`, both idempotent, both seeding `restricted` rows. A `Form` seeded by an earlier version is backfilled in place rather than left stale, and a field's `url` - like the form's own `links` - is only ever written while it's still unset, so an admin's edit is never overwritten. Neither method flushes - the caller decides when, so a batch of seeds stays one transaction.
- **`Contract\FormPageUrlProviderInterface`**/**`Registry\FormPageUrlRegistry`** and the **`form_url(name)`** Twig function answer where a named `Form` is actually reachable on the front end. A bundle displaying that form on something richer than this bundle's bare `ui_form_submit` route contributes its URL (SiteBundle answers with the `Page` carrying the matching `form` block, an admin-editable per-locale slug); the first provider with an answer wins, and with none the bare route is used - so a template linking to a form never has to know which bundles are installed.
- **Two ConfigBundle keys** drive the shared protections, both under the *form* group: `site-form-delay` (seconds a submission must take at minimum, the timing half of `FormBotProtection`) and `site-form-gdpr` (whether the site-wide consent checkbox is shown). They live here rather than in SiteBundle, this bundle's form layer being what reads them.

### reCAPTCHA

reCAPTCHA v3 is implemented here rather than pulled from a third-party bundle - `karser/karser-recaptcha3-bundle` was dropped, as the only part of it this ecosystem ever used was a single POST to Google's `siteverify` endpoint, while three compiler passes/type extensions existed solely to feed it ConfigBundle's values.

| Piece | Role |
| --- | --- |
| `Service\CaptchaVerifier` | Reads the keys, POSTs the token to `siteverify`, compares the score to the threshold. Fails closed: an unverifiable token is rejected, never waved through |
| `Form\CaptchaType` | The hidden field itself (`getParent()` is `HiddenType`), unmapped, unlabelled, carrying a `Validator\Constraints\Captcha` |
| `Validator\Constraints\Captcha`/`CaptchaValidator` | Server-side check. Silent when no keys are configured - nothing was rendered to solve |
| `templates/form/captcha_theme.html.twig` | The widget, registered app-wide from `c975LUiBundle::prependExtension()` |
| `assets/js/captcha.js` | The `captcha` Stimulus controller |

Three ConfigBundle keys drive it, all under the *security* group: `recaptcha3-site-key`, `recaptcha3-secret-key` and the optional `recaptcha3-score-threshold` (defaults to `0.5`; `0` is a valid value meaning "accept everything"). With either key missing, `CaptchaType` renders a bare hidden input, the validator stays silent and nothing is ever requested from Google.

**Google's `api.js` is only fetched once the visitor actually interacts with the form**, and the token is only requested on submit. Loading it upfront - what the widget it replaces did - cost ~765 KB and ~1.5 s of main thread on every page carrying a form, and dropped a `_GRECAPTCHA` third-party cookie on visitors who never submitted anything. Asking for the token at submit time also means it is always fresh, where a token grabbed on page load expires after two minutes. If Google is blocked or unreachable, the form is submitted anyway with an empty token and the server decides - a visitor is never left on a page that silently refuses to submit.

Because nothing inline is emitted, the widget needs no CSP nonce. Under a strict CSP, `script-src` still needs `www.google.com` and `www.gstatic.com`, and `frame-src` needs `www.google.com`.

Adding it to your own form type:

```php
use c975L\UiBundle\Form\CaptchaType;

$builder->add('captcha', CaptchaType::class, [
    // Reported to Google alongside the token, so the admin console can break scores down per form
    'action_name' => 'contact',
]);
```

---

## Font picker

**`Form\FontChoiceType`** is a generic font-family `<select>` (`getParent()` is `ChoiceType`), not tied to any other system here - any bundle/app form can `add('font', FontChoiceType::class)` to offer a select built from whatever **`Registry\FontRegistry`** knows about, instead of a plain text field. Implement `Contract\FontProviderInterface::getFonts(): array` (auto-discovered the same way as `BlockFixtureProviderInterface`, no tag needed - see `DependencyInjection\Compiler\FontProviderPass`) in a bundle/app declaring `@font-face` font-family names of its own (this bundle's own `Service\FontService` answers with every uploaded family, see [Fonts](#fonts) below); every registered provider contributes, the registry returning their deduplicated, alphabetically sorted union - font families add up rather than replace each other, so an app declaring `@font-face` families of its own gets them alongside the uploaded ones instead of masking them. An empty array (no provider installed) is a valid fallback a caller can handle its own way. `c975l/config-bundle`'s `ConfigCrudController` is the first consumer: it uses `FontChoiceType` for its `font`-kind config rows (e.g. `theme-font-family-title`), merging in whatever raw value is already stored so a font removed from `@font-face` since stays selectable instead of silently disappearing.

---

## Fonts

An admin uploads their own font files straight from the dashboard (`Controller\Management\FontCrudController`, TTF/WOFF/WOFF2, 5 MB max) — no dev/deploy needed. Each upload is one `Entity\Font` row (name/weight/style + the file, stored under `public/medias/fonts`); `Listener\FontCssListener` (Doctrine listener + `CacheWarmerInterface`) compiles every row into `public/bundles/build/site-fonts-uploaded.css`, one `@font-face` block per row, contributed by this bundle's own `StylesheetProvider`. No format conversion happens server-side (WOFF2 encoding needs Brotli + glyph-table transforms that plain PHP can't do without a system binary) — an admin wanting broad browser fallback just uploads the same font in more than one format, producing several `@font-face` rules with identical `font-family`/weight/style that the browser picks between.

The Font list's "Bulk import" toolbar action (`Controller\Management\FontBulkImportController`) uploads several font files at once instead of one row at a time: each file's name/weight/style is guessed from its filename (`Service\FontFilenameParser`, following the `FamilyName-WeightStyle.ext` convention used by Google Fonts and most foundries, eg. `OpenSans-Bold.ttf`), then goes through the same `Font`/`FontCssListener` pipeline as a single upload. A wrong guess is fixed afterward on that row's own edit screen.

`Service\FontService` is this bundle's own `FontProviderInterface` implementation, so every uploaded family shows up in the `FontChoiceType` select above — which is what the `theme-font-family-*` configs of `c975l/site-bundle` are rendered with. Selected fonts can be exported the same way as any other content: an "Export selection" batch action, `site-role-admin`-gated, producing a zip re-importable via ConfigBundle's **Import content** screen (`Management\FontImportProvider`). `Management\FontExportProvider` also plugs Fonts into ConfigBundle's **Export sync (everything)** dashboard shortcut.

`Twig\FontPreloadExtension` exposes **`font_preloads()`**: the admin-uploaded font files the current theme actually uses (2 at most, `path` + MIME `type` each), to emit as `<link rel="preload">` in the `<head>` — the `@font-face` rules live inside the compiled stylesheet, so without it the browser only discovers the files after downloading *and* parsing it. Cached, refreshed on any font or theme-font change.

---

## Email builder

A separate, email-safe (table layout, inline CSS, no JS) block-based system for composing email bodies (`Entity\EmailTemplate`/`EmailBlock`, tables `site_email_template`/`site_email_block`) - deliberately **not** a reuse of the page `Block` system: an email-safe vocabulary has to stay closed (no arbitrary markup can survive Outlook/Gmail), so `EmailBlock::$type` resolves through a plain `match()` in `Service\EmailTemplateRenderer` instead of a DI-tagged registry, and every kind shares one flat set of columns (same principle as `FormField`, see its own docblock) instead of a per-kind dynamic sub-form.

- **`Controller\Management\EmailTemplateCrudController`** (the "Email templates" menu entry this bundle contributes) lists/creates/edits every `EmailTemplate`. Its `blocks` collection is a drag-and-drop `CollectionField` of `Form\EmailBlockType` entries, same sortable mechanism as `Form`'s own `fields`. A "Preview" action renders the compiled HTML in a new tab (admin-only, placeholder variables left untouched). Both this index and `FormCrudController`'s own show a GDPR guidance note linking straight to the `site-form-gdpr` config row, via `Twig\ConfigLinkExtension`'s **`config_edit_url(slug)`** function (falls back to the plain Config list when that slug hasn't been loaded into DB yet).
- **Block kinds** (`EmailBlock::TYPE_*`): `heading` (h1/h2), `text` (plain text, split into `<p>` paragraphs on blank lines - deliberately not rich/Trix text, keeps the email-safe HTML fully server-controlled), `button` (bulletproof table-based button), `image` (a plain URL for now, not a Media picker - see below), `divider`, `spacer`, and `fields_table` (renders a `variables['fields']` label ⇒ value array as a table, e.g. a `Form` submission's answers - see `SendEmailFormAction` below).
- **`image`'s url** can be just a path (e.g. `/medias/logo.webp`) instead of a full absolute URL - `EmailTemplateRenderer` resolves it against the single `site-url` ConfigBundle parameter (same one `fullLayout.html.twig` itself already builds the logo's `src` from), so the domain lives in one place instead of being hand-typed into every image block and going stale the day it changes. An already-absolute url (`http(s)://`, an external/CDN image) is left as-is.
- **Placeholders**: any `heading`/`content`/`label`/`url`/`alt` field may contain a `{{ variable_name }}` token, resolved by `EmailTemplateRenderer` via a literal `strtr()` against the `$variables` array passed to `render()`/`renderBody()` - **not** real Twig evaluation (an `EmailBlock`'s text is admin-authored data, not code; handing it to `Twig::createTemplate()` would open a server-side template injection hole).
- **`EmailTemplateRenderer::render()`** returns one standalone `<html>` document. When an `EmailLayoutProviderInterface` is registered (e.g. SiteBundle, bringing its own branded header/footer), the compiled body is wrapped through it - so the admin **preview** action and a real `EmailTemplate`-based send (e.g. `SendEmailFormAction`) both render the same way a recipient would actually see it. With no provider registered (e.g. an app with no SiteBundle), it falls back to its own bare, un-branded shell (`templates/emails/blocks/_wrapper.html.twig`). Implement `Contract\EmailLayoutProviderInterface::wrap(string $bodyHtml): string` (auto-discovered the same way as `BlockFixtureProviderInterface`, no tag needed - see `Registry\EmailLayoutRegistry`/`DependencyInjection\Compiler\EmailLayoutProviderPass`) to provide your own; only the first registered provider is used. **`renderBody()`** returns just the compiled `<table>` fragment, with no document/layout of its own - meant to be embedded inside a real `.html.twig` template via the **`email_template_body(name, variables)`** Twig function (`Twig\EmailTemplateExtension`, `is_safe: html`), the same way for every email that's actually sent. **`renderNamed(name, variables)`** is `render()` for a template designated by its name rather than held as an entity - what a bundle sending a transactional email has, instead of a per-app Twig path it would have to know. It returns `null` when no template carries that name, so the caller decides what a missing template means; `c975l/config-bundle` uses it for the `account_validation`/`password_reset` emails, which therefore have no `.html.twig` file of their own at all. A template that does want a file around it - `c975l/site-bundle`'s `templates/emails/contact_notification.html.twig` - plainly `{% extends "@c975LSite/emails/layout.html.twig" %}` and calls `email_template_body('contact_notification', {...})` in its `content` block, an explicit, ordinary Twig `extends`, not a bundle-template-override. `email_template_body()` silently renders nothing if `name` isn't found, so a missing/renamed `EmailTemplate` never breaks the email it's embedded into.
- **The shared email stylesheet** (`sass/emails.scss` and its `sass/emails/` partials, compiled to `public/css/emails.min.css`) is the base every c975L bundle sending mail renders against - six of them do (Ui, Site, Shop, Payment, Social, Crowdfunding) and only one can count on SiteBundle being installed, which is why it lives here. It is deliberately not `styles.scss` with overrides: an email is laid out in tables and read by clients that ignore most of a page stylesheet, so the page's layout layer (sections, flats, grids, flex) has no counterpart in it. Reach it from your own email layout through the **`@c975LUiCss`** Twig namespace, before adding your branding:

```twig
<style>{{ source('@c975LUiCss/emails.min.css')|resolve_css_variables }}</style>
```

- **`resolve_css_variables`** (`Twig\CssVariableExtension`, backed by `Service\CssVariableResolver`) replaces every `var()` and `color-mix()` by the value it resolves to, from the `:root` declarations of the stylesheet it is given. No mail client resolves a custom property, and a CSS inliner copies declarations verbatim into `style=""` attributes - so without it an email reaches Gmail, Outlook and most mobile clients with all its colored declarations dropped. Apply it to the whole `<style>` **before** inlining. `EmailStylesheetTest` fails if an email rule reads a token the stylesheet doesn't declare.

- **`SendEmailFormAction`** resolves the email body from `Form::$actionConfig`'s `template` (a Twig path, e.g. one that itself calls `email_template_body()` - see above, the default `send_email` config), falling back to the legacy `@c975LUi/emails/form_submission.html.twig` when unset. An `emailTemplate` key, naming an `EmailTemplate` directly, is also available and takes over instead when set and found (rendered standalone via `render()`, no layout) - handy for a Form built purely through the admin in an app with no dedicated Twig template of its own to point `template` at.

---

## AI Assistant

Two independent, optional features sharing one display name (hardcoded `"Donovan"`, see `AiRephraseExtension::assistantName()`), both disabled by default and config-driven through ConfigBundle (`c975l:config:load-all` loads their default rows from `config/configs.json`). **This bundle makes no assumption about what's behind either feature - bring your own backend.** No default endpoint, no default API key, no bundled AI provider: every value below starts `null`/`false`, and each feature stays entirely inert until a consuming app fills the rest in.

The sidebar link reads `"Donovan (AI Agent)"` - the `"(AI Agent)"` half is translated (`label.ai_assistant_menu_suffix`), composed once in `MenuProvider::getLinks()` rather than left as a translation key, since `MenuBuilder` only ever calls `trans()` on the whole label as one string.

### Dashboard assistant ("what block should I use?")

A free-text question box (`AiAssistantController::index()`/`ask()`). **`ROLE_SUPER_ADMIN` only** - stricter than the rest of the page (`"site-role-admin"`, see below): this calls a backend that's typically a shared/mutualized resource paid for by whoever operates it (e.g. Laurent's own 975l.com, across every one of his client sites), so who can spend against it is deliberately kept narrow rather than opened to every editor, even though answering "which block should I use" would otherwise suit editors best. The dashboard section of the AI Assistant page is hidden entirely for a viewer without that role, not just disabled.

The same question box also appears as a card directly on ConfigBundle's dashboard home (`Management\DonovanWidgetProvider`, implementing its `DashboardWidgetProviderInterface`) - under the exact same conditions as the page's own dashboard section above (`isEnabled()` + `ROLE_SUPER_ADMIN`), so it never shows a "not set up yet" state itself.

| Config slug | Purpose |
| --- | --- |
| `ui-ai-assistant-dashboard-enabled` | Master switch - while `false`, the page's dashboard section shows setup steps and a link to Config instead of the question box |
| `ui-ai-assistant-dashboard-endpoint` | Plain HTTP URL the question is POSTed to (`{"question": "..."}`) |
| `ui-ai-assistant-dashboard-token` (sensitive) | Bearer token sent with the request |

The actual call is made by `AiAssistantClient`, the default implementation of `Contract\AiAssistantClientInterface::ask(string $question): ?array{answer: string, sources: array{label: string, url: string}[]}`. It only knows how to POST to a URL and parse `{"answer": "...", "sources": [{"label": "...", "url": "..."}]}` back - it has no idea what's on the other end, what data backs the answer, or whether that endpoint mutualizes questions across several sites. `sources` is always present (defaults to `[]` if a backend omits it) - a plain `{label, url}` pair per citation, never a bare identifier a frontend would have to resolve into a URL itself, since this bundle makes no assumption about what URL scheme a backend's citations resolve to. Override the service (standard Symfony service decoration/alias) to swap in a different transport - e.g. a purely local implementation with no network call at all.

**Self-hosting your own backend instead of a shared/mutualized one.** Nothing here requires 975l.com specifically - any consuming app can point `ui-ai-assistant-dashboard-endpoint`/`-token` at a controller of its own, as long as it honors the contract above. Minimal shape:

- A route accepting `POST` with `Authorization: Bearer <token>` (check it yourself, no Symfony Security expected - `AiAssistantClient` sends a plain bearer header) and a `question` field (JSON or form-encoded, your choice), returning the `{answer, sources}` shape above.
- To actually answer "which block should I use", the endpoint needs context about *your* block system: build a prompt from `BlockRegistry::all()` (kind, `getLabel()`, `getDescription()`, `getCategory()` per entry) so the LLM only ever cites real kinds instead of hallucinating one - the `sources` you return should point wherever your own block gallery/showcase lives (see "Gallery Showcase Providers" below), not a fixed URL this bundle can't know.
- Same provider choice as the rephrase feature below (Anthropic native, or any OpenAI-compatible API like Euria/Infomaniak) - nothing here forces reusing `AiRephraseClient`'s config namespace, a self-hosted endpoint is free to read its own config slugs for its own key/provider/model.
- This is exactly what 975l.com's own `AiHelpController`/`AiHelpService` do (not shipped in this bundle - it's app-specific, not a bundle concern) for every one of Laurent's client sites, so they don't each need their own key: a working reference if you want to see the shape end-to-end, but self-hosting yours doesn't require reading it.

That reference implementation also layers two caching tiers in front of the LLM call, worth knowing about once your own self-hosted backend's question volume grows past a handful of one-off questions - both entirely optional, a backend answering every question fresh from the LLM with no caching at all is a perfectly valid starting point (see the maker below):

- **Exact cache**: a SHA-256 hash of the normalized question text. A hit skips the LLM entirely - the cheapest tier, but only ever matches a byte-for-byte repeat of a question already asked.
- **Semantic cache**: on an exact-hash miss, the question is vectorized (an embedding call - a model distinct from the chat one, chat models aren't usable as embedding models) and compared by cosine similarity against every previously-answered question still valid for the current context version. A close-enough match (a configurable similarity threshold) reuses that answer instead of triggering a fresh LLM call, so a rephrasing ("how do I add a page?" vs "how do I create a page?") is recognized as the same question. The vectors are stored as MariaDB's native `VECTOR(n)` type (11.7+, `VEC_DISTANCE_COSINE()` computes the similarity directly in SQL) - `Doctrine\VectorType`, shipped in this bundle (not app-specific, unlike the rest of this backend), maps a PHP `float[]` to it (pack/unpack as raw little-endian float32 bytes, the same bytes `VEC_FromText()`/`VEC_ToText()` convert to/from; bind it as `ParameterType::BINARY`, not the default `STRING`, or the client charset conversion silently corrupts the bytes). On an older MariaDB/MySQL without native vector support, a plain JSON column plus an in-PHP cosine similarity comparison is a perfectly viable fallback at the scale a single backend's question corpus is ever likely to reach - no approximate-nearest-neighbor index needed either way at that scale.

Since this is several files, not one: `php bin/console c975l:ui:donovan-qa:create` (needs `symfony/maker-bundle`, dev-only, see "Registering a custom block kind" above for the same dev-dependency note) scaffolds a working skeleton matching the shape above - a controller (`App\Controller\Api\DonovanQaController`, route `POST /api/donovan-qa/ask`), an LLM client (`App\Service\DonovanQaLlmClient`, same Anthropic/Euria dispatch as `AiRephraseClient`), a block-context builder (`App\Service\DonovanQaContextBuilder`, wraps `BlockRegistry`), a status/setup-guide Twig extension + template override for this same page (so a "Donovan (Q&A)" section appears here too, matching the two sections above), and a test skeleton. It prints the `config/configs.json` snippet for the 6 needed config slugs (`donovan-qa-llm-enabled`, `donovan-qa-llm-provider`/`-api-key`/`-model`/`-base-uri`, `donovan-qa-authorized-tokens`) to paste rather than writing it - same reasoning as `c975l:ui:block:create` not touching `services.yaml`: creating/loading app-level config entries is app-specific (`ConfigCrudController` disables manual `Config` creation on purpose, so an app needs its own `loadDefaultConfig()`-calling command if it doesn't have one yet, see 975l.com's own `AppConfigLoadCommand` for a 15-line model). `sources[].url` in the generated context builder is a placeholder (`''`) - fill in wherever your own block gallery/showcase lives, this bundle can't guess it.

The maker asks one interactive question - *"Add a semantic cache ... on top of the exact-hash cache?"* - defaulting to **no** (`--no-interaction` skips it and keeps the minimal exact-hash-only shape above, no prompt hanging a scripted install). Answering yes additionally generates: `App\Entity\DonovanQaAnswer` (the cache row - hash, text, contextVersion, sources, hit/token counters, and a nullable embedding column), `App\Repository\DonovanQaAnswerRepository` (`findOneByQuestionHash()` plus `findBestSemanticMatch()`, the raw `VEC_DISTANCE_COSINE()` query - table/column names resolved from Doctrine's own metadata rather than hardcoded, so it doesn't assume your app's naming strategy), `App\Service\DonovanQaEmbeddingClient` (the embedding call), `App\Service\DonovanQaService` (the cache-or-call orchestration the controller delegates to instead of calling the LLM client directly - `DonovanQaContextBuilder` gets no context-version scheme of its own here, a `TODO` in the generated service flags where to add one once that matters for you), and test skeletons for the new client and service. It then prints the 3 additional config slugs (`donovan-qa-embedding-model`, `donovan-qa-semantic-cache-enabled`, `donovan-qa-semantic-cache-threshold`), the `doctrine.yaml` snippet registering `c975L\UiBundle\Doctrine\VectorType` as the `vector` type (`dbal.types` + `dbal.mapping_types`, both needed - the former for reading/writing values, the latter so `doctrine:schema:validate`/`make:migration` recognize a `VECTOR(n)` column as this type from introspection instead of trying to alter it away), and a reminder to run `make:migration` (needs MariaDB 11.7+/MySQL 9+ for the native `VECTOR` type the generated entity uses - on an older version, adapt the generated entity/repository to the JSON-column fallback described above instead, the maker doesn't generate that variant).

This bundle also implements ConfigBundle's `Management\ProcedureProviderInterface` itself (`Management\ProcedureProvider`, auto-tagged like `MenuProviderInterface`, no service config needed), contributing its own admin procedures (e.g. "create a form", "create an email template") from `config/procedures.json` - each entry a `slug`/translated `title`/translated `body` triple, merged with every other bundle's via ConfigBundle's `ProcedureBuilder` for the consuming app's dashboard assistant/`DonovanQaContextBuilder` to draw on, same reasoning as `BlockRegistry` for block kinds above.

It also implements ConfigBundle's `Management\GuidedProjectProviderInterface` (`Management\UiGuidedProjectProvider`, auto-tagged the same way), contributing three replayable exercises to the dashboard's "Guided projects" panel: **"Téléverser une image"** (upload it into the media library once, then reuse it in as many blocks as you like), **"Créer un formulaire"** (build it field by field, then drop it into a page through its block) and **"Personnaliser un e-mail"** (rework a sent e-mail's content, made of the same blocks a page is). They continue the order sequence after ConfigBundle (10-30) and SiteBundle (50-80), picking up at 90. Only the opening step of each carries an `url`: from there the panel walks the screen the user has been sent to, highlighting the button or the field they are meant to use next (`.action-new`, `#Media_alt`, `[data-form-field-template-catalog-url]`…). A collection field is pointed at through its row marker rather than an id: EasyAdmin's `collection_widget` replaces `form_widget_compound` entirely, so no `id` is rendered on it and its label is a `<legend>` with no `for`.

### Rephrase button (content editing)

A "rephrase" action calling the editor's own AI provider directly - no intermediary, and **the rephrased content itself is never persisted or logged**, the request/response round-trip is otherwise stateless (`AiAssistantController::rephrase()` / `AiRephraseClient`). **`"site-role-admin"`** (`ROLE_ADMIN` by default) - lower than the dashboard assistant's `ROLE_SUPER_ADMIN` since this spends the site's own key/budget, not a shared one, but still above a plain editor. The only thing that outlives the request is an aggregate token count (see below) - a number reveals nothing about what was rephrased. The button only appears when the feature `isEnabled()` - no permanently-visible-but-disabled state, matching the dashboard assistant's own page. Works on plain text: rich formatting (bold, links, lists...) is not preserved across a rephrase, a deliberate scope limit rather than an oversight.

The result is never a straight replacement - `ai-rephrase.js` appends it after the original, separated by `\n\n---\n\n`, so both stay directly editable in the same field. An editor keeps, deletes or merges either side by hand, rather than losing the original the moment a rephrase comes back or needing a separate "apply" step.

Two independent selects are sent with the request: a style (`AiRephraseClient::getStyles()` - `neutral`/`professional`/`friendly`/`concise`/`persuasive`/`simple`/`enthusiastic`/`expanded`) and a length (`AiRephraseClient::getLengths()` - `same`/`shorter`/`longer`, defaulting to `same`). Both index a closed `const` map on the server side - an unexpected/tampered value falls back to its default rather than being forwarded to the LLM, so neither can be used to inject arbitrary prompt instructions.

Every Trix-edited field gets it automatically (`block_theme.html.twig`'s `trix_editor_widget`, no per-field wiring needed). A **plain** textarea does not, by default: the same block also renders technical/structured values dashboard-wide (e.g. ConfigBundle's Config CRUD renders its `json`-kind values through a plain textarea too), so showing the button unconditionally there would be actively wrong. A plain-text content field opts in explicitly:

```php
TextareaField::new('summary')
    ->setFormTypeOption('attr', ['data-ai-rephrase' => 'true']),
```

(see SiteBundle's `PageCrudController::configureFields()`, `summarySocialNetwork`, for a real example)

A third, field-independent spot: once enabled, the AI Assistant page itself shows a free-standing textarea wired to the same `_ai_rephrase.html.twig` partial and `ai-rephrase.js` controller - useful for rephrasing arbitrary text that isn't tied to any CMS field at all (a draft, something pasted in from elsewhere...).

| Config slug | Purpose |
| --- | --- |
| `ui-ai-assistant-rephrase-provider` | `anthropic`, `openai` or `euria` |
| `ui-ai-assistant-rephrase-api-key` (sensitive) | The editor's own key for that provider - billed to whoever owns it |
| `ui-ai-assistant-rephrase-base-uri` | Required only for `euria` (or any other OpenAI-compatible API): its base URI, since Euria/Infomaniak AI Tools exposes an OpenAI-compatible API under a different host |
| `ui-ai-assistant-rephrase-model` | Optional for `anthropic`/`openai` (each falls back to a reasonable default), **mandatory for `euria`** - its catalog isn't static enough to hardcode a default, `isEnabled()` stays `false` without it. Recommended: `mistralai/Mistral-Small-4-119B-2603` |

`anthropic` and `openai` are called with their native APIs; any other value is treated as an OpenAI-compatible API using `ui-ai-assistant-rephrase-base-uri` as its host - this is how Euria is supported without provider-specific code.

A style selector (`neutral`/`professional`/`friendly`/`concise`) sits next to the button, backed by `AiRephraseClient::rephrase(string $text, string $style = 'neutral')` - each style maps to a fixed prompt fragment server-side (`AiRephraseClient::STYLES`), a closed list the request's raw `style` value is only ever used to *index*, never interpolated into the prompt itself, so a tampered value can't inject arbitrary instructions. `getStyles(): array` is the whole surface a custom UI needs to build its own selector.

### Rephrase spend tracking

Every provider response already includes its own token usage - `AiRephraseClient` reads it and hands it to `AiUsageTracker`, which rolls it up into one `AiUsage` row per calendar month (`inputTokens`/`outputTokens`/`requestCount`), not one row per request: a per-request log would tie a token count to a timestamp close enough to correlate with a specific edit, which the "nothing is persisted" promise above is precisely there to avoid. `AiUsageTracker::getCurrentMonth()` is the read side - shown on the AI Assistant page when the rephrase feature is enabled.

A failed call (bad/revoked key, provider outage...) is recorded on the same row instead of silently swallowed, and cleared automatically on the next successful call. While a failure is recorded, `AiAlertProvider` (implementing ConfigBundle's `AlertProviderInterface`) surfaces a dashboard-wide **warning** - so a broken key doesn't go unnoticed until an editor happens to report it. No proactive key validation on save: an invalid key is a normal runtime failure (a cheap, immediate 401 - no tokens billed, nothing resembling abuse to a provider), not something worth a dedicated "test connection" flow - the rephrase button itself just surfaces a plain error message on failure, same reasoning.

`AiAlertProvider` also surfaces two low-key **info** alerts (not warnings - being off is the normal, intended state for a site not using either feature) whenever the dashboard assistant or the rephrase feature isn't fully configured yet (missing/false `*-enabled`, endpoint, token, provider or key) - a discovery nudge on top of the Config screen's own label/description, for an app operator actively rolling either feature out across several sites.

Every one of these three alerts links to the AI Assistant page itself (`management_ui_ai_assistant_index`), not straight to the Config screen: the page is the actual "what do I do" landing spot. Each missing setup step links directly to *that* config row's edit page (`AiAssistantController::configLinks()`, one `AdminUrlGenerator` lookup per slug via `ConfigRepository`) rather than the raw config list - and for the rephrase key specifically, a short "where to get one" note per provider (Anthropic/OpenAI/Euria), same three-part structure for each (site → what to click → billing note), since the dashboard's own endpoint/token aren't self-service at all - they're whatever the backend's operator hands out, so that step just says to ask them instead.

### On cost and abuse

Both keys are the consuming app's own, entered as `sensitive` config (encrypted at rest by ConfigBundle's `VaultEncryptor`) - this bundle has no billing relationship with any provider. An app centralizing the dashboard endpoint across several of its own sites (one shared backend, one shared token) is responsible for its own rate limiting on that backend - a leaked token can otherwise be called from anywhere, same as any bearer-token API.

---

## Block render cache

`BlockExtension::renderBlock()` (called by the `render_block()` Twig function, itself used by the `<twig:c975LUi:Blocks:Block>` component) caches each block's rendered HTML in `cache.app` (via `TagAwareCacheInterface`), keyed by `block_render_{id}_{locale}` with an infinite TTL - no re-render, no DB round trip for the block's own data, on every subsequent hit across every visitor. `BlockCacheInvalidationListener` (`src/Listener/`) invalidates it automatically: it listens to `postUpdate`/`preRemove` on both `Block` and `Media` (an image/audio/video swap doesn't touch the parent `Block`'s own fields, so it has to be watched too) and calls `$cache->invalidateTags(['block_{id}'])`. This fires for *any* origin - EasyAdmin, an importer, another bundle - since it's a Doctrine listener on the entity class itself, not tied to a specific controller.

Locale is part of the cache key because a kind's template can render different content per `app.request.locale` even though `Block::$data` didn't change (e.g. `legal_model`, which includes a different legal-text template per locale).

The key carries **no template version**, so a release that only changes a Twig template invalidates nothing on its own — every page would keep serving the markup built by the previous release. `BlockCacheClearer` (`src/Service/`) covers that: it implements Symfony's `CacheClearerInterface`, so `bin/console cache:clear` invalidates the whole `blocks_all` tag. Any deployment already running `cache:clear` therefore picks up template changes with no step of its own, and nothing needs to be repeated in each site's deploy script. The dashboard's own "clear cache" shortcut (`BlockShortcutController`) stays available for clearing it by hand between deployments.

**Set `cacheable: false` on a kind whenever its rendered output isn't a pure function of `(Block::$id, Block::$data, locale)`** - i.e. whenever caching it under its own block id could serve stale or wrong-visitor content:

- **Embeds a per-request form** (CSRF token, session state): `ContactFormBundle`'s `contact_form` is the current example. A cached form would hand every visitor the same CSRF token.
- **Reads another Block's data**: SocialBundle's `social_links_display` is a data-less "pointer" kind that always renders the site-wide `social_links` singleton found via `BlockRepository::findOneByKind()`. Caching it under *its own* id would never see updates to the singleton it points at.
- **Queries unrelated entities live**: BookBundle's `book_series`/`book_books`/`book_to_be_published`/`book_serie_strips` list `Book`/`Serie`/`Strip` records via `BookBlockExtension`'s Twig functions - entities `BlockCacheInvalidationListener` doesn't watch, so a newly published book wouldn't ever invalidate the cache.

When in doubt, default to `cacheable: false`: the cost is one avoidable render per hit, not a correctness bug.

### Keeping a kind cacheable despite reading outside data

A kind that reads live data `BlockCacheInvalidationListener` doesn't watch would normally have to fall back to `cacheable: false` (see above) - implement `BlockCacheTagProviderInterface` instead (auto-discovered the same way as `BlockFixtureProviderInterface`, no tag needed) to keep it cacheable while adding your own extra cache tag(s) on top of the default `block_{id}`/`blocks_all` ones, then invalidate that tag yourself wherever the outside data actually changes:

```php
use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

class ArticlesSliderCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function getCacheTagResolvers(): array
    {
        return [
            'articles_slider' => fn (Block $block): array => ['articles_slider_' . $block->getData()['pageId']],
        ];
    }
}
```

SiteBundle's own `ArticlesSliderCacheTagProvider` is a real example: `articles_slider` resolves another `Page`'s own `article` blocks live at render time, so its listener tags the render with `articles_slider_{pageId}` and invalidates that tag whenever one of that page's articles changes.

---

## Legal models

Pre-built legal templates are available for **France**, in French (`fr`), English (`en`) and Spanish (`es`).
They live here rather than in SiteBundle so a site running Ui with a satellite bundle (shop, book…) and no page
management at all still has its terms of sales and its privacy policy.
Templates live under `templates/models/{country}/{model}.{locale}.html.twig` — the country is a directory,
the locale a filename suffix:

| Model | Path |
| --- | --- |
| Cookies policy | `@c975LUi/models/france/cookies.{locale}.html.twig` |
| Copyright | `@c975LUi/models/france/copyright.{locale}.html.twig` |
| Legal notice | `@c975LUi/models/france/legal-notice.{locale}.html.twig` |
| Privacy policy | `@c975LUi/models/france/privacy-policy.{locale}.html.twig` |
| Terms of sales | `@c975LUi/models/france/terms-of-sales.{locale}.html.twig` |
| Terms of use | `@c975LUi/models/france/terms-of-use.{locale}.html.twig` |

They pull the site's own data from ConfigBundle as they render, and a few sections only appear once the
matching config value is filled in — `site-other-cookies` and `site-other-copyright` add their own section,
`site-owner` the "Owner" one, and the Matomo opt-out link in the cookies policy needs `site-matomo-url`.

**Feel free to contribute translations or add templates for other countries.**

### Rendering a model

The normal way is the `legal_model` block: pick a model and, optionally, a "latest update" date from the
back-office. SiteBundle's `c975l:site:pages:import-defaults` already creates one such page per model, on a site running it. The block renders the model for the *current request locale*, so a page
served in `en` picks `legal-notice.en.html.twig` on its own — no per-locale page needed.

The four models that carry a date (legal notice, privacy policy, terms of sales, terms of use) display the
**later** of two values: the model's own revision date, hardcoded at the top of the template, and the
`latestUpdate` set on the block. A model revised upstream therefore can never display a date older than its
own content. The cookies policy and the copyright notice display no date at all.

To render a model with no block anywhere — an app holding no page management, or one serving its terms of
sales from its own controller — `legal_model_html()` renders the same finished document, `%config%` markers
resolved and all:

```twig
{{ legal_model_html('france/terms-of-sales', '2026-01-01') }}
```

Its third and fourth arguments are the customization delta and the locale, both optional: passing none renders
the bundle's own text in the request's locale. There is no customization screen behind that call - a delta
handed to it is the caller's own.

The plain `{% include %}` works too, for a page wanting the template and nothing else:

```twig
{% extends 'layout.html.twig' %}

{% trans_default_domain 'ui' %}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {% include '@c975LUi/models/france/terms-of-sales.fr.html.twig' with {latestUpdate: '2026-01-01'} %}
{% endblock %}
```

Always pass `latestUpdate` there, even as `null` — the four dated models read it unguarded, so omitting it
entirely throws under `strict_variables`. Passing `null` falls back on the model's own revision date.

The models are plain markup, with no Twig `{% block %}` of their own: there is nothing to `{% embed %}`. To
rework a model wholesale as a developer, override the template in the app
(`templates/bundles/c975LUiBundle/models/france/…`) — Symfony's usual bundle template override. To let the
site's own owner adjust it from the back-office, see below.

### Customizing a model, unit by unit

**Management → Legal models** (in the sidebar's collapsed "Advanced" submenu) lists every `legal_model` block
of the site and opens a screen where the site's owner can, section by section:

- **hide** a section that doesn't apply (a showcase site with no terms of sales),
- **rewrite** its wording, and retitle it,
- **move** it, by dragging its card,
- **add** sections of their own, at the top level or nested under one of the model's, and remove them again.

Its first column says where each document sits, which only the bundle owning the block can tell: implement
`BlockLocationProviderInterface` (SiteBundle does, for its pages) and the column fills in, along with the
public url the drift check below tests. Nothing implementing it means a screen listing the documents with no
location, which is exactly what an app with no page management gets.

The screen shows the bundle's own text, editable in place - not empty "override me" fields. Each card has a
"back to the bundle's text" button, which is also how a section is put back on the updatable path. Reordering
reuses the same drag and drop as the block collections (`ea-sortable`), scoped per level: a sub-section is
dragged inside its own section and never climbs out of it.

The screen is also reachable from the page itself: browsing a legal page while logged in as `site-role-editor`,
UiBundle's hover **Edit** button — which points at the block's row in the Page form for any other block — opens
this screen instead (`LegalModelEditUrl`, called by whichever bundle owns the block - SiteBundle's `SiteBlockEditUrlProvider` for a Page), and hovering one section of the document rather than the
document as a whole lands straight on that section's own card (`focusUnit`, read by the `legal-model`
controller). The per-section URLs are built client-side by the `legal-model-edit` controller, from the one URL
the block's wrapper carries: a rendered `legal_model` block is cached and served to every visitor alike, so no
editor-only URL is ever rendered into it.

What makes this different from copying the model into the page: **only what was explicitly changed is
stored**. What comes back from the editor is compared against the bundle's own wording, so opening the screen
and saving it untouched stores nothing at all. The comparison ignores the `class`/`style` attributes, because
Trix re-serializes whatever it is handed and drops them - taking that for a rewrite would freeze every section
of the document on its first save. The delta lives in the block's `data` under `customization`, keyed by the `data-legal-id` each
`<section>` and `<h3>` of the templates carries — identifiers slugified from the English headings, so they
mean the same thing in all three locales and a delta survives a locale switch. Everything untouched keeps
being rendered from the bundle's own template, which means **it keeps arriving with every `composer update`**.
A block with no delta at all skips the DOM pass entirely and renders exactly what it rendered before this
existed — nothing to migrate, on any existing site.

The granularity is the `<section>` and, inside it, the `<h3>`: a section's own body is what sits between its
heading and its first sub-section, so rewriting a section leaves its sub-sections alone, and vice versa.

### `%config%` markers

The models read the site's own data through `legal_var()` rather than `config()`: `site-name`, `site-owner`,
`site-director`, `site-director-location`, `site-contact-email`, `site-contact-phone`, `site-producer`,
`site-hosting-provider` and `site-dpo` - all nine declared by ConfigBundle, so they are there whatever else the
site installs. It resolves on the spot, so a model rendered any way at all — a
`legal_model` block, or the plain `{% include %}` shown above — reads as finished text, with nothing to
post-process.

The customization screen is the one place that sees them as `%site-name%` markers instead, and what the client
writes there is stored carrying those markers. `LegalModelRenderer` substitutes whatever is left over the
finished document.

That's what keeps a rewritten section alive: a client who retypes the liability clause can still write
`%site-name%` in it, and renaming the site later still updates their text. Only those nine slugs substitute;
anything else stays inert text, so no back-office field can read a config value the models never showed. The
markers are listed on the customization screen itself.

Note that a `legal_model` block is cached per (block, locale) like any other. Saving the customization flushes
it; changing a ConfigBundle value does not — use the "Clear the block cache" dashboard shortcut, exactly as
for every other block that reads the configuration.

### When the bundle reworks a text you had rewritten

Each override stores a fingerprint of the bundle wording it replaced. When a later release rewords that same
passage, the `legal_model` health check provider reports it on ConfigBundle's **Health check** page — as an
`ok` row, never a warning: it feeds neither the dashboard alerts nor the digest email. The customization
screen then shows the new wording next to the section, and nothing else happens. Merging two versions of a
legal text is not something a bundle gets to decide: the page is the site owner's responsibility, and the
update is offered, never applied.

A site that never customized anything reports nothing at all — it simply keeps receiving the updates.

---

## Generic Twig filters and functions

Five general-purpose helpers, none of them tied to blocks or media - they live here rather than in SiteBundle (where they started) so an app running on ConfigBundle + UiBundle alone still has them.

| Helper | Role |
| --- | --- |
| `\|nl2br` | Overrides Twig's native filter to emit `<br>` rather than its XHTML `<br />`, which the W3C validator flags. Keeps the native `pre_escape`, so `{{ value\|nl2br }}` still escapes its input and only an explicit `{{ value\|raw\|nl2br }}` passes markup through |
| `\|linkify` | Turns bare `http(s)://` URLs in a plain string into real `<a target="_blank" rel="noopener noreferrer">` links. Splits the raw string first and escapes each part separately, so a quote right after a URL still stops the match; trailing sentence punctuation stays out of the href |
| `route_exists(name)` | Whether a route of that name is declared - what a shared template needs before linking to a route only some installs declare |
| `template_exists(path)` | Whether `templates/<path>` exists in the app, for an override a bundle offers but doesn't ship |
| `asset_exists(path)` | Whether `public/<path>` or `assets/<path>` exists, same idea for an optional image/stylesheet |

## Reusable Twig components

Block templates are thin adapters around a set of Symfony UX Twig components living in `templates/components/`, callable directly in your own templates as `<twig:c975LUi:Group:Name .../>`.

| Component | Purpose |
| --- | --- |
| `<twig:c975LUi:Alert:Alert>` | Bootstrap-style alert box |
| `<twig:c975LUi:Article:Article>` | Single article (title/content/media) |
| `<twig:c975LUi:Article:Articles>` | Loops `Article` over a collection |
| `<twig:c975LUi:Audio:Audio>` | HTML5 audio player |
| `<twig:c975LUi:Blocks:Block>` | Renders one `Block` entity via its registered kind template |
| `<twig:c975LUi:Blocks:Blocks>` | Loops `Block` over a collection, auto-wraps consecutive `card` blocks in a `.cards` flex row |
| `<twig:c975LUi:Button:Button>` | Styled button/link |
| `<twig:c975LUi:Card:Card>` | Bootstrap card |
| `<twig:c975LUi:Card:Cards>` | Loops `Card` over an externally-supplied collection (no `Block` involved) |
| `<twig:c975LUi:Collection:Grid>` | Section title, followed by a grid of already-rendered items (see the `collection` block below) |
| `<twig:c975LUi:Contact:Details>` | Contact details panel, publishing the same fields as a schema.org JSON-LD graph |
| `<twig:c975LUi:Cta:Band>` | Centered call-to-action panel (title/text/button) |
| `<twig:c975LUi:Expertise:Banner>` | Dark panel with text and a list of tags |
| `<twig:c975LUi:Feature:Bar>` | Row of short arguments (title + caption) |
| `<twig:c975LUi:Hero:Hero>` | Header banner with title, subtitle, optional CTA buttons and image |
| `<twig:c975LUi:Image:Icon>` | Small icon image |
| `<twig:c975LUi:Image:Image>` | Responsive image |
| `<twig:c975LUi:Image:Link>` | Image wrapped in a link |
| `<twig:c975LUi:Pagination:Pagination>` | Pagination links |
| `<twig:c975LUi:Portfolio:Grid>` | Grid of project cards sourced from a block's own medias |
| `<twig:c975LUi:Process:Steps>` | Section title followed by numbered steps |
| `<twig:c975LUi:Progress:Bar>` | Progress bar |
| `<twig:c975LUi:Section:Cards>` | Section title followed by a stack of full Card blocks (`.cards` row) |
| `<twig:c975LUi:Section:Features>` | Section title followed by a grid of features (icon/title/text) |
| `<twig:c975LUi:Slider:Slider>` | Image/media slider |
| `<twig:c975LUi:Text:Hook>` | Lead-in paragraph, set apart from the text it introduces |
| `<twig:c975LUi:Text:Readmore>` | Collapsible "read more" text block |
| `<twig:c975LUi:Text:Section>` | Text section with optional image |
| `<twig:c975LUi:Video:Iframe>` | Embedded video iframe (YouTube etc.) |
| `<twig:c975LUi:Video:Video>` | HTML5 video player |

Props match the Twig variables used inside each template — see `templates/components/<Group>/<Name>.html.twig` for the exact list.

Two filters go with them, both from `Twig\TrixExtension`: **`trix_inline`** drops Trix's block-level `<div>` wrappers where only phrasing content is allowed, joining the lines with `<br>`; **`plain_text`** reduces editor HTML to the text a caption, an `aria-label` or a `<meta>` can hold. `plain_text` decodes the entities `striptags` leaves behind — without that a `&amp;` reaches the page as `&amp;amp;`, Twig having escaped it a second time. `Image:Link` uses it for its fallback accessible name.

> **Maintenance note:** update this table whenever a component is added, renamed, or removed in `templates/components/`.

### Cards: a grid of teaser cards

Two independent ways to get a row of image + title + description + button cards, matching two
different sources:

**1. Entered by hand in EasyAdmin — several `card` blocks.** The `card` block kind carries its own
`vich_uploader`-managed image (`media_types: 'image/*'`, same mechanism as `article` — the media
belongs to that one block, no pairing with anything else) plus optional `url`/`target`/`buttonLabel`
fields. When a media and/or a `url` is set, `blocks/Card.html.twig` renders an image + button teaser
instead of the plain content box. There is no dedicated kind for **manually entered** cards: a
"collection of cards" editors fill in by hand is just several `card` blocks placed next to each other —
`<twig:c975LUi:Blocks:Blocks>` automatically wraps consecutive `card`-kind blocks in a `.cards` flex row
(see `templates/components/Blocks/Blocks.html.twig`), the same way several `article` blocks form a list
of articles. (For a grid pulled live from another bundle's own entities instead, see the `collection`
kind below.)

Both that adapter and the `collection` one wrap the tail of a card — its description and its button — in
a `.card-data`, and `sass/_cards.scss` pins that tail to the bottom of the card body. So a row of cards
carrying images of unequal heights and texts of unequal lengths still lines every button up on one line,
with nothing to set per card. It takes a four-rule chain, each link useless without the others: `.cards`
stretches the cards to a common height, `.card` is the column handing that height down, `.card-body`
takes what the header leaves (`flex: 1`), and `.card-data` sits at its bottom (`margin-top: auto`, its
1em of spacing written as padding since the margin is now the pinning). The column is put on the body
through `:has(> .card-data)` rather than on every body: a card whose body is free content stays in plain
block flow, where adjacent margins still collapse. `CardTailAlignmentTest` locks the four rules in the
compiled stylesheets.

**2. Any bundle calling `<twig:c975LUi:Card:Cards>` directly — no `Block` entity involved.** The
component doesn't know or care where `items` comes from; each entry must expose:

| Key | Required | Notes |
| --- | --- | --- |
| `id` | no | HTML `id` of the `.card` element |
| `title` | no | Card header |
| `description` | no | Plain text, shown under the image |
| `image` | no | Full URL or path resolvable by `asset()` |
| `url` | no | Link target for the image and the button |
| `target` | no | `''` (default, same window) or `_blank` |
| `buttonLabel` | no | Defaults to `url` when empty |

```twig
{# e.g. from BookBundle, mapping its own query result #}
<twig:c975LUi:Card:Cards items="{{ books|map(book => {
    id: 'book-' ~ book.slug,
    title: book.title,
    description: book.summary,
    image: book.coverUrl,
    url: path('book_show', {slug: book.slug}),
}) }}" />
```

### Collection: a live grid sourced from another bundle

The `collection` kind lets an editor drop a section on a page that always shows the latest N items
from **another bundle's own entities** (books, products, projects...) — unlike `card`, no item data is
entered on the block itself, only which source to pull from (`source`), how many to show (`limit`) and
the surrounding section heading/link. Each item is resolved live at render time and rendered through
`collection_item`, a `card`-based kind reserved for this use (never offered in the block picker - see
`pickable: false` in `config/services.yaml`), so it looks the same as a manually placed `card`.
Not cacheable (`cacheable: false` in `config/services.yaml`) — its content depends on another bundle's
own entities, which `BlockCacheInvalidationListener` has no way to invalidate on.

A bundle exposes its entities to this block by implementing `CollectionSourceProviderInterface`
(auto-discovered the same way as `BlockFixtureProviderInterface` — no tag needed):

```php
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem;

class BookCollectionSourceProvider implements CollectionSourceProviderInterface
{
    public function __construct(private BookRepository $books) {}

    public function getSources(): array
    {
        return [
            'book.collection.books' => [
                'label' => 'Books',
                'items' => function (?int $limit): iterable {
                    foreach ($this->books->findLatest($limit) as $book) {
                        yield new CollectionItem(
                            title: $book->getTitle(),
                            description: $book->getSummary(),
                            imageUrl: $book->getCoverUrl(),
                            url: $book->getUrl(),
                        );
                    }
                },
            ],
        ];
    }
}
```

`imageUrl` is an already-resolved URL string, not a `Media`/entity reference — each provider is
responsible for resolving its own image storage before handing it back. A source removed since a
`collection` block was configured (e.g. its owning bundle uninstalled) doesn't break the page it's
still referenced from, it just renders an empty grid.

`CollectionItem` also takes `buttonLabel` (defaults to the raw `url` when empty) and `buttonIcon` (a
`c975L\UiBundle\Image\Icon` component `src`, e.g. an icon path from `social_link_icon()`) — both flow
straight into the transient `collection_item` Block's own teaser button, so a collection item's
call-to-action reads the same as a manually placed `card`'s.

The `collection` block's own **Presentation** field (`variant`) switches every item's markup at once,
without an app-level template override: `''` (default) renders each item as a `card`, `'portfolio'`
reuses `portfolio_grid`'s own markup/CSS instead (see `CollectionItem.html.twig`).

#### Item detail pages

A source can optionally expose a 3rd key, `detail`, alongside `label`/`items`:

```php
'items' => function (?int $limit): iterable { /* ... */ },
'detail' => fn (string $slug): ?array => $this->books->findOneBySlug($slug)?->toDetailData(),
```

`callable(string $slug): ?array` — given an item's own slug, return a plain array of template variables
(by convention always including `title`, used for that URL's `<title>`), or `null` if the slug doesn't
resolve to anything (the caller falls through to a 404). This lets every item in the source get its own
detail URL — `/pages/{page}/{slug}` for a `Page` carrying this `collection` block — with **no Page/Block
row persisted per item**: the data is rebuilt from the source on every request. Pair it with the
`collection` block's own `detailPage` field (the slug of a **real, separate `Page`** whose own blocks
render as this item's detail view, a `collectionItem` Twig global carrying the current item's data to
whichever of them needs it) — see SiteBundle's README ("Item detail pages", under "Collection entries")
for the full recipe and `PageController::resolveCollectionDetail()` for the resolution logic itself.

To also make each item's own **title** a link to its detail page, `CollectionItem` needs the same slug
the `detail` callable expects:

```php
yield new CollectionItem(
    title: $book->getTitle(),
    // ...
    slug: $book->getSlug(),
);
```

`CollectionExtension::renderItems()` builds that link itself (`/pages/{currentPage}/{item->slug}`) once
both the block's `detailPage` and the item's `slug` are set — nothing to do on the template side. Either
one missing (no `detailPage` configured, or a source whose items carry no `slug`) just renders the title
as plain text, same as today.

### Video and audio blocks: driven by the uploaded file

The `video` and `audio` kinds carry no file path and no format field of their own: both are read back from the uploaded `Media` (its stored `mimeType`). `video` accepts `video/mp4,video/webm,video/ogg` plus an optional `image/*` used as the player's cover, told apart by mimetype in `blocks/Video.html.twig`; `audio` accepts `audio/mpeg,audio/ogg,audio/wav`. `VideoType` keeps only the player's own display fields — `options` (`autoplay`/`muted`/`loop`) plus the same `title`/`description`/`width`/`height`/`class` as `VideoIframeType`, and `AudioType` the same `title`/`description`/`class` (no `width`/`height`, an `<audio>` element has no such attributes). All three kinds render the same `<figure>` / `<h3 …-title>` / `<figcaption …-description>` structure, on a `video-`, `video-iframe-` or `audio-` class prefix sharing one set of sass rules (see `sass/_images.scss`).

> **Breaking:** blocks of these kinds saved before this change stored a raw `src`/`type` (and `poster`) in their data with no media attached, and render nothing now. Re-upload the file on each one — there is no automatic migration.

### Video:Iframe: consent-gated third-party embeds

`<twig:c975LUi:Video:Iframe>` (the `video_iframe` block) can rewrite a YouTube URL to
`youtube-nocookie.com` (opt-in per block, via its "Use no-cookie version" checkbox), and defers
creating the real `<iframe>` client-side until cookie consent is given — the block's own HTML
never changes with consent state, so it stays cacheable.

It has **no composer dependency on any consent-banner bundle**. Instead it reacts to an optional,
documented contract, checked at connect time:

- a `[data-controller~="cookie-consent"]` (or `cookieConsent`) element present somewhere on the page (if absent, it fails
  open and renders the iframe immediately — a site with no consent banner isn't blocked by this),
- a `window.CookieConsent` global exposing [`vanilla-cookieconsent`](https://cookieconsent.orestbida.com/)
  v3's API (`acceptedCategory('content')`, `acceptCategory('content')`),
- its `cc:onConsent`/`cc:onChange` DOM events, so the placeholder upgrades to the real iframe live,
  without a page reload, as soon as consent is given.

This bundle's own `<twig:c975LUi:Cookie:Consent />` (see [Cookie banner](#cookie-banner)) is a
ready-made provider of that contract — but any consuming app's own banner satisfying it works just
as well.

---

## PDF thumbnails

When a `.pdf` file is uploaded through VichUploader on **any entity** (no interface required), the bundle automatically generates a `.webp` thumbnail of the first page next to it (`document.pdf` → `document.webp` - the extension is replaced, not appended), via Ghostscript + Imagine/GD.

- **Requires Ghostscript** (`gs`) installed on the server. If missing, the thumbnail generation silently fails — the PDF upload itself is unaffected.
- **Requires `exec()`** to be enabled. On managed hosting where it's disabled (e.g. Infomaniak), thumbnail generation is skipped the same way — the PDF upload itself is unaffected.
- **Skipped for private files** — entities implementing `VichPrivateFileInterface` (e.g. a paid download in a shop) are not thumbnailed, since there's no public preview use case for them.
- **Thumbnail width** defaults to `400px`, or reuses `getImageWidth()` if the entity also implements `VichImageResizableInterface`.

No configuration needed — handled by `VichPdfThumbnailListener`, auto-registered like the rest of the bundle's services.

By default an uploaded PDF is stored under an auto-generated name (`block-{kind}-{id}-{uniqid}.pdf`). Filling in the **File name** field (`Media::$name`, shown for `application/pdf` uploads) overrides this: `UiMediaNamer` slugifies it into the stored filename instead (e.g. "Rapport annuel" → `rapport-annuel-xxx.pdf`). It's distinct from **Caption** (`Media::$label`, a display string), which isn't filesystem-safe.

---

## Satellite media entities

A satellite bundle needing its own Vich-uploaded media entity (e.g. ShopBundle's or CrowdfundingBundle's own `Media`) doesn't need to duplicate the usual id/position/name/size/file/updatedAt/user fields, nor relate to UiBundle's own `Media` entity: `Entity\Trait\VichMediaTrait` provides them as a plain trait, so each bundle keeps its own abstract class, its own `SINGLE_TABLE` inheritance and its own table, with no Doctrine relation - and therefore no composer dependency - between bundles that both need one.

Implement `Contract\VichMediaNamableInterface::getVichMediaPath(): string` on that entity (the trait doesn't do this for you, since the path depends on your own storage layout) to get two things for free:

- `UiMediaNamer` (Vich's naming strategy) already requires it for any entity going through it.
- `Listener\MediaFileRemoveListener` deletes the underlying file from `public/` whenever such an entity is removed - a generic `preRemove` Doctrine listener, auto-registered, that needs no per-entity listener of your own.

For a **private** download (e.g. a paid file in a shop) instead of a public one, also implement `Contract\VichPrivateFileInterface` (see [PDF thumbnails](#pdf-thumbnails) above) and use `Service\PrivateFileResponseFactory::createDownloadResponse(string $absoluteFilePath, string $downloadFilename): ?BinaryFileResponse` from your own controller to build the attachment response (`null` if the file is missing) - it only builds the response, access control (checking the current user actually purchased/owns the file) stays your controller's job.

---

## Site-wide media (favicon, logo, og-image)

A `Media` row isn't necessarily attached to a `Block` — it can instead hold one of a fixed set of site-wide graphics, identified by a `role`:

```php
use c975L\UiBundle\Entity\Media;

Media::ROLE_FAVICON;          // 'favicon'
Media::ROLE_APPLE_TOUCH_ICON; // 'apple-touch-icon'
Media::ROLE_OG_IMAGE;         // 'og-image'
Media::ROLE_LOGO;             // 'logo'
```

`role` is unique per value, so there is at most one `Media` for each. Create/replace one the same way as any other `Media` (e.g. from your own app's settings form or a fixture), setting `setRole(Media::ROLE_FAVICON)` — `UiMediaNamer` then stores it under a fixed, predictable filename at the root of `public/` instead of the usual per-block path.

`favicon` and `apple-touch-icon` are the two roles with a *fixed* spec (48×48 `.ico`, 114×114 `.png`, see `Media::FIXED_ICON_SPECS`): whatever is uploaded is converted to that exact size and format, and stored under the role's own filename. Both accept an **SVG** on top of the usual PNG/JPG/GIF/WEBP, `Service\SvgRasterizer` rendering it to a 512px PNG the pipeline then downscales — a vector source giving a visibly cleaner icon than a small raster one blown up.

That rasterizing goes through `ext-imagick`, a Composer **suggest** rather than a requirement. Without the extension the two roles simply keep accepting raster images only: `Validator\FixedIconFormat` refuses the SVG at upload time, listing the formats it does accept, rather than storing markup under an `.ico` name no browser can read. The same refusal covers an SVG ImageMagick can't render (its own internal renderer, used when the librsvg delegate isn't installed, chokes on a fair share of real-world markup) — so an icon is never silently broken, it is either converted or turned down.

### SVG text that isn't vectorized

An SVG served through an `<img>` is rendered as an isolated document: it reaches neither the page's `@font-face` rules nor its stylesheet. So an `<svg>` still drawing its text with a `<text>` element needs that font on the **visitor's own machine**, and falls back to whatever the renderer picks otherwise — the same story server-side, where `SvgRasterizer` flattens an icon role with the fonts the server happens to carry. Self-hosting the font on the site changes nothing; converting the text to paths (Inkscape: *Path > Object to Path*) is the fix.

It is the one defect an author cannot see, their own machine being the one that has the font, so `Service\SvgTextDetector` looks for it and `Listener\SvgTextWarningListener` flashes a warning on the upload itself — hooked on `vich_uploader.post_upload` rather than on a CRUD controller, so a site graphic, a block's media and a gallery photo are all covered at once. A **flash, not a validation error**: the file is perfectly storable, it just won't look the same to everyone. The detector decides on content and never on the name (an icon role's stored file already carries the role's `.ico`/`.png` while still holding the uploaded markup), names the families each `<text>` depends on, and is what the `svg-fonts` health check reuses to report the files already in place.

Retrieve it anywhere in Twig with the `site_media()` function, which returns `null` if none was uploaded yet:

```twig
{% set favicon = site_media('favicon') %}
{% if favicon %}
    <link rel="icon" href="{{ vich_uploader_asset(favicon) }}">
{% endif %}
```

---

## Site graphics

The favicon, Apple touch icon, logo, default Open Graph image and error-image pool are `Media` rows carrying a `role` (see [Site-wide media](#site-wide-media-favicon-logo-og-image) above for the roles themselves and how they are stored). `Controller\Management\SiteGraphicCrudController` is the screen that fills them, under *Management → Advanced → Site graphics*, gated by `site-role-editor`.

The index shows one button per graphic still missing: each opens the upload form with the role already picked and the choice frozen, so only the file is left to choose. `SiteGraphicAlertProvider` raises the same thing as a dashboard alert, linking to the same pre-filled form. The buttons disappear once the four singleton graphics exist — `error-image` is a pool, added through the plain "new" action.

`SiteGraphicExportProvider`/`SiteGraphicImportProvider` plug them into ConfigBundle's **Export sync (everything)** shortcut and **Import content** screen: a singleton role matches by its own role on import, while the repeatable `error-image` pool is replaced wholesale (no natural key of its own to match against). `SiteGraphicMediaUsageProvider` is what makes the Media library say "this one is the favicon".

`Form\OgImageType` embeds a single `Media` upload, for an entity of your own carrying a share image.

---

## Theme

The site's colors, fonts and light/dark mode are admin-editable config keys of the `theme` group, declared here because the `--c975l-*` custom properties they compile to are the ones this bundle's own CSS reads: `theme-color-primary`, `theme-color-secondary`, their two `-dark-mode` counterparts, `theme-color-background`, `theme-color-text`, `theme-font-family-title`/`-body`/`-accent` and `theme-mode` (`auto`/`light`/`dark`).

`Listener\ThemeVariablesCssListener` compiles them into `public/bundles/build/site-theme.css` on every change — a Doctrine listener on the `Config` entity, and a `CacheWarmerInterface` too, so a fresh file exists after a deploy even without an admin re-saving anything. The mapping is mechanical (`theme-color-primary` → `--c975l-color-primary`), so a new key needs no lookup table; a bare custom font name gets a generic fallback appended (`sans-serif`/`monospace`) in case the `@font-face` fails to load.

`Service\ThemeVariablesStylesheetProvider` contributes that file at **priority `0`**: after every bundle's compiled defaults (tagged 100) and before an app's own `assets/styles/themes/*.css` (auto-tagged at -100). That is what makes the admin's values win over the bundles and lose to a token the site took back.

`theme_variables_css()` returns the same compiled CSS as a string, for inlining where a `<link>` isn't possible — emails, chiefly.

---

## Cookie banner

```twig
<twig:c975LUi:Cookie:Consent />
```

Wraps [`vanilla-cookieconsent`](https://cookieconsent.orestbida.com/) v3 (MIT), served from this bundle (`public/js/cookieconsent.umd.js` + `public/css/cookieconsent.css`) rather than from a CDN: a third party must not receive the visitor's IP before any consent is given, and it keeps the CSP free of an external `script-src`/`style-src` host. Its Stimulus controller is registered lazily, so it only loads on a page that actually renders the component.

**The component carries its own guard**: it renders nothing unless `site-enable-cookie-consent` is on, so a layout includes it unconditionally. `url-cookies-policy` (optional) links the banner to your cookies page.

Deliberately binary — one non-essential category (`content`), two buttons (accept/reject), no preferences panel to build or maintain. That single category is what the [`video_iframe`](#video-blocks) block waits on before creating its real iframe.

---

## Media Library

A `Media` row can be attached in several ways depending on the consuming bundle: to a `Block`, directly to another entity (e.g. a Page's og-image), or as a site-wide `role`. `MediaCrudController` provides a single EasyAdmin gallery browsing every `Media` regardless of how it's attached, with a click-through to edit its metadata (alt, caption, credits, CSS classes...). Site-wide role graphics stay read-only there — they keep being managed wherever the consuming bundle handles roles (e.g. `SiteGraphicCrudController` in c975L/SiteBundle), and their thumbnail links straight to that screen rather than to the library's own edit form, for an admin holding the `site-role-editor` permission it gates itself with. Without it the thumbnail is no longer a link at all. Detail is disabled: every thumbnail opens a form, the only page rendering the file itself. Creating a new `Media` directly from this library (with no `Block` yet) is reserved to `ROLE_SUPER_ADMIN` — regular admins keep adding media the normal way, through a block's own form.

The gallery shows 20 thumbnails and offers **20 · 50 · 100** under its paginator, like every other back-office list: `Management\PaginatorPageSize` reads the size from a `pageSize` query parameter (validated against those three before it reaches a `LIMIT`) and `templates/management/paginator.html.twig` offers them as links, both wired for every CRUD at once in ConfigBundle's `DashboardController::configureCrud()` — EasyAdmin's own paginator only knows one fixed size. The sizes offered come from the `page_sizes()` Twig function (`Twig\PageSizeExtension`), so overriding the template alone can't offer one the service would refuse.

UiBundle does **not** register a menu entry for it: `c975l/config-bundle` (which owns the menu registration mechanism, see below) already depends on `c975l/ui-bundle`, so the reverse would be a circular dependency. A bundle that depends on both - e.g. c975L/SiteBundle - should add an entry pointing to `MediaCrudController::class` in its own `MenuProviderInterface` implementation.

`Media::$url`/`Media::$description` back the per-project link and text of the `portfolio_grid` kind (see `MediaUploadType`'s `portfolio_grid` context) - a project card's title reuses the existing `$label` field.

Attaching more than one `Media` to a `hero` block lays them out beside the text in one of two ways, picked with its "Media layout" field (`HeroType::$mediaLayout`). A single attached media keeps the plain static image whichever is set, and an unset value reads as `slideshow`, so every hero stored before the field existed renders exactly as before.

- `slideshow` (the default) cycles through them in a pure-CSS crossfade, 6s per slide, no JS, disabled under `prefers-reduced-motion` - see `.hero__media--slideshow`.
- `grid` shows all of them at once in three columns, as square tiles bordered by the section's own tone (`--section-border`) over an opaque plate (`--hero-media-grid-background`, white) rather than a card surface - a logo wall, a set of icons. A mark is drawn for one background, so the plate keeps its colors from mixing into the hero's; for the same reason the tiles carry no `:hover` - they are not links. Nothing animates, so nothing can collide - see `.hero__media--grid`.

Both live in `sass/_page-sections.scss`. `BlockType::HERO_MEDIA_MAX` caps a hero at 9 medias, for the slideshow's sake: its keyframes can't read the slide count, so each slide's turn is a `:nth-child` delay and each count both a `[data-count]` duration and its own `@keyframes`, holding a slide opaque for exactly its share of the cycle - all three generated from `$hero-slide-max`, and an image past that cap would silently collide with an earlier slide's timing. One cap covers both layouts rather than a validation branch reading `mediaLayout` from inside `BlockType`'s `PRE_SUBMIT` listener. The tiles carry no color filter of their own: a site whose icons are monochrome and sitting on a dark flat inverts them from its own `app.css`, which is where rules overriding a bundle's classes belong.

A `hero` block's "Show image as a full-width background" toggle (`HeroType::$hasBackgroundImage`) instead shows the first attached image full-bleed behind the centered text, dropping the side-by-side layout and slideshow - see `.hero--has-bg` in `sass/_page-sections.scss`. That backdrop is a real `<img class="hero__bg">` rather than a CSS `background-image`: a background needs a `style` attribute, which a CSP nonce never covers (nonces only ever apply to `<style>`/`<link>` *elements*).

A hero's typographic scale is retunable without touching its rules, through five custom properties each read with the bundle's own value as its fallback: `--hero-title-size` (`clamp(40px, 6vw, 66px)`), `--hero-title-letter-spacing` (`-0.01em`), `--hero-title-line-height` (`1.03`), `--hero-sub-size` (`19px`) and `--hero-sub-max-width` (`480px`). They are declared nowhere, so a site setting none of them renders exactly as before - a design wanting a bigger, tighter hero sets them in its own `theme.css` instead of restating `.hero__title`/`.hero__sub`.

A hero's two call-to-action buttons are both optional, the primary one as much as the secondary: a button is only rendered when it holds **both** its label and its url (a label alone would print a box linking to the current page, a url alone an empty clickable box), and the row itself disappears when neither is set, rather than leaving its margins behind under the title.

A `hero` block's "Heading level of the title" field (`HeroType::$titleLevel`) picks between `h1` and `h2`. Leave it on `h1` when the hero opens the page and carries its real title; switch it to `h2` when the page template already prints its own `<h1>` above the blocks, two `<h1>` on one page being announced by screen readers as two top-level headings.

### Headings and the `<section>` element

Every section-level kind whose title is optional (`section_cards`, `flex_columns`, `section_features`, `portfolio_grid`, `collection`, `text_section`) follows the same two rules, both of them what the W3C validator asks for:

- with an eyebrow but no title, the eyebrow *is* the section's heading and renders as `<h2 class="section-eyebrow">` instead of `<p>` — it keeps its exact eyebrow look, and the slots' own `<h3>` no longer skip a level down from the page's `<h1>`;
- with neither, the wrapper renders as a `<div>` instead of a headingless `<section>` (same rule already applied to `feature_bar` and the `form` block).

`text_section` derives its in-page anchor from that same heading: from the title, or from the eyebrow when there is no title (`TextSectionType`), so moving a heading from one field to the other doesn't silently drop the anchor other pages may link to.

`cta_band`, `expertise_banner` and `process_steps` are not concerned: their title is a required form field, so their `<h2>` is always there.

A `card` block's own **Level** field stays the editor's call — picking `h3` for a card sitting under a section that has no heading at all still skips a level.

### Image dimensions and layout shift

Every upload records the stored file's own pixel size in `Media::$width`/`$height` (see `VichImageResizeListener`, reading through `ImageDimensionsReader`), so the templates rendering a media can emit real `width`/`height` attributes on the `<img>`.

This is what removes the layout shift Lighthouse reports as CLS, and the `img-responsive` class can't do it: `max-width: 100%; height: auto` keeps an image fluid, but tells the browser nothing about its proportions *before* the file is downloaded — so the box collapses to nothing and the rest of the page jumps once the image arrives. The two are meant to be combined, not chosen between: the attributes let the browser derive an `aspect-ratio` and reserve the right rectangle, then `height: auto` takes over for the actual rendering.

That `height` is mandatory, not decorative. HTML `width`/`height` attributes map to CSS *presentational hints* on the `width`/`height` properties, and a hint only loses to author CSS for a property that CSS actually declares — so a rule shaping an image without ever declaring `height` leaves `height="800"` in charge, and any `aspect-ratio` it sets is ignored once both dimensions are definite. Any sass rule targeting the `img` element must therefore settle its own height, which `tests/Assets/ImageAspectRatioHeightTest` locks for the rules that set an `aspect-ratio`.

Both fields stay editable in the media form, pre-filled with the detected values. Raster formats are measured with `getimagesize()`; an SVG is read from its `width`/`height` attributes, falling back to its `viewBox` when those are absent or expressed in relative units (`100%`, `em`). A media with nothing measurable (PDF, audio, video) simply keeps empty fields.

Because they stay editable, both forms exposing them (`Form\MediaUploadType` for a Block's uploads, `Controller\Management\MediaCrudController` for the media library) run **`Service\MediaDimensionsFiller`** on submit: an entry saved with *both* inputs blank means "unknown" and gets the detected size back, rather than erasing it. A single filled field is enough to leave the media alone — a width typed without a height is a deliberate pair.

Being admin-typed, those fields also accept a CSS length (`50%`, `100px`, `auto`), which an HTML `width`/`height` attribute silently discards. **`Media::getIntrinsicWidth()`/`getIntrinsicHeight()`** return the value only when it's a bare pixel count, so a template can tell a known intrinsic size from a CSS length.

Medias uploaded before this existed keep empty fields until you backfill them:

```bash
php bin/console c975l:ui:media-dimensions
```

It only fills a row that has none, so a value typed by hand survives, and it can be re-run at will.

The reserved rectangle fixes the shift, not the delay: the one above-the-fold image likely to be the LCP element should also opt out of lazy-loading, with `priority` on `<twig:c975LUi:Image:Image>`, `<twig:c975LUi:Image:Icon>` or `<twig:c975LUi:Image:Link>` — it renders `loading="eager" fetchpriority="high"` instead of the default `loading="lazy"`. Use it on that one image only; marking everything as priority defeats the point.

### Declaring where a Media is used

UiBundle only knows about `Media`/`Block`; it has no visibility into which entity of a consuming bundle owns that Block, or holds a direct reference to a Media (like a Page's og-image). Each bundle that knows this can contribute that information:

1. Implement `MediaUsageProviderInterface` in your bundle - no tag needed, it's auto-discovered like `BundleWhatsNewProviderInterface`.
2. Return, for a given batch of already-loaded `Media` rows, the places they're used.

```php
use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Media;

class MyMediaUsageProvider implements MediaUsageProviderInterface
{
    public function getUsages(array $medias): array
    {
        // [mediaId => [['label' => string, 'url' => ?string], ...], ...]
        return [...];
    }
}
```

`url` can be `null` for a purely descriptive entry (no admin page to link to). Every registered provider's results are merged and shown in the "Used in" field of the Media Library's edit page.

---

## Automatic CSS injection

UiBundle provides a mechanism for bundles to declare their stylesheets automatically, without requiring manual `@import` or `<link>` additions in each application.

### How CSS injection works

1. Each bundle that provides CSS implements `BundleStylesheetProviderInterface` and registers itself with the `ui.stylesheet` service tag, saying through its `priority` where its sheet belongs in the cascade. An app implements the same interface and tags nothing — see [From the app itself](#from-the-app-itself).
2. UiBundle collects all tagged providers at compile time (ordered by `priority`, highest first).
3. The `bundle_stylesheets()` Twig function returns the resolved list of URLs, ready for use in a layout template.

In `kernel.debug`, `bundle_stylesheets()` returns each bundle's stylesheet separately, for instant reload on every CSS edit. Outside debug (prod), it instead returns a single URL pointing to `public/bundles/build/site.css`, a concatenation of every registered local stylesheet built by `StylesheetCacheWarmer` (auto-registered, runs on `bin/console cache:warmup` / on first request after a cache clear - like any optional Symfony cache warmer). CDN stylesheets (absolute URLs) are excluded from that file and keep being linked on their own in both cases.

### Where the form layer lives

`sass/_forms.scss` styles the bare form controls (`input`, `select`, `textarea`, `label`, the submit button, radio/checkbox rows) and belongs here rather than in SiteBundle: eight c975L bundles require `c975l/ui-bundle` and none requires `c975l/site-bundle`, so UiBundle is the only floor a form rendered by ShopBundle, BookBundle or PaymentBundle can count on — and UiBundle renders forms of its own (the `Form`/`FormField` builder, `components/Form/Form.html.twig`, the block and captcha form themes).

Its `--input-*`, `--form-*`, `--label-*` and `--required-color` tokens are still declared by SiteBundle's `sass/_variables.scss`, the admin-editable theme contract — see [Token defaults](#token-defaults) for how they resolve without it. Override the width every form is laid out on with `--form-width` (defaults to `min(70vw, 1000px)`).

A field turns green or red as soon as it has been judged, replacing the blue focus ring. Two sources, one look: the browser's own constraint validation (`required`, `pattern`, `type="email"`, `minlength`…) through `:user-valid` / `:user-invalid`, and the `.success` / `.error` classes the `password` controller writes for the checks HTML cannot express. `:user-*` rather than `:valid` / `:invalid` on purpose — the latter match from page load and would paint an untouched form red before the visitor typed anything. Green is restricted to fields actually declaring a constraint: `:user-valid` matches any touched field, and turning a free-text input green says nothing. Retune both through `--input-valid-border-color` / `--input-invalid-border-color` (and their `-shadow-` pair), which default to the site's success/danger button colors.

### Token defaults

`sass/_tokens.scss` declares a default for every custom property this bundle reads but does not own — SiteBundle's whole theme contract. It exists because an unresolved `var()` with no fallback makes its entire declaration invalid, so a missing token is a card with *no* border rather than a slightly off one, and eight c975L bundles require `c975l/ui-bundle` while none requires `c975l/site-bundle`.

They sit in `@layer ui-defaults`, and that layer is the point: a layered rule always loses to an unlayered one whatever the source order, so SiteBundle's `:root`, the admin's compiled `bundles/build/site-theme.css` and a site's own `theme.css` all win without anything having to be sequenced — the two bundles' stylesheet providers share `priority: 100`, so their relative order is not something either can rely on. Nothing else in this bundle is layered, and `TokenDefaultsTest` fails if a token is read without a default, if a default is declared for a token nothing reads, or if a second layer appears.

Values are light-mode only. Dark mode is SiteBundle's (`sass/_theme-dark.scss`); with that bundle absent there is no dark theme to follow.

`scaffold/assets/styles/themes/ui.css` is the catalogue of those tokens, installed by `c975l:scaffold:install`: every one of them commented out at its default, so the lines a site leaves active read as exactly what its design decides. One such file per bundle, each holding what that bundle reads — this one travels with UiBundle, so a site running ShopBundle or BookBundle without SiteBundle still gets its whole retunable surface. Colors and fonts are deliberately absent, being admin-editable, and so are the per-variant `--section-*` tokens. `ScaffoldThemeTest` fails if a declared token is missing from it, if a value shown there is no longer the one in force, or if a line ships uncommented.

### The reading measure

`--reading-max-width` is the column body copy is laid out on — SiteBundle declares it, this bundle reads it with `min(75ch, 90vw)` as its fallback. Every block that *is* body copy shares it rather than carrying a length of its own: `.slider`, `.slider-single`, `.image-compare` and `.readmore`. They each used to hardcode 800px, which sat edge to edge on a viewport of exactly that width and drifted apart from the text they sit with. A site retuning its measure now moves all of them at once.

Sections are wider than body copy and keep their own measures, but through tokens too rather than bare lengths: `--section-wrap-max-width`, `--section-head-max-width`, `--hero-text-max-width`, `--hero-media-max-width`, `--cta-band-text-max-width` and `--alert-max-width`. `ReadingMeasureTest` fails if a column-wide rule goes back to a bare length, or if a section measure is written without a token.

### Adding CSS from your bundle

**Create a provider class** in your bundle:

```php
use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

class StylesheetProvider implements BundleStylesheetProviderInterface
{
    public function getStylesheets(): array
    {
        return [
            'bundles/mybundle/css/styles.min.css', // local public asset
            'assets/styles/themes/mybundle.css', // the app's own sheet, served by AssetMapper
            'https://cdn.example.com/lib/styles.min.css', // CDN URL, passed through as-is
        ];
    }
}
```

**Register it with the tag** in `config/services.yaml`:

```yaml
services:
    MyBundle\Service\StylesheetProvider:
        tags:
            - { name: 'ui.stylesheet', priority: 10 }
```

A path starting with `assets/` is one of the *app's* own sheets — its theme files — rather than a bundle's compiled one under `public/`. It is read from the project root instead, being an AssetMapper source never copied there, and `bundle_stylesheets()` drops that prefix before asking AssetMapper for its URL, the `assets/` directory being its root. Registering them is what folds a site's theme into the single `site.css` the bundles already share: AssetMapper never merges CSS, so a site splitting its theme one file per bundle would otherwise pay one request each.

The `priority` attribute is optional (default `0`). Higher priority providers are injected first — use a high value (e.g. `100`) for reset/base styles that must load before others.

### From the app itself

A site registers its own theme files the same way, minus the tag: `config/services.yaml` belongs to the app, and the `c975l:scaffold:install` command that writes the theme files may not edit it. So a service merely *implementing* `BundleStylesheetProviderInterface` is tagged at compile time, below every bundle's priority range — which is exactly where a site's theme has to load, after every sheet it means to retune.

```php
namespace App\Service;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

class ThemeStylesheetProvider implements BundleStylesheetProviderInterface
{
    public function getStylesheets(): array
    {
        return [
            'assets/styles/themes/ui.css',
            'assets/styles/themes/site.css',
        ];
    }
}
```

Nothing to add to `config/services.yaml` — autowiring registers the class, and the compiler pass does the rest. Do **not** copy the tagged snippet above into an app: any priority a bundle also uses puts the site's theme ahead of that bundle's sheet, which then overrides the theme rather than the other way round.

### Using it in a layout template

Call `bundle_stylesheets()` in the `stylesheets` block of your layout:

```twig
{% block stylesheets %}
    {% for stylesheet in bundle_stylesheets() %}
        <link rel="stylesheet" href="{{ stylesheet }}">
    {% endfor %}
{% endblock %}
```

Local paths are resolved to absolute versioned URLs via Symfony's asset package. CDN URLs (starting with `http`) are returned as-is.

---

## Automatic CSS injection for EasyAdmin management pages

Same idea as above, but for CSS that should only load on the EasyAdmin dashboard (e.g. `/management`), not on the public site. Keep using plain `style="..."` attributes for one-off, low-value cases (a single `margin: 0` isn't worth an extra HTTP request) — reach for this mechanism when a management template accumulates real, reusable CSS (see `templates/management/media_index.html.twig` for an example).

### How it works

1. Each bundle that provides management-only CSS implements `BundleStylesheetManagementProviderInterface` and registers itself with the `ui.management_stylesheet` service tag.
2. UiBundle collects all tagged providers at compile time (ordered by `priority`, highest first) into `StylesheetManagementRegistry`.
3. The EasyAdmin `DashboardController::configureAssets()` method (in the app or in the bundle that owns the dashboard, e.g. `c975l/config-bundle`) injects `StylesheetManagementRegistry` and calls `addCssFile()` for each entry.

### Adding management CSS from your bundle

**Create a provider class** in your bundle:

```php
use c975L\UiBundle\Contract\BundleStylesheetManagementProviderInterface;

class StylesheetProvider implements BundleStylesheetManagementProviderInterface
{
    public function getManagementStylesheets(): array
    {
        return [
            'bundles/mybundle/css/management.min.css',
        ];
    }
}
```

**Register it with the tag** in `config/services.yaml`:

```yaml
services:
    MyBundle\Service\StylesheetProvider:
        tags:
            - { name: 'ui.management_stylesheet', priority: 10 }
```

The `priority` attribute is optional (default `0`). Higher priority providers are injected first — use a high value (e.g. `100`) for reset/base styles that must load before others.

### Consuming it in the dashboard controller

```php
use c975L\UiBundle\Registry\StylesheetManagementRegistry;

public function __construct(
    private readonly StylesheetManagementRegistry $stylesheetManagementRegistry,
) {}

public function configureAssets(): Assets
{
    $assets = Assets::new();

    foreach ($this->stylesheetManagementRegistry->all() as $stylesheet) {
        $assets->addCssFile($stylesheet);
    }

    return $assets;
}
```

Unlike the JS admin mechanism (`BundleScriptAdminProviderInterface`), no AssetMapper/importmap entry is needed — `addCssFile()` resolves plain public paths via Symfony's asset package, same as `getStylesheets()` above.

---

## Shared building blocks for satellite bundles

A set of small, dependency-free helpers every c975L bundle attaching blocks or uploading files needs. They live here rather than in SiteBundle, where most of them started as traits: a trait shared across packages is only ever analysed against the callers living in the same package, so a bundle copying it got no help from static analysis - and four satellite bundles (shop, book, gallery, crowdfunding) needed them without needing SiteBundle at all.

| Helper | Role |
| --- | --- |
| `Service\UniqueSlug::build($slugger, $base, $collides)` | Normalizes a raw slug and appends `-2`, `-3`… until `$collides()` reports the candidate free. The scope uniqueness is checked against stays the caller's business (site-wide for a page, per-group for a collection item); only the suffixing is fixed here |
| `Service\BlockFocusUrl::build($adminUrlGenerator, $crudFqcn, $entityId, $block)` | The EasyAdmin edit URL of a block's owner, optionally jumping straight to that block's own row (`focusBlock`) |
| `Service\BlockMoveRowAttrBuilder::build($ownerType, $ownerId)` | The `row_attr` array `ea-sortable.js` reads to drag a saved block into a container - URL, CSRF token and failure label included. A service, not a trait: no caller has to know the route id. Returns `[]` for an unsaved entity, so the sortable simply doesn't arm itself |
| `Service\BuildFileWriter::write($projectDir, $filename, $contents)` | The one way a listener drops a generated stylesheet into `public/bundles/build/`. Written to a temp file then `rename()`d, so a request reading it mid-rewrite never gets half a stylesheet |
| `Form\VichImageOptions::default($maxSize, $required)` | The five Vich image-upload options (`allow_delete`, `download_uri`, `asset_helper`, the `File` size constraint…), for both an EasyAdmin `setFormTypeOptions()` and a plain `FormBuilder::add()` |
| `Listener\AbstractBlockCacheInvalidationListener` | The Doctrine lifecycle wiring of a listener reacting to one block kind changing - `postPersist`/`postUpdate`/`preRemove` all delegate to the subclass's `invalidate()`, which only has to say which kind it filters on and which cache tag it drops |

### Exporting and importing blocks

**`Management\BlockDataExporter`**/**`Management\BlockDataImporter`** are the shared Block/Media serialization behind every content export carrying a block collection (SiteBundle's `Page`, its `Menu`…), so the recursive walk through a container kind's slots is written once rather than per entity. A block nested in a `flex_columns` round-trips like a top-level one, medias and files included.

A content export carries the blocks alone, never the `Form`/`EmailTemplate` a `form`-kind block points at. Implement **`Contract\FormBlockDependencyProviderInterface::ensureFormBlockDependenciesExist(array $blockData)`** (auto-discovered, no tag needed - see `FormBlockDependencyProviderPass`) to seed yours on the way in; the importer asks every registered provider in turn, and a bundle owning none of the imported form names simply does nothing.

### Forcing a download

**`Controller\DownloadController`** (route `download_file`, `/download/{file}`) adds a `Content-Disposition: attachment` on top of a file the web server already serves from `public/` - what a "download this PDF" link needs. It is deliberately **not** merged with `Service\PrivateFileResponseFactory`, which serves the digital items bought through ShopBundle/CrowdfundingBundle from outside `public/` and keeps its own access checks.

---

## Checking a page's layout

A component centered by `margin: … auto`, or laid out past its measure by a negative one, loses that layout the moment a stronger rule writes the `margin` shorthand over it — with nothing in its own sass changed to show for it. That's how the slider ended up hugging the left edge in v1.12.0, and the colored flats lost their full-bleed breakout in v1.13.0. Two mechanisms cover it, one reading the stylesheet, one reading a rendered page.

### Off the stylesheet, in the test suite

`Testing\StylesheetCascade` reads compiled stylesheets the way the cascade does — in load order, with each rule's specificity — so a test can answer "does this rule beat that one on the same element" without a browser. `Testing\ComponentCenteringAnalyzer` uses it to find the components a sheet centers, the ones it breaks out of their measure, and the rules strong enough to write either away. `ComponentCenteringTest` runs the pair over this bundle's own sheet.

They live in `src/` rather than `tests/` on purpose: a bundle's `tests/` is autoload-dev and never reaches the bundles depending on it, and a stylesheet is only meaningful next to the ones loaded with it. So your own bundle can run the same engine over its sheet **plus** this one's, which is where the cross-bundle collisions are:

```php
use c975L\UiBundle\Testing\ComponentCenteringAnalyzer;
use c975L\UiBundle\Testing\StylesheetCascade;

$analyzer = new ComponentCenteringAnalyzer(StylesheetCascade::fromFiles(
    $uiBundleDir . '/public/css/styles.css',   // load order matters: source order decides between two rules of equal specificity
    $myBundleDir . '/public/css/styles.css',
));

foreach ($analyzer->analyse(ComponentCenteringAnalyzer::tagsByClass($myBundleDir . '/templates/components'))['violations'] as $violation) {
    self::fail(ComponentCenteringAnalyzer::describe($violation));
}
```

They're excluded from the service container (see the `Testing/` entry in `config/services.yaml`) — test utilities that ship, in the same spirit as Symfony's own `Test` namespaces.

### Off a rendered page, by hand

Some defects only exist once the page is laid out: a block wider than the viewport, a centering that computes to `0px`, an image blown up past the window. `c975l:ui:layout-audit` measures them in a headless Chrome:

```bash
php bin/console c975l:ui:layout-audit https://example.com/ https://example.com/contact
php bin/console c975l:ui:layout-audit http://127.0.0.1:8000/ --width=390 --width=1280 --strict
```

Run it from the **consuming site's** console, and install its dependency there rather than here — hence `chrome-php/chrome` being a `suggest` of this bundle: `composer require --dev chrome-php/chrome`. Without it the command exits cleanly with a warning, so a site that never installed it is never broken by the command existing.

It reports without failing unless `--strict` is passed — a headless browser is never deterministic enough to gate a push on, and a check that fails at random is a check that ends up disabled. A page it could not measure at all is reported too, rather than counted as a clean one.

Deliberately not a `HealthCheckProviderInterface`: those run from cron on the managed server, which has no browser and no way to install one.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/CoreBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/CoreBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!
