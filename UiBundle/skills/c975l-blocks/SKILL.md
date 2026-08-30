---
name: c975l-blocks
description: "Use this skill when working with page blocks in a Symfony application built on the c975L ecosystem — attaching a block collection to an entity, registering a custom block kind, containers and their slots, contexts, anchors, the render cache, the edit overlay, and the legal models. Covers what makes a kind cacheable, why a kind is a service tag rather than a class, and how blocks are exported. Triggers on: HasBlocksInterface, HasBlocksTrait, BlockRemovalListener, ui.block tag, render_block, BlockRegistry, getContexts, pickable, cacheable, contexts, block_group, flex_columns, anchor, hidden, Block::$hidden, isHidden, blockHide, set a block aside, hide a block, BlockCacheInvalidationListener, BlockCacheTagProviderInterface, BlockOwnerResolverInterface, BlockEditUrlProviderInterface, contact_details, ContactSnippetBuilder, SameAsProviderInterface, sameAs, legal_model, c975l:ui:block:create, TrashableInterface, TrashableTrait, isDeleted, trash, soft delete, restore, Rating, RatingService, RatingRepository, deleteForOwners, ui_rating, ui_ratings, ui-rating-icon, ui-rating-scale, ui_rating_vote, compact, aggregate, rating-vote--compact, RatingSnippetBuilder, AggregateRating, Review, ReviewService, ReviewRepository, ReviewStatus, ReviewCollectionSourceProvider, ReviewReplyPublisherInterface, ReviewReplyRegistry, ReviewVerifierInterface, ReviewVerifierRegistry, verified, ui_reviews, ui_reviews_enabled, ui_reviews_section, ui_review_url, ui-enable-reviews, ReviewShortcutController, ReviewTokenSigner, ReviewNotifier, ReviewAlertProvider, ui_review_new, moderation, avis, site-has-accounts, Favorite, FavoriteService, FavoriteRepository, FavoriteItemProviderInterface, FavoriteItemRegistry, ui_favorite_toggle, ui_favorite_list, wishlist, ui_can_hold_flash, label.rating_throttled, label.favorite_throttled, favorite-status, block-picker, ui-block-picker-trigger, ui-block-picker-on, ui-block-thumb, data-kind-row, Blocks:Thumb, block-thumbs."
---

# c975L UiBundle — blocks

> A page is a sorted collection of blocks composed in the back office. Any entity can own one, and any bundle can add a kind, without either knowing about the other.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi` · **Translation domain:** `ui`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Block.php`, `src/Contract/HasBlocksInterface.php`, `src/Entity/Trait/HasBlocksTrait.php`, `src/Contract/TrashableInterface.php`, `src/Entity/Trait/TrashableTrait.php`, `src/Registry/BlockRegistry.php`, `src/Listener/`, `src/Form/Block/`, `src/Twig/`, `src/Management/BlockDataExporter.php`, `src/Management/BlockDataImporter.php`, `src/Entity/Rating.php`, `src/Service/RatingService.php`, `src/Repository/RatingRepository.php`, `src/Controller/RatingController.php`, `src/Service/RatingSnippetBuilder.php`, `src/Entity/Review.php`, `src/Enum/ReviewStatus.php`, `src/Service/ReviewService.php`, `src/Repository/ReviewRepository.php`, `src/Controller/ReviewController.php`, `src/Controller/Management/ReviewCrudController.php`, `src/Form/ReviewType.php`, `src/Service/ReviewCollectionSourceProvider.php`, `src/Contract/ReviewReplyPublisherInterface.php`, `src/Registry/ReviewReplyRegistry.php`, `src/Contract/ReviewVerifierInterface.php`, `src/Registry/ReviewVerifierRegistry.php`, `src/Service/ReviewTokenSigner.php`, `src/Service/ReviewNotifier.php`, `src/Management/ReviewAlertProvider.php`, `templates/review/`, `templates/collection/ReviewItem.html.twig`, `src/Entity/Favorite.php`, `src/Service/FavoriteService.php`, `src/Repository/FavoriteRepository.php`, `src/Controller/FavoriteController.php`, `src/Contract/FavoriteItemProviderInterface.php`, `src/Registry/FavoriteItemRegistry.php`, `templates/blocks/`, `templates/components/Blocks/`, `assets/js/block-picker.js`, `sass/_block-thumbs.scss`, `config/services.yaml`

