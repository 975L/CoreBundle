---
name: c975l-blocks
description: "Use this skill when working with page blocks in a Symfony application built on the c975L ecosystem — attaching a block collection to an entity, registering a custom block kind, containers and their slots, contexts, anchors, the render cache, the edit overlay, and the legal models. Covers what makes a kind cacheable, why a kind is a service tag rather than a class, and how blocks are exported. Triggers on: HasBlocksInterface, HasBlocksTrait, BlockRemovalListener, ui.block tag, render_block, BlockRegistry, pickable, cacheable, contexts, block_group, flex_columns, anchor, BlockCacheInvalidationListener, BlockCacheTagProviderInterface, BlockOwnerResolverInterface, BlockEditUrlProviderInterface, legal_model, c975l:ui:block:create, TrashableInterface, TrashableTrait, isDeleted, trash, soft delete, restore."
---

# c975L UiBundle — blocks

> A page is a sorted collection of blocks composed in the back office. Any entity can own one, and any bundle can add a kind, without either knowing about the other.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi` · **Translation domain:** `ui`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Block.php`, `src/Contract/HasBlocksInterface.php`, `src/Entity/Trait/HasBlocksTrait.php`, `src/Contract/TrashableInterface.php`, `src/Entity/Trait/TrashableTrait.php`, `src/Registry/BlockRegistry.php`, `src/Listener/`, `src/Form/Block/`, `src/Twig/`, `src/Management/BlockDataExporter.php`, `src/Management/BlockDataImporter.php`, `templates/blocks/`, `templates/components/Blocks/`, `config/services.yaml`

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
- `media_types`, `media_required`, `media_multi_upload` drive the media collection, `media_types`
  being enforced on both the input's `accept` and a server-side `File` constraint.
- The owning bundle is derived from the template's Twig namespace — no attribute to fill.

**Un-registering a kind is safe**: a `Block` row outlives its tag, and `render_block()` skips an
unknown kind rather than throwing, so uninstalling a bundle blanks its blocks out of the pages instead
of taking them down.

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

## Legal models

The legal notice, privacy policy, terms of sales and use, cookies and copyright are **this bundle's**:
the `legal_model` block renders them and the **Legal models** screen customizes them section by
section. A site running a shop with no page management needs them just as much. **Do not write legal
text into a template**, and do not duplicate the models in a satellite bundle.

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
- **Do not re-implement the block export walk.**
- **Do not write legal text in a template** or duplicate the legal models.
- **Do not make a singleton kind pickable.**
- **Do not add a built-in kind to this bundle from an app** — `c975l:ui:block:create` generates into
  the app's own namespace, which is where a one-off kind belongs.