**Related skills:** `c975l-media`, `c975l-forms-emails`, `c975l-ui-assets` in this same bundle, and `c975l-config`, `c975l-management` in ConfigBundle beside it.

## Giving an entity blocks

**This is the highest-value thing a satellite bundle can do**, and it is three files:

```php
class Product implements HasBlocksInterface
{
    use HasBlocksTrait;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'shop_product_block')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;
}
```

```twig
<twig:c975LUi:Blocks:Blocks blocks="{{ product.blocks }}"/>
```

plus a `BlockOwnerResolverInterface` (`supports($ownerType)` / `find($ownerType, $ownerId)`) so a
block's edit screen can find its way back to its owner.

- **`ManyToMany`, never `OneToMany`** — `Block` has no foreign key back to any owner, which is what
  keeps this bundle out of your domain.
- **Name the join table explicitly.**
- **Never add `orphanRemoval`**: `removeBlock()` queues the block and `BlockRemovalListener` removes it
  on `preFlush`.
- Then `doctrine:migrations:diff` and `migrate`.

From there the entity's page is composed in the back office with every registered kind — hero, text
section, image, slider, cards, cta band — **with no template of its own to write**.

## Deleting an entity in two steps

An entity carrying `TrashableInterface` and `TrashableTrait` is never lost in one click: its
back-office **delete** writes a flag, the row and its files staying where they are until a second,
deliberate deletion. Unlike `HasBlocksTrait` above, the trait carries its own `isDeleted` column
mapping — a relation's mapping differs at every use, a boolean column's never does — so generate a
migration after adding it.

The flag is all the bundle gives; the two steps are yours, and always the same three moves:

- **`deleteEntity()`** sets the flag and flushes instead of calling `remove()`. That is what spares the
  cascades and the file listeners, which only ever run from a real removal.
- **The read paths filter it out, in the repository, never at each caller** — one caller forgetting is
  what puts a trashed entity back on the site. Leave the by-slug lookup unfiltered, so the front office
  can answer **410 Gone** from the row itself.
- **The index switches** on a `?trash=1` query parameter (`createIndexQueryBuilder()`), where a restore
  and a permanent delete appear. **Hold the permanent one at a higher role**: it is the only
  irreversible one.

## Registering a kind

A kind is a **service tag**, not a class to extend:

```yaml
ui.block.booking:
    class: stdClass
    tags:
        - name: ui.block
          kind: booking
          label: label.block_booking
          description: label.block_booking_description
          translation_domain: my_bundle
          category: label.category_booking
          form: App\Form\Block\BookingType
          template: '@App/blocks/booking.html.twig'
          pickable: true
          cacheable: true
```

`bin/console c975l:ui:block:create` scaffolds the form type, the template and the test in a consuming
app. The block's own data is JSON in `Block::$data` — **no column, no migration, ever.**

- **`pickable: false`** for a singleton managed from its own dashboard entry and rendered through
  `BlockRepository::findOneByKind()`, so editors cannot create separately-filled copies per page.
- **`cacheable`** — see below. Declare both explicitly; neither has a useful default.
- **`contexts`** restricts a kind to named contexts. A few are **exclusive** (the navbar's, a
  `flex_columns` slot's): there the rule is reversed and only a kind that opted in is offered.
  `BlockRegistry::getContexts()` reads that list back, a non-empty one telling a kind reachable only
  inside a parent from one offered everywhere.
- `media_types`, `media_required`, `media_multi_upload` drive the media collection, `media_types`
  being enforced on both the input's `accept` and a server-side `File` constraint.
- The owning bundle is derived from the template's Twig namespace — no attribute to fill.

**Un-registering a kind is safe**: a `Block` row outlives its tag, and `render_block()` skips an
unknown kind rather than throwing, so uninstalling a bundle blanks its blocks out of the pages instead
of taking them down.

## Choosing a kind in the back office

`assets/js/block-picker.js` puts a visual palette in front of each row's kind `<select>`: silhouettes
and labels grouped by the categories the select already carries, searched over the label, the
description and the slug, opened as a full-height sheet on a phone and as a centred dialog above it.
The select is never removed, only hidden by CSS (`.ui-block-picker-on`), so every kind-change rule of
`Form\BlockType` still reads a posted `kind` and a browser without JavaScript keeps the plain field.

**A registered kind needs nothing to appear there.** `BlockType` writes `data-kind-row` on the row and
`data-label`/`data-description` on each `<option>` (`choice_attr`, read off `BlockRegistry`), which is
all the palette reads — so a kind's `label` and `description` are what an editor sees on its tile.

The silhouette is markup plus CSS, no image: five `<b>` parts arranged per kind in
`sass/_block-thumbs.scss`, a kind with no rule of its own still drawing the generic one. A page
listing kinds **outside** `/management` — a site's public showcase — draws the same tile with
`<twig:c975LUi:Blocks:Thumb kind="banner_title"/>` and loads
`bundles/c975lui/css/block-thumbs.min.css` from its own `BundleStylesheetProviderInterface`; the
back-office gets the rules through `sass/management.scss` instead.

## The render cache

Each block's rendered HTML is cached with an infinite TTL, keyed by block id and locale, and
invalidated by a Doctrine listener watching `Block` **and** `Media` — a swapped image does not touch
the parent block's own fields. `bin/console cache:clear` invalidates everything, which is how a
template-only release is picked up.

**Set `cacheable: false` whenever the output is not a pure function of (block id, data, locale)**:

- it embeds a per-request form (a cached CSRF token would be served to every visitor);
- it reads another block's data (a pointer kind rendering a site-wide singleton);
- it queries entities the invalidation listener does not watch.

**When in doubt, `cacheable: false`** — the cost is one avoidable render, not a correctness bug. To
keep a kind cacheable while reading outside data, implement `BlockCacheTagProviderInterface` and
invalidate your own tag where that data changes.

## Anchors, containers, edit overlay

Any page-section kind can carry an anchor, which is what makes it a menu target; the whole page tree
is walked, nested sections included. Container kinds hold other blocks in slots, and a saved block is
dragged from one collection to another.

`BlockEditUrlProviderInterface` returns an edit url per block id so an editor gets the hover **Edit**
button on the rendered page — call `Service\LegalModelEditUrl::build()` first in your implementation,
a `legal_model` block being edited on its own screen. `BlockLocationProviderInterface` tells the
screens listing one kind site-wide where each block actually lives.

## Setting a block aside

`Block::$hidden` keeps a block on the page and renders it nowhere: its fields, its medias, its slots and
its place in the order are untouched, which is what lets a page be seen without a block instead of the
block being deleted and built back afterwards. The eye button of each row's toolbar toggles it, driving
the row's own hidden checkbox (`Form\BlockType`, never shown) so the state is stored by the same save as
every other field.

`BlockExtension::renderBlock()` is the single gate — a hidden block returns an empty string, wrappers
included, whether it is a page's own block or a slot of a container — and the check sits **before** the
render cache, so toggling the flag changes the page with no entry to invalidate. A template laying its
blocks out in cells of its own has to drop them earlier still: `Blocks.html.twig` filters them before its
card grouping counts kinds, and `Section/FlexColumns`, `Section/Cards` and `Video/Grid` before they count
their slots, a hidden one otherwise holding an empty cell — or a whole row — open. The flag travels
through `BlockDataExporter`/`BlockDataImporter`, an archive with no `hidden` key landing visible.

## The contact graph

The `contact_details` kind has two outputs off the same fields: the panel a visitor reads, and the
schema.org JSON-LD graph `ContactSnippetBuilder` assembles, every field optional and an empty one
dropped rather than published blank.

A bundle holding the urls of the profiles naming that same business elsewhere — a Google listing, a
social account — implements `SameAsProviderInterface` and they reach that graph's `sameAs`, the
property tying the site and those profiles into one entity. Same auto-discovery as everything above,
no tag needed; the registry is read at render time, so urls kept in the database are current, and it
de-duplicates across providers. **Do not add a field to the block for them** — the bundle owning the
profiles is the only one that knows their urls.

## Legal models

The legal notice, privacy policy, terms of sales and use, cookies and copyright are **this bundle's**:
the `legal_model` block renders them and the **Legal models** screen customizes them section by
section. A site running a shop with no page management needs them just as much. **Do not write legal
text into a template**, and do not duplicate the models in a satellite bundle.

A model states only the processing the site actually does: `site-has-accounts` (bool, `true` by
default) drops the account, password and login-identifier passages of the privacy policy on a site
holding no accounts, a text describing processing that does not exist being worse than a short one.
A setting rather than a check on a route or a form — the login exists everywhere for the
administrator, and accounts outlive a closed registration.

## Visitor ratings

Anything at all is rated by a visitor, blocks or not — the rated thing is **named**, never related:
`Entity\Rating` stores an `ownerType`/`ownerId` pair, the same vocabulary `BlockOwnerResolverInterface`
round-trips, so no bundle maps a collection it never reads.

```twig
<twig:c975LUi:Rating:Rating ownerType="book" ownerId="{{ book.id }}"/>
```

`ui_rating(ownerType, ownerId)` returns `{average, count, scale, icon}`, and
`ui_ratings(ownerType, ids)` one tally per id **in a single query** — what a listing needs to avoid an
N+1.

On a listing, two more props turn the widget into what a catalog card has room for:

```twig
{% set ratings = ui_ratings('book', books|map(b => b.id)) %}
<twig:c975LUi:Rating:Rating ownerType="book" ownerId="{{ book.id }}" compact="true" :aggregate="ratings[book.id]"/>
```

- **`compact`** prints the score and nothing else — no "37 avis", and nothing at all before the first
  vote, the empty row of icons saying it already. Except on a **scale of 1**, where the count *is* the
  reading and there is no average to drop it for.
- **`aggregate`** hands the widget the tally the listing already read, so thirty cards run **no query
  of their own**; left out, each one reads its own. `ui_rating()` takes it as a fifth argument, and
  reads anything but that shape as no vote at all — a catalog card is no place to raise an error.
- **Only a listing rendered outside the block cache asks for it.** The html of a cached block is shared
  by every visitor and its averages would freeze with it, which is why `Book:Books`, `Strip:Cards` and
  ShopBundle's `Product:Products` all take the widget as an opt-in prop their index pages alone pass.

- **The icon and the scale are the site's**, two `configs.json` entries of the `general` group:
  `ui-rating-icon` (`star`, `heart`, `thumbs-up`, `face-smile`) and `ui-rating-scale` (1 to 10). A
  scale of **1 is a "like"**: the count replaces the average and clicking again takes the vote back.
- **The scale is read server-side, never off the request** — a forged POST would otherwise store a 10
  on a site rated out of 5.
- **One vote each, without a login**: an authenticated visitor is keyed on their account, anyone else
  on a 32-hex token their own browser mints **on the click** and never before, which is what keeps the
  widget out of consent territory. Both land in the same `voter` column under one unique constraint.
- **`POST /rating/{ownerType}/{ownerId}` (`ui_rating_vote`) takes no CSRF token** and answers
  `no-store`: a token would open a session whose `Set-Cookie` the shared cache would hand to the next
  visitor. A json body, an `Origin`/`Referer` of this site and the `ui_rating` limiter stand in its
  place.
- **A vote the limiter turns down is told apart from every other failure** — the widget writes
  `label.rating_throttled` into its tally, not `label.rating_error`: a visitor rating a whole catalog
  reaches that ceiling in the ordinary course of browsing, and "come back in a few minutes" is the one
  thing that answers it. Either key is overridden in the app's own `translations/`.
- **`RatingSnippetBuilder` publishes the tally to a search engine** — `build($ownerType, $ownerId)`, or
  `buildFromAggregate($aggregate)` off a tally the listing already read. It returns a *fragment*, never
  a graph: schema.org reads an `AggregateRating` as a property of the thing rated, so the bundle owning
  that thing nests the node in its own. An owner nobody voted on returns `[]`, a zeroed node being what
  Google rejects the whole rich result for.
- **Nothing cascades.** Whichever service deletes the rated row *for good* calls
  `RatingRepository::deleteForOwner()` — on the permanent delete only, never on a trash: a restored
  entity has to find its notes where it left them. A whole set goes through
  `deleteForOwners($ownerType, $ownerIds)`, one query for the lot rather than one per row.

## Written reviews

A rating is one anonymous click; a **review** is a text, a name and a decision to publish. Two things,
one owner vocabulary: `Entity\Review` carries the same `ownerType`/`ownerId` pair as `Rating`, both
nullable — filled for a review about one listed thing, null for a review about the site itself.

The same rows hold **what a visitor wrote here** and **what a platform was asked for** (see
c975l/social-bundle's `ReviewsSourceInterface`). Only `source` tells them apart: `Review::SOURCE_SITE`
(`'site'`) for the first, the platform's own name for the second. What separated them was never worth
two entities — ten columns out of eleven were the same.

```twig
{# The whole section - the published reviews and the fold the form opens in - rendered once and kept #}
{{ ui_reviews_section('book', book.id) }}
```

`ui_reviews_section()` holds its render in the same tagged cache the page's blocks are in, emptied on
every review written, imported or moderated, and answers an empty string while `ui-enable-reviews` is
off. Reach for `ui_reviews()` and the `Review/List` component directly only to lay the section out
differently — the caching is then yours to do.

- **`ui-enable-reviews`** (bool, `false` by default) gates the whole feature at once: the public form,
  the management screen, the collection source. Off, `ui_reviews()` returns `[]` rather than failing.
  It is flipped from the dashboard's own toggle row as well as from the Config screen
  (`Controller\Management\ReviewShortcutController`, `site-role-admin`).
- **A submission is born `pending` and unverified**, whatever the form sent — the two fields deciding
  whether a text is readable and whether the site vouches for it are never the author's to fill.
  `ReviewStatus` is `pending` / `published` / `rejected`; an import is born `published`, its platform
  having moderated it already.
- **Nothing but `published` is ever served**: every repository method a visitor reaches goes through
  `publishedQueryBuilder()`, so adding one never adds a way around moderation.
- **`GET|POST /review/{token}` (`ui_review_new`) names what is reviewed through a signed token**, never
  through its id: `ReviewTokenSigner` signs `ownerType:ownerId` with the app secret, so a public url
  prints no database id and `/review/book/1..n` walks nothing. Build it with `ui_review_url()` and
  never with `path()`, which has no id to be given any more. It resolves what is being reviewed
  through `FavoriteItemProviderInterface` — the wishlist's own providers, rather than a contract of
  its own — and 404s on an unsigned token as on an id nobody claims.
- **The form is fetched, not printed.** The section renders a `<details>` fold whose panel loads the
  form on the first open (`assets/js/review-form.js`); the same route serves the form alone to an
  XHR and the whole page to a plain visit, so the sheet around it carries no session, no CSRF token
  and no `Set-Cookie`, and works with javascript off as a plain link.
- **A submitted review is notified to the site.** `ReviewNotifier` sends the site's own `email-to`
  address a plain-text notice in the site's locale, its result ignored — a review is stored whatever
  the mailer answers. `ReviewAlertProvider` says on the dashboard how many are waiting.
- **The score goes into the same average as the clicks.** Publishing a review carrying a rating calls
  `RatingService::record()` under a voter derived from the author's e-mail (a truncated sha-256, so
  `Rating` still holds no address of anyone); rejecting or deleting it calls `withdraw()`.
  `ReviewService::syncRating()` does both and is called on **every** save, so no transition has to be
  remembered. A visitor who clicked the stars *and* left a scored review counts twice — the price of
  the anonymous vote, already accepted in `resolveVoter()`.
- **The "vérifié" badge is earned, never assumed.** `ReviewVerifierInterface` (auto-discovered, one per
  owner type) answers "did that address get hold of that thing?"; the shop's own implementation reads
  the paid orders of the address and compares **item ids**, never titles or slugs. No verifier for
  a kind means `verified: false` — the badge says the site checked, not that it had no way to. Settled
  once in `submit()` and never recomputed: an order archived years later must not un-verify a review.
- **A review is never rewritten here.** The moderation screen edits nothing the author wrote: a local
  review can be published, rejected or deleted, an imported one can only be answered — removing it
  would hide here what stays published there, which is what L111-7-2 forbids.
- **The public answer travels first.** `ReviewService::reply()` hands the reply to
  `ReviewReplyRegistry`, which finds the platform's publisher (`ReviewReplyPublisherInterface`,
  auto-discovered by interface) and lets its exception through — a reply stored here but refused there
  would show an answer its author never received. A local review has no platform and is simply stored.
- **Displayed by the generic `collection` block**, never by a kind of its own:
  `ReviewCollectionSourceProvider` exposes the source `ui.collection.reviews`, cache tag `ui_reviews`,
  item template `templates/collection/ReviewItem.html.twig` — the very card `ui_reviews()` draws, so a
  book's reviews and the site's wall never drift apart.

## Wishlist

The same terms as the ratings above, for a thing a visitor puts *aside*: `Entity\Favorite` stores an
`ownerType`/`ownerId` pair and a `holder`, so no bundle maps a collection it never reads.

```twig
<twig:c975LUi:Favorite:Button ownerType="shop_product" ownerId="{{ product.id }}"/>
```

- **A row is unreadable until its owner resolves it.** Implement `Contract\FavoriteItemProviderInterface`
  in the bundle owning the thing — `supports($ownerType)` plus `getItems($ownerType, $ownerIds)`
  answering **the whole page at once**, keyed by owner id, as the very `Model\CollectionItem` the
  `collection` block hands its own items over as. Auto-discovered, no tag
  (`DependencyInjection\Compiler\FavoriteItemProviderPass`). Two providers claiming one `ownerType`
  throws; a kind nobody implements is simply dropped from the list rather than drawn empty.
- **Leaving out what the visitor may no longer see** — a draft, something trashed, something withdrawn
  from sale — **is the provider's own call**, being the only one that knows what "published" means for
  its kind of thing. A wishlist is public reading.
- **Whose list it is** is one opaque `holder`: `u<id>` for an authenticated visitor, so it follows them
  to another browser, a 32-hex token their own browser mints **on the click** otherwise, kept in its own
  `localStorage` store. One column for both, under a unique constraint on
  `(owner_type, owner_id, holder)` — which is what lets a list built anonymously be handed over to the
  account on the next authenticated request carrying that token.
- **The three routes take no CSRF token**, for the reason the vote's does not: `ui_favorite_page`
  (`GET /favorites`, the cacheable shell), `ui_favorite_toggle` and `ui_favorite_list` (both POST,
  `no-store`). A json body, an `Origin`/`Referer` of this site and the `ui_favorite` limiter stand in
  its place. `ui_favorite_list` is a POST because of the token, which must not reach a url.
- **A refused change is read on a line of its own** — a `role="status"` paragraph under the heart
  (`.favorite-status`, `data-ui-favorite-target="status"`), carrying `label.favorite_throttled` when the
  limiter turned the change down and `label.favorite_error` otherwise. The button is a shape cut out of a
  color: it carries no visible text and is in no live region, so a message written into its `aria-label`
  is neither seen nor announced. One bucket per address covers `ui_favorite_toggle` and `ui_favorite_list`
  alike, so `/favorites` opens on that same message rather than announcing a breakdown.
- **A template overriding `templates/components/Favorite/Button.html.twig` has to keep that element** —
  the controller empties it on every click, and Stimulus throws on a target it cannot find. A copy taken
  before this release leaves the heart dead on the first click.
- **The button dispatches `ui-favorite:changed`** with `{count}`, bubbling — what a navbar counter
  listens to.
- **Nothing cascades here either**: `FavoriteRepository::deleteForOwner()` / `deleteForOwners()`, on the
  permanent delete only.

## Exporting

`BlockDataExporter` / `BlockDataImporter` are the shared Block/Media serialization behind every content
export carrying a block collection, containers walked recursively, medias and files included. **Reuse
them** rather than writing a walk of your own. A content export never carries the `Form` or
`EmailTemplate` a `form` block points at — seed yours on the way in with
`FormBlockDependencyProviderInterface`.

## Do not

- **Do not add a column for a block's own data** — it goes in `Block::$data`.
- **Do not use `OneToMany` or `orphanRemoval`** for a blocks collection.
- **Do not `remove()` a trashable entity from its delete action**, and do not filter the flag out at
  each caller instead of in the repository.
- **Do not write a page template per entity** when blocks would compose it.
- **Do not cache a kind that embeds a form or reads outside data.**
- **Do not test `hidden` at each caller** — `render_block()` already answers with an empty string. Filter
  it only where a template counts blocks or opens a cell of its own before rendering them.
- **Do not re-implement the block export walk.**
- **Do not write legal text in a template** or duplicate the legal models.
- **Do not add a field for outside profile urls** to the contact block — contribute them through
  `SameAsProviderInterface`.
- **Do not make a singleton kind pickable.**
- **Do not map a relation to `Rating`**, and do not read a rating's scale off the request.
- **Do not map a relation to `Favorite`** either, and do not resolve a wishlist one row at a time —
  `getItems()` is handed the whole page's ids.
- **Do not return from a `FavoriteItemProviderInterface` what the visitor may no longer see.**
- **Do not publish `RatingSnippetBuilder`'s node as a graph of its own** — nest it in the node of the
  thing rated.
- **Do not read `app.flashes` unguarded** in a template or in an overridden `{% block flashes %}`:
  reading the bag starts a session for every anonymous visitor. Wrap it in `ui_can_hold_flash()`, as
  this bundle's `layout.html.twig` and `Form` component do.
- **Do not call `RatingRepository::deleteForOwner()` from a trash action** — only from the
  permanent delete.
- **Do not build the review url with `path('ui_review_new', ...)`** — the route takes a signed token,
  which only `ui_review_url()` (or `ReviewTokenSigner::sign()`) mints.
- **Do not screenshot a block to illustrate it** — the silhouette is drawn in
  `sass/_block-thumbs.scss`, and a capture goes stale the day a template or the theme moves.
- **Do not write the thumb's five parts out by hand** — render `c975LUi:Blocks:Thumb`, the very markup
  the picker builds.
- **Do not add a built-in kind to this bundle from an app** — `c975l:ui:block:create` generates into
  the app's own namespace, which is where a one-off kind belongs.
