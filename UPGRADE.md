# UPGRADE

## From `v1.7` to `v1.8`

### A `flip_card` is no longer a `.card`

The two kinds shared `sass/_cards.scss` until now: a flip card's face *was* a `.card`, header band, body
padding and all. It is its own object from this release on — outlined, rounded, its title inside the face —
and everything it is made of lives in `UiBundle/sass/_flip-card.scss`.

On each site, two things to check:

```bash
grep -rn 'flip-card' assets/styles/ templates/
```

- **A stylesheet reaching a flip card through `.card`** (`.flip-card .card`, `.flip-card .card-header`…)
  matches nothing any more. The faces are `.flip-card-face` / `.flip-card-front` / `.flip-card-back`, the
  title is `.flip-card-title` and the content `.flip-card-body`.
- **A theme retuning a flip card through the card's tokens** does the same through its own:
  `--flip-card-radius`, `--flip-card-border-width`, `--flip-card-front-background`,
  `--flip-card-back-background`, `--flip-card-title-size`, `--flip-card-text-size` and
  `--flip-card-media-max-width`. That separation is the point — retuning a card no longer moves a flip card.

The twelve accents work exactly as before, through `flip-card--accent-*` classes of the kind's own pointing
`--flip-card-accent` at the same `--block-accent-*` tokens. Stored values are untouched: a flip card marked
"red" stays marked "red", and the accent now paints its outline and its two titles rather than a header band.
Nothing has to be re-entered.

### The honeypot field hides itself with a class

`Service\FormBotProtection::addHoneypotField()` writes `'class' => 'ui-field-aside'` on the decoy's row and
label, where it used to write an off-screen `style=""`. A nonce-based `style-src` makes `'unsafe-inline'` a
no-op and no nonce ever authorizes an *attribute*, so on any site carrying the CSP of `v1.7` both rules were
dropped by the browser and the decoy showed up in the middle of the form, asking every visitor for a
"Department".

Nothing to do if the site's forms are built by the bundles. **A site rendering a form through its own form
theme** has to let that class reach the rendered row and label like any other — a theme hard-coding
`class="..."` on a row rather than merging `row_attr` swallows it, and the decoy becomes visible again. The
class name deliberately states nothing: it ships in the compiled stylesheet, where a telling one would be a
grep away for the very scripts the field exists to catch.

## From `v1.6` to `v1.7`

### The front layout now nonces `style-src`, so a theme's `style=""` stops being applied

`UiBundle/templates/layout.html.twig` calls `csp_nonce('style')` on every page it renders. In CSP2 and
above, a directive carrying a nonce ignores `'unsafe-inline'` altogether, so every inline style served by
a site using this layout is now dropped by the browser unless it carries that same nonce: `style=""`
attributes in the site's own templates, and inline styles written by third-party scripts.

The bundles' own front templates no longer hold a single `style=""` attribute, so nothing inside them
changes. What has to be checked is the site's own templates and any embedded widget.

On each site:

```bash
grep -rn 'style="' templates/
```

Each hit moves to a class in the site's stylesheet. Where the value is computed at render time — a
background image, a width — the pattern used by the bundles is a `<style>` element carrying the nonce:

```twig
<style nonce="{{ csp_nonce('style') }}">#{{ elementId }} { background-image: url('{{ url }}'); }</style>
```

The site's own scripts are subject to the same rule, a nonce authorizing an element and never an
attribute — so `el.style.top = …`, `el.style.cssText = …` and `el.setAttribute('style', …)` stop being
applied too:

```bash
grep -rn '\.style\.\|setAttribute(.style.' assets/
```

Each hit becomes a class the script toggles. For a value only the runtime knows — a measured position —
`UiBundle`'s `assets/js/nonced-style-element.js` builds a nonce'd `<style>` to write it into, and
`assets/js/block-edit-overlay.js` shows the pattern.

A site rendering from its own layout rather than `UiBundle`'s is unaffected until it makes the same call.

## From `v1.5` to `v1.6`

### A config whose value is a fixed list is now picked, not typed

`site_config` gains a `choices` column, and a new `choice` entry kind alongside `text`/`bool`/`json`/`font`.
An entry declaring `"kind": "choice"` plus a `"choices": [...]` list is edited as a `<select>` over exactly
those values, and refuses anything else — in the back office and through `c975l:config:set` alike.

Nothing about the values already in production changes: the column is added empty, `c975l:config:load-all`
fills it from each bundle's `configs.json` the same way it already re-syncs `label`/`kind`/`group`, and no
`value` is ever rewritten. A value that is *not* on its entry's list — set by hand before the list existed —
stays exactly as it is, stays selectable in the form so the entry can still be opened, and is only rejected
the day someone saves that entry, which is the moment to replace it.

On each site:

```bash
composer update c975l/core-bundle
php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate
php bin/console c975l:config:load-all
```

Three entries become `choice` in this release, all of them settings whose wrong value used to be swallowed
silently by whatever read them:

| Config | Values | What a typo used to do |
| --- | --- | --- |
| `theme-mode` | `auto`, `light`, `dark` | read as `auto`, the site staying on the visitor's own preference |
| `ui-watermark-position` | `top-left`, `top-right`, `bottom-right`, `bottom-left` | read as `bottom-right` |
| `ui-ai-assistant-rephrase-provider` | `anthropic`, `openai`, `euria` | the AI assistant answering nothing at all |

Check those three in the back office after the upgrade: an off-list value is exactly what this change is
about, and this is where it becomes visible.

Bundles of your own declaring a config read back against a fixed list should follow — see the `choice` kind in
ConfigBundle's README.

### A footer credit is a choice of what it shows, and can show a name

`display-made-by` and `display-hosted-by` are `choice` entries now — `none`, `logo`, `name` or `logo-name` —
and two keys join the `credits` group: `site-made-by-name` and `site-hosted-by-name`, the text half of a credit
whose logo and url were already declared.

**Four keys move to this bundle**: `site-hosted-by-url`, `site-hosted-by-logo`, `display-made-by` and
`display-hosted-by`, declared by SiteBundle until now. `site-made-by-logo` and `site-made-by-url` came here in
`v1.5` and their hosted-by counterparts had no reason to stay behind. Same slugs, same `credits` group, same
`site_config` rows — only the bundle declaring them changes.

**`credits_mode()`** is the Twig function to read those two entries with (SiteBundle's `MadeBy`/`HostedBy`
components do). It always answers one of the four modes, and that is where the value of an older site is
understood: `c975l:config:load-all` never rewrites a `value` (see `ConfigService::syncMetadata`), so a row still
holding `"true"` reads as `logo` — the only thing a credit could show back then — and `"false"` as `none`. A
footer switched off stays off, which a raw `config('display-made-by')` would no longer manage: as a `choice`,
the string `"false"` is no longer cast to a boolean, and any non-empty string is truthy in Twig.

Nothing breaks untouched then, but the back office keeps offering that stale `true` as an extra entry of the
select, and refuses to save the entry while it is picked. Re-pick both values after the upgrade, or:

```sql
UPDATE site_config SET value = 'logo' WHERE slug IN ('display-made-by', 'display-hosted-by') AND value = 'true';
UPDATE site_config SET value = 'none' WHERE slug IN ('display-made-by', 'display-hosted-by') AND value = 'false';
```

### A menu no longer offers a container that can't hold its items

`BlockRegistry::isAllowedInContext()` now applies to the `menu` context the opt-in rule it already applied
inside a container's slots: **a container kind is only offered in a menu if it declared `contexts: 'menu'`
itself.** In practice that means `c975l/site-bundle`'s `menu_group` — built with the `menu_slot` slot context a
`menu_link` opts into — and no longer UiBundle's own `block_group`, which shares its form and its template but
builds its slots with the default context, where no menu link is ever allowed. A `block_group` picked in a
footer was accepted by the picker, then refused every link dropped into it (`kind_not_allowed_in_target`), with
no way for the editor to tell why. Non-container kinds are untouched: a footer takes any of them on purpose.

**A footer composed before `menu_group` existed may hold a `block_group`.** It renders exactly as it did — a
stored block's kind is never re-checked against a context — and keeps holding whatever it holds: what it never
accepted, before this release as after it, is a `menu_link`. So a footer grouping social links or an image in a
`block_group` needs nothing; one meant to group *links* wants a `menu_group` instead, the group being only a
wrapper. Nothing is migrated automatically, for that exact reason: both are legitimate.

### A refused block move is explained, in a modal

Dragging a saved block into a container it doesn't take used to end on a native `alert()` saying only that the
move failed. It now opens a Bootstrap modal carrying the reason the server gave, when it is one an editor can
act on. Three things come with it, none needing action: `assets/js/admin-modal.js`, exporting
`showAdminMessage(title, message, closeLabel)` for any admin script; a `data-block-move-close-label` attribute
added by `Service\BlockMoveRowAttrBuilder` (so a caller asserting on that method's exact output has to follow);
and the `action.close` / `flash.block_move_kind_not_allowed` translations in the `ui` domain.

### The block layer's body text now follows the page's own reading size

`c975l/site-bundle` gains `--font-size-body`, the size every text set in the body font is read at (it defaults
to `1rem`, i.e. the browser's own default, which is what a site was read at before the token existed). For that
setting to mean anything, the block layer's body-font text is now sized in `em` rather than in fixed pixels:
a `feature_bar`'s items, a `cta_band`'s paragraph, an `expertise_banner`'s text, a `process_steps` step's text,
a `portfolio_grid` card's text, a `hero`'s stat label, a `section_btn`'s label, a slider's caption and a
`blockquote`. **Titles and eyebrows are deliberately left in px** — they are the design's own marks and must not
grow with the body copy — and so is everything already read through a token of its own.

Nothing moves on a site that sets no `--font-size-body`: every converted value is the exact `em` equivalent of
the pixel length it replaces at the default `1rem`. A site that *does* raise the token sees its block text
follow, which is the point. `BlockTextScaleTest` (UiBundle) and `BodyFontSizeTest` (SiteBundle) lock the split.

One default changes unit with it: `--hero-sub-size` now falls back to `1.1875em` instead of `19px`. Same size
at the default reading size; a theme already setting that token in px is unaffected.

## From `v1.4` to `v1.5`

### The status report is now read from the site, not sent by it

`c975l:status:send` **is gone**, and so is the `site-status-url` config entry it read. A site no longer posts
its report anywhere: it serves it at `/status/report`, to a caller presenting the site's key in the
`X-Status-Key` header, and answers 404 to everyone else — including when no key is configured, which stays the
default and means "answer nobody".

Sending could only ever say what was true when the site last spoke, and could never say the site was down: a
receiver had to infer that from a silence, which takes days to become certain and reads as current data in the
meantime. Asked instead, the answer is true at the moment it is read, and no answer at all is itself an answer.

On each site:

```bash
composer update c975l/core-bundle
php bin/console c975l:config:load-all
php bin/console c975l:config:prune --force   # drops the now-undeclared site-status-url
```

Then **remove any scheduled entry or crontab line calling `c975l:status:send`** — it no longer exists, so it
would fail on every run.

Then **import ConfigBundle's controllers**, if the app does not already:

```yaml
# config/routes.yaml
c975l_config:
    resource: "@c975LConfigBundle/src/Controller/"
    type: attribute
```

An app that did not import them — it was pointless until now, the bundle having no attribute-routed controller,
everything under `Controller/Management/` being routed by EasyAdmin's dashboard — gets a 404 on
`/status/report` until this import is there, and that 404 is indistinguishable from the one a wrong key gets.

An app whose `access_control` protects more than `^/management` — a back office with a catch-all `^/` rule, say
— has to let the new route through, or its callers meet a login redirect, which says as much about the url
existing as a 404 would not:

```yaml
- { path: ^/status/report$, roles: PUBLIC_ACCESS }
```

`site-status-key` is unchanged and keeps its value; only its role moves, from signing what left the site to
authenticating who may read it. A site that had no key set stays closed, as before. It now has to be **at least
32 characters** — read online rather than merely presented in an outgoing report, a short key is guessable, so
anything shorter is treated as no key at all and the site answers nobody.

On the receiving side, whatever collected the reports has to **fetch them** instead of exposing an endpoint:

```bash
curl -H 'X-Status-Key: <that site's key>' https://example.com/status/report
```

The payload is unchanged, `version` included — a collector already reading it needs no other change.

`php bin/console c975l:status:dump` replaces `c975l:status:send --dump`: same JSON, no key and no network needed.

## From `v1.3` to `v1.4`

### The offsite `deleted/` folder is now called `previous/`

`c975l:config:backup:offsite` moves what a mirror would have overwritten or removed into a dated folder at the
destination, and that folder was named after the rclone mechanism filling it rather than after what it holds.
`deleted/` reads as a bin containing only what was removed — a file merely overwritten is in there too — and it
is the folder someone opens on the day a gallery got emptied, so it now says what it is:

```text
<target>/previous/2026-08-05/public/medias/…
```

Nothing to do on a destination that has never had a mirror overwrite anything (the folder is created on the first
one, and its absence is not an error). Where it exists, **rename it once** — left as it is, `deleted/` stops being
written to and stops being purged, so it stays offsite untouched until someone removes it by hand:

```bash
rclone --config rclone.conf moveto <target>/deleted <target>/previous
```

`site-backup-offsite-keep-days` is unchanged and now purges `previous/`.

### `privacy_embed_url` covers every declared platform, not YouTube alone

The filter (and the `video_iframe` block's "Use no-cookie version" checkbox behind it) rewrote YouTube
URLs and left everything else untouched. It now reads `Video\VideoPlatform`, UiBundle's registry, so
**Vimeo and Dailymotion are rewritten too** — to their own canonical embed URL, Vimeo's carrying its
`dnt=1` do-not-track flag. A URL belonging to no declared platform is still left exactly as it was.

Two consequences on stored blocks:

- A `video_iframe` block whose `src` is a Vimeo or Dailymotion URL **is rewritten the next time it is
  saved** with the checkbox on (`BlockVideoNoCookieListener`). Nothing rewrites the ones already stored —
  they go on rendering from the URL they hold.
- **Player parameters on an already-formed embed URL are dropped** by the rewrite (`?autoplay=1`,
  `?start=30`), only the id being read back. This was already true of the `/watch?t=42s` URLs the filter
  was written for. A block relying on such a parameter has to carry it through the block's own form
  instead — or keep its checkbox off, which leaves the URL untouched.
  Two parameters are the exception, being what makes the player play at all rather than an option:
  YouTube's `list` (a playlist, whose video id is the literal `videoseries`) and Vimeo's `h` (the access
  token of an unlisted video). Both are kept through the rewrite.

Adding a platform is now a case in `VideoPlatform`, and the origins its player needs framed are exposed
as a parameter for a site's CSP:

```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    csp:
        enforce:
            frame-src: ['self', '%c975l_ui.video.embed_origins%']
            # The level 1 fallback, for browsers that don't know frame-src
            child-src: ['self', '%c975l_ui.video.embed_origins%']
```

### A linkable route entry can stand for one row rather than for a screen

`LinkableRouteProviderInterface` returned route names only, so a route taking a parameter
(`/galerie/{category}`) could not be offered as a menu target at all. An entry may now key itself on one
row of the contributing bundle's own data and name what to build its url with — GalleryBundle lists each
of its categories that way:

```php
$routes['gallery_category.' . $category->getId()] = [
    'label' => (string) $category->getTitle(),
    // The row's own title, no key to translate
    'translation_domain' => false,
    // Optional, and shown by the back office's target select alone
    'picker_label' => 'Galerie - ' . $category->getTitle(),
    'route' => 'gallery_category',
    'params' => ['category' => (string) $category->getSlug()],
];
```

**Existing providers keep working as they are**: a key that is a parameter-less route name, with a label
and its domain, is still the common case, and `LinkableRouteRegistry` fills the two new keys in itself.

Two things to know if you read the registry yourself rather than through SiteBundle's menus:

- `get()`/`all()` hand back **normalized** entries — `route`, `params` and `translation_domain` are always
  there. Code translating `$entry['label']` on its own should call `label()` instead, which handles a
  literal label too, or `pickerLabel()` where it is building a list to choose a target from.
- Providers are walked on the **first read** rather than in the constructor, so one listing rows queries
  the database only on the pages that actually read the registry.

## From `v1.2.5` to `v1.3`

**The backup no longer archives `public/` and `private/` whole.** It archives the database and the files
each bundle *declares*, and mirrors the rest instead of tarring it. Rolling nine gigabytes of JPEG into a
monthly `tar.bz2` bought about one percent of compression for an hour of CPU against a one-hour timeout,
and produced an archive whose only use was to be extracted whole. Four things to do per site.

**1. Nothing to install.** `site-backup-offsite-target`, `site-backup-offsite-max-age-hours` and
`site-backup-offsite-keep-days` arrive on their own with `configs.json`; `site-backup-full-interval-months`
is gone and its row can be deleted. `var/BackupDateTimeFile` and `var/BackupFullDateTimeFile` are no longer
read and can go with it. The scheduled `c975l:config:backup` keeps its name, its alias and its slot.

**2. Declare your uploads.** ConfigBundle declares `.env.local`, UiBundle its `medias/site` and `medias/fonts`
folders plus the site-wide graphics at the root of `public/`. Every other folder — a gallery, a shop's
downloadable files, anything of your own on either `public/medias/<bundle>/` or `private/medias/<bundle>/` —
needs a `BackupPathProviderInterface`, in the bundle that owns it or in the app
(see [the readme](ConfigBundle/README.md#what-is-backed-up-and-what-deliberately-isnt)). Check what the
first run publishes:

```bash
php bin/console c975l:config:backup && cat var/backup/manifest.json
```

An upload folder missing from `mirror` is an upload folder nothing is saving.

**3. Choose how the files leave the server.** Set `site-backup-offsite-target` to an rclone remote to push,
or leave it empty and have your existing offsite process call `c975l:config:backup:offsite --ack` when it's
done pulling. Do one or the other: until either happens, every run warns that nothing has ever left the
machine, which is exactly what it should say.

**4. Seed the first mirror by hand.** It transfers everything and takes as long as that takes, and the
Scheduler has a single worker to block:

```bash
php bin/console c975l:config:backup:offsite
```

**If you restore from the archives with scripts of your own, they need changing.** No
`WEBSITE_-_…_-_Complete.tar.bz2` or `…_-_Partial.tar.bz2` is produced any more. The database archives are
untouched; the files split in two — a small dated `FILES_-_…tar.bz2` holding the `archive` paths, and the
mirror, which is restored by copying it back rather than by extracting a complete archive and replaying
every partial over it in order. The archives already on disk stay readable by whatever reads them today.

**Thumbnails are no longer cropped square.** `-thumb.webp` is now generated inset — the whole image, its
longest side capped at `getThumbnailSize()` — instead of outbound-cropped to a square. A portrait no longer
loses its top and bottom to the crop, but a grid that relied on every thumbnail coming out the same square
now gets mixed shapes: the files already on disk stay square, the ones generated from now on don't. If a
template sized its tiles off the file itself, size the tile in CSS instead and let the image fill it:

```css
.thumbnail { aspect-ratio: 1; object-fit: cover; }
```

Nothing regenerates the thumbnails already written; re-uploading a media rewrites its own.

## From `v1.2.3` to `v1.2.4`

**An upload is never enlarged any more.** `VichImageResizeListener` capped the highres derivative at the
original's own width but not the entity's own stored file, so a source narrower than the target width was
blown up to it — a softer, heavier file made of pixels that were never there. On a
`VichMultiSizeImageInterface` entity (GalleryBundle's photos, typically), the effect was worse than
cosmetic: a 800px original stored a 1024px "medium" next to a 800px "highres", the two resolutions
inverted, and a viewer offering to zoom showed something smaller than what it zoomed from.

Nothing to run, but **the fix only applies to what is uploaded from now on**: files already stored keep
the size they were written at. To find out whether a site is affected, compare a stored image with its
own source — or, for a gallery, a photo with its `-highres` sibling:

```bash
# From the app's public/ directory, on any photo of the gallery
identify -format "%f %wx%h\n" medias/gallery/*/*/photo-*.webp | head
```

A "medium" wider than its `-highres` sibling means that photo was upscaled on upload. There is no
regeneration command: the original is gone, and re-deriving from the stored file would upscale it a
second time. Re-upload those photos from their originals if the high resolution matters; leave them
otherwise, they are no worse than they were yesterday.

## From `v1.1.2` to `v1.2`

**`robots.txt`, `humans.txt` and `llms.txt` are generated files now.** `c975l:seo:files:create` writes the three of them into `public/` from the new `seo` config group (see [ConfigBundle's readme](ConfigBundle/README.md#robotstxt-humanstxt-and-llmstxt)). Three things to do once per site.

**1. Carry what your `robots.txt` said into the configs.** The first run replaces the file, keeping a copy at `existingFiles/public/robots.txt.old` — a file this bundle wrote carries a marker, one it didn't is backed up before being overwritten. Your own `Disallow:` lines go into `seo-robots-disallow`, anything else into `seo-robots-extra`; the `Sitemap:` line is derived from `site-url` and needs no config at all. Same for `humans.txt`, whose `From:` and `THANKS` block become `seo-humans-from` and `seo-humans-thanks`.

**2. Stop tracking them in git.** `c975l:scaffold:install` adds the three to the app's `.gitignore`, but a rule never untracks a file git already follows — they are rewritten from this environment's own configs on every deployment, so a committed copy is a merge conflict waiting to happen:

```bash
git rm --cached public/robots.txt public/humans.txt public/llms.txt
```

(`git rm --cached` on a path git doesn't track answers `did not match any files`, which is fine — it means there was nothing to untrack.)

**3. Add the command to your deployment.** Right after the sitemaps, `robots.txt` only declaring the sitemap index once that run has written one:

```diff
 echo "------> Creation des sitemaps";
 ${{ env.PHP_VERSION }} bin/console c975l:sitemaps:create --env=prod;
+echo "------> Creation des fichiers SEO (robots.txt, humans.txt, llms.txt)";
+${{ env.PHP_VERSION }} bin/console c975l:seo:files:create --env=prod;
```

Nothing else is required: the command is also scheduled nightly by `ConfigMaintenanceTaskProvider`, and a "Create the SEO files" tile sits on the dashboard. Until it has run at least once, the health check reports `robots.txt` as missing — which is exactly what it is.

**`seo-robots-block-ai` ships on**, and the generated `robots.txt` blocks the crawlers listed in `seo-robots-ai-crawlers` — never the answer engines, which keep reading the site and citing it back. A config already in the database keeps the value it holds, `c975l:config:load-all` never overwriting one: a site installed before this change stays off until it is switched on from the back-office, or with `c975l:config:set seo-robots-block-ai true`. From there the monthly `ai-crawlers` health check reports what appeared in the community list, with `c975l:seo:crawlers:update` (or its dashboard tile) to merge it in.

A site that hand-wrote its `robots.txt` before `c975l:seo:files:create` existed gets it backed up to `existingFiles/public/robots.txt.old` on the first run, and the generated file starts from the configs alone — **the paths the old file forbade are not read out of it**. Copy them into `seo-robots-disallow` (e.g. `["/*.pdf$"]`) before or after that first run, or the site silently stops forbidding what it used to.

**A site whose `robots.txt` closed everything** (`User-agent: * / Disallow: /` — a private site, an API, a staging environment) is the case to settle *before* the first run, not after: the generated file would otherwise open it to crawlers, and the nightly `ConfigMaintenanceTaskProvider` entry runs the command whether you do or not. Set `seo-robots-private` to `true` first, and the generated file closes the same way it used to.

```bash
php bin/console c975l:config:set seo-robots-private true --env=prod
php bin/console c975l:seo:files:create --env=prod
```

## From `c975l/config-bundle` and `c975l/ui-bundle` to `c975l/core-bundle`

The two packages have been merged into a single one. **The bundles themselves have not changed**: same namespaces, same service ids, same template paths, same translation domains, same `bundles.php` entries.

### What to change

```diff
 "require": {
-    "c975l/config-bundle": "^5",
-    "c975l/ui-bundle": "^1",
+    "c975l/core-bundle": "^1.0"
 }
```

That is the whole migration for a site that only consumes the two bundles. A site whose own `src/` uses `c975L\ConfigBundle\…` or `c975L\UiBundle\…` classes keeps its code untouched — the namespaces are unchanged.

### What you do not have to change

- no PHP `use` statement
- no `@c975LConfig/…` or `@c975LUi/…` template reference
- no translation key
- no `config/bundles.php` entry — both bundles are still registered separately
- no `configs.json` key

### The one dependency that changes

`nelmio/security-bundle` is a real requirement of this package, where `c975l/config-bundle` only suggested it: UiBundle's minimal layout calls `csp_nonce()` on every page it renders. A site that did not have it gets it installed — and registered by its own Flex recipe — with nothing to do. A site that already had it keeps its own configuration.

### If you forget

`c975l/core-bundle` declares `replace` for both old package names, so a satellite bundle still requiring `c975l/config-bundle` or `c975l/ui-bundle` resolves onto this package instead of installing a second copy of the same namespaces.

The replacement is declared at the exact versions this package supersedes (`config-bundle 6.0.0`, `ui-bundle 1.18.0`), not at `*`. An older constraint such as `c975l/config-bundle: ^5` therefore **fails to resolve** rather than silently receiving newer, incompatible code — read what changed in each of them below, and update the constraint.

### The old packages

`c975l/config-bundle` and `c975l/ui-bundle` are abandoned in favour of `c975l/core-bundle`. Their last published versions (`v5.17.1` and `v1.17.0`) remain installable forever; they simply receive no further releases.

---

## What changed inside the two bundles

Everything below was written as `config-bundle 6.0.0` and `ui-bundle 1.18.0`. Neither was ever published: they ship as this package's first release, and this is where their migration notes live from now on. Each bundle's own `UPGRADE.md` stops at its last published version — [ConfigBundle](ConfigBundle/UPGRADE.md) at `v5.17.1`, [UiBundle](UiBundle/UPGRADE.md) at `v1.17.0`.

### ConfigBundle

**A c975L bundle is located through the kernel now, not through `vendor/c975l/*`.** Four services guessed a bundle's directory from its Composer package name — and this package ships two bundles one directory below that guess, so `c975l:config:load-all` loaded nothing, `c975l:scaffold:install` copied nothing, `c975l:config:check-importmap` reported clean, and `c975l:deprecations:check` blamed nobody. They all ask `Service\BundleLocator` now, which reads `%kernel.bundles_metadata%` and keeps the `c975L\` namespaces. **Nothing to run**, with one consequence worth knowing: only the bundles **registered in `config/bundles.php`** are discovered, where the glob read anything sitting in `vendor/`. A bundle installed but not registered contributes nothing to the application anyway — three cases meet that description: one disabled for a while, one registered for `dev` only (`kernel.bundles_metadata` holds the current environment's bundles, no others), and one pulled in as another bundle's dependency and never enabled.

Where that matters is `c975l:config:prune` and its "Obsolete configs" page, the only two things here that delete: an entry declared by such a bundle is no longer seen as declared, and would have looked like an orphan to delete. Both now tell that case apart — they read Composer's own installed-package registry for it — and report those entries separately, never offering them for deletion. **If one of your entries shows up in that list, register its bundle rather than delete the row**: the value you typed in is still under it.

**`ImportmapProviderInterface::get*ImportmapEntries()` returns a path relative to your own bundle.** `ImportmapRegistry` prefixes it with wherever that bundle really sits, so no bundle spells out its place under `vendor/` — the very assumption this merge broke, and which would break again the next time two bundles ship together. A provider the application itself ships is left untouched: its paths are the project root's own, exactly as they appear in `importmap.php`.

```diff
 '@c975l/my-bundle/controllers-admin.js' => [
-    'path' => './vendor/c975l/my-bundle/assets/controllers-admin.js',
+    'path' => 'assets/controllers-admin.js',
     'entrypoint' => true,
 ],
```

In this ecosystem that means `c975l/site-bundle`, `c975l/gallery-bundle` and `c975l/social-bundle`, five entries between them. A satellite left unchanged writes a path prefixed twice into `importmap.php`, which AssetMapper cannot resolve — so update it in the same release that bumps its `c975l/core-bundle` requirement.

**Redirects, the site-wide health checks and the content-quality machinery moved here from SiteBundle.** None of them needed a Page: a url that changed needs a redirect whether it was a page's or a product's, a TLS certificate belongs to the host, and a shop's own urls deserve the same content checks a page gets. Namespace for namespace:

| Was, in SiteBundle | Now |
| --- | --- |
| `Entity\Redirect`, `Repository\RedirectRepository` | `c975L\ConfigBundle\*` (table `site_redirect` unchanged) |
| `EventSubscriber\RedirectSubscriber` | `c975L\ConfigBundle\EventSubscriber\RedirectSubscriber` |
| `Controller\Management\RedirectCrudController` | `c975L\ConfigBundle\Controller\Management\RedirectCrudController` |
| `Management\Redirect{Export,Import}Provider`, `RedirectChainHealthCheckProvider` | `c975L\ConfigBundle\Management\*` |
| `Management\{SslCertificate,SecurityHeaders,SeoFiles}HealthCheckProvider` | `c975L\ConfigBundle\Management\*` |
| `Service\{SslCertificate,SecurityHeaders,SeoFiles}Client` | `c975L\ConfigBundle\Service\*` |
| `Management\ContentQualityAnalyzer`, `Service\ContentQualityClient` | `c975L\ConfigBundle\*` |
| `Management\DeclaredUrlsHealthCheckProvider`, `DeclaredUrlsHealthCheckPass` | `c975L\ConfigBundle\*` |
| `Service\PageExistenceChecker` | `c975L\ConfigBundle\Service\UrlStatusChecker` |
| `Twig\CopyrightExtension` (`site_copyright()`) | `c975L\ConfigBundle\Twig\CopyrightExtension` |
| `Service\Security\SessionNonceGenerator` | `c975L\ConfigBundle\Security\SessionNonceGenerator` |

**Nothing to run**: same table, same route names, same config slugs (`site-author` and `site-first-online-date` are declared here now, matched on their existing `site_config` row), same `site_copyright()` Twig function.

Three new contracts come with it:

- **`Service\SiteUrlResolver`** — `siteRoot()` returns the one spelling of the site root (`https://example.com/`) every site-wide check groups its dashboard row under. SiteBundle's `PagePublicUrlResolver::resolveSiteRoot()` is gone; its home Page resolves to this exact string, so a site with pages and one without land on the same row.
- **`Management\ContentOffenceLocatorInterface`** (+ `ContentOffenceLocatorRegistry`) — how a bundle turns an offence the analyzer found (an image with no alt text, a broken link) back into a link to the screen that fixes it. SiteBundle registers `PageContentOffenceLocator` for its blocks; implement it and your service is auto-tagged. Without one the offence is still reported, just unlinked.
- **`Management\SelfCheckedSitemapProviderInterface`** — a `SitemapProviderInterface` implementing it gets no generic `urls-<name>` check built on top of it, having one of its own. SiteBundle's `SitePageSitemapProvider` uses it; `DeclaredUrlsHealthCheckPass` no longer names any class from another bundle.

**`security-headers` reads the site root instead of the home Page**, so it runs on a site with no pages at all. Its row label is `null` rather than the page title; same url, same dashboard row.

**`nelmio/security-bundle` is a `suggest` of this bundle now.** `SessionNonceGenerator` keeps a CSP nonce stable across a Turbo visit; it is registered only when the interface exists (`config/services_nelmio.yaml`), so an app without that bundle is unaffected.

**`HealthCheckErrorRow` replaces SiteBundle's `HealthCheckErrorRowTrait`.** The "the check itself blew up" row (network/API failure rather than a check result) is what every health check calling something over the network has to build, so it belongs next to `HealthCheckResult` and `HealthCheckProviderInterface`. Two changes on the way: it is a static class rather than a trait (a trait shared across bundles is only ever analysed against the users of its own package), and the translation domain — hardcoded to `site` — is a parameter, the summary being the calling bundle's own wording:

```diff
-$this->errorRow($url, $label, 'label.my_check_failed', $e->getMessage());
+HealthCheckErrorRow::build($this->translator, 'my-domain', $url, $label, 'label.my_check_failed', $e->getMessage());
```

**`Twig\CanonicalUrlExtension` moved here from SiteBundle.** `canonical_url()` builds the canonical url of the page being rendered from `site-url` and the current path, stripping the query string and normalizing the trailing slash — every bundle serving urls of its own needs one, not just the one serving Pages. Same function name, same behaviour.

**`url-terms-of-use` is declared here now.** SiteBundle, ShopBundle and PaymentBundle each shipped an identical declaration of it, and `c975L\PaymentBundle\Form\PaymentFormFactory` — which requires neither Site nor Shop — is what reads it. One declaration, at the ancestor the three have in common. Same slug, same `legal` group, **nothing to run**.

**The nine legal identity keys are declared here now.** `site-name`, `site-director` and `site-contact-email` already were; `site-owner`, `site-producer`, `site-hosting-provider`, `site-dpo`, `site-director-location` and `site-contact-phone` join them, from SiteBundle. UiBundle's legal models print all nine (see its own section below), and a site running a shop without page management had no way to fill six of them. Same slugs, same `legal` group, same severities, **nothing to run** — only the bundle declaring them changes.

**The email configs every bundle sends through moved here.** `c975L\UiBundle\Service\EmailService` resolves its From/To/Reply-To from `email-from`/`email-to`/`email-reply-to` and their `-name` counterparts — six keys, of which only `email-from` was declared here (and declared a second, identical time by SiteBundle). The other five lived in SiteBundle alone, so an app running Config + Ui + a satellite bundle threw `Missing email parameter(s)` on its first send, this bundle's own account-confirmation email included. All six are declared here now, and SiteBundle's duplicate `email-from` is gone. **Nothing to run**: the slugs, groups and severities are unchanged, so an existing site's rows are matched as they are.

`site-name`, `site-contact-email`, `site-director`, `site-made-by-logo` and `site-made-by-url` moved for the same reason — this bundle's `DashboardController`, `MenuProvider`, `ConfigEssentialActionProvider` and `DeploymentHealthCheckProvider` all read them, and a back-office with no title is not a site-content problem. The five `email-text-*` keys stay in SiteBundle: they are the copy of its own branded email layout.

**Sending an email from a satellite bundle no longer needs SiteBundle at all.** The whole chain is Config + Ui: seed the template with `FormSeeder::ensureEmailTemplate()`, compose it with `EmailTemplateRenderer::renderNamed()`, hand the result to `EmailService::send()` as `html:`. The wrapper comes from whichever `EmailLayoutProviderInterface` is registered — SiteBundle's branded layout when installed, UiBundle's plain shell otherwise — and the addresses from the six configs above. `EmailVerifier` is the worked example.

**The account layer moved here from SiteBundle.** Every satellite bundle (Shop, Book, Gallery, Crowdfunding, Payment, Social) requires this bundle and UiBundle, none requires SiteBundle — yet all of them relate their entities to `Contract\UserInterface`, whose only implementation, back-office and registration flow lived in SiteBundle. An app running Config + Ui + a satellite bundle therefore had accounts it could neither create nor manage. What moved, namespace for namespace:

| Was | Is now |
| --- | --- |
| `c975L\SiteBundle\Controller\Management\UserCrudController` | `c975L\ConfigBundle\Controller\Management\UserCrudController` |
| `c975L\SiteBundle\Security\Voter\UserManagementVoter` | `c975L\ConfigBundle\Security\Voter\UserManagementVoter` |
| `c975L\SiteBundle\Service\UserRegistrar` | `c975L\ConfigBundle\Service\UserRegistrar` |
| `c975L\SiteBundle\Service\EmailVerifier` | `c975L\ConfigBundle\Service\EmailVerifier` |
| `c975L\SiteBundle\Service\PasswordResetter` | `c975L\ConfigBundle\Service\PasswordResetter` |

The `user-roles-available` config, the `label.users`/`label.roles`/`label.info_user*` translations and the "Users" menu entry moved with them; the entry is now contributed by this bundle's own `MenuProvider`. **Nothing to run**: `site_user` is the app's own table, untouched, and both the config row and the translation keys keep their exact names.

**Re-scaffold the account files.** `App\Entity\User`, `App\Entity\ResetPasswordRequest`, their repositories, `App\Security\UserChecker`, `App\Form\ChangePasswordFormType`, the three controllers (`Security`, `Registration`, `ResetPassword`), the two `FormAction` services, `templates/security/login.html.twig`, `templates/reset_password/reset.html.twig` and the `validators` catalog are shipped by this bundle's scaffold now instead of SiteBundle's. They keep their exact paths in your app, so this is a re-scaffold, not a move:

```bash
php bin/console c975l:scaffold:install --dry-run
php bin/console c975l:scaffold:install
```

Your own copies land in `existingFiles/*.old`. Read them back if you had edited any — three of them changed behaviour on the way:

- **`RegistrationController` and `ResetPasswordController` redirect to `app_login`.** They used to resolve the SiteBundle `Page` carrying the matching `form` Block and fall back on `page_home`, which a Config-only app has neither of. Every outcome now lands on the login form, the one route this scaffold owns itself — the visitor isn't authenticated yet at any of those points, and logging in is where the flow was heading.
- **`login.html.twig` calls UiBundle's `form_url()`** instead of SiteBundle's `site_page_for_form_block()`. Same result on a site running SiteBundle (the real Page with its admin-editable per-locale slug, through the new `FormPageUrlProviderInterface`), the bare `ui_form_submit` route elsewhere. Both it and `reset.html.twig` extend `templates/layout.html.twig`, the name every c975L app already uses for its own shell — the error pages extend it too.
- **`templates/layout.html.twig` is scaffolded here now**, SiteBundle's scaffold no longer shipping it (nor the `base.html.twig` that only existed to alias it). One file for both worlds, since Twig takes the first template of the list that exists:

  ```twig
  {% extends ['@c975LSite/layout.html.twig', '@c975LUi/layout.html.twig'] %}
  ```

  A site running SiteBundle keeps its full layout — header, footer, navigation, SEO, theme — exactly as before; an app without it falls back on UiBundle's new minimal shell (stylesheets, importmap, flashes, `content` block). Adding or removing SiteBundle changes nothing in your app, and the file stays yours to replace outright with your own markup. `c975l:scaffold:install` will back your current one up to `existingFiles/` and hand you this one: unless you had put real markup in it, take the new version. **`templates/base.html.twig` becomes an orphan** — delete it once nothing extends it.
- **`ResetPasswordRequestFormAction` renders the `password_reset` EmailTemplate directly**, rather than the `@c975LSite/emails/reset_password_email.html.twig` file that no longer exists.

**The registration and reset-password emails are composed from their EmailTemplate, not from a Twig file.** `EmailVerifier::sendEmailConfirmation()` lost its `$template` argument and `UserRegistrar::register()` with it; both now render the admin-editable `account_validation` template through `EmailTemplateRenderer::renderNamed()`, wrapped by whichever bundle registers an `EmailLayoutProviderInterface` — SiteBundle's branded layout when it is installed, UiBundle's plain fallback otherwise. **Update your calls** if you drive either service yourself:

```diff
-$userRegistrar->register($user, $plainPassword, 'app_verify_email', $subject, '@c975LSite/emails/confirmation_email.html.twig', $email);
+$userRegistrar->register($user, $plainPassword, 'app_verify_email', $subject, $email);
```

Both return `false` without sending when the named template has been renamed or deleted from the back-office, an empty email being worse than none.

**`register` and `reset_password_request` are seeded by `UserFormSeeder` now**, not by SiteBundle's `DefaultPagesImporter` — which keeps seeding the Pages that carry them, and delegates. Idempotent as before, so an existing site's Forms and EmailTemplates are left exactly as they are.

**Added `c975l:config:user-create`**, an interactive equivalent of the account step of `c975l:site:create` for an app that has no site foundation to run that wizard on. It creates the account through the new `AdminUserCreator` (which `c975l:site:create` also calls now, so the two can't drift) and seeds the account Forms around it.

**The scaffold installer moved here too.** `c975L\SiteBundle\Service\ScaffoldInstaller` and `c975l:scaffold:install` are `c975L\ConfigBundle\Service\ScaffoldInstaller` and the same command — the tool installing every bundle's `scaffold/` has no reason to live in one of them. Same command name, same behaviour, nothing to run.

**The failed-Messenger screen, the table export and their two shortcuts moved here.** `MessengerFailedMessageService`, `MessengerAlertProvider`, `MessengerFailedController`, `SingleEnvelopeReceiver`, `MessengerCleanupCommand` and `ExportTablesCommand` are `c975L\ConfigBundle\*` now — cross-cutting infrastructure any bundle queueing a message needs, not site content. Three renames follow:

| Was | Is now |
| --- | --- |
| `c975l:site:messenger-cleanup` | `c975l:config:messenger-cleanup` |
| `c975l:site:export-tables` | `c975l:config:export-tables` |
| `management_site_messenger_failed*` routes | `management_config_messenger_failed*` |

The cleanup command is scheduled through `MaintenanceTaskProviderInterface`, so **nothing to change in a crontab** — the schedule resolves the new name by itself. The `site-messenger-cleanup-mailto` and `site-messenger-cleanup-retention-days` configs keep their slugs. The "Export tables" and "Enable/disable registration" dashboard shortcuts are contributed by `ConfigShortcutProvider` now.

**No Messenger configuration is required for any of it**: listing, purging and deleting go through Doctrine. `MessengerFailedMessageService`'s `$failureReceiver` is optional (`@?messenger.transport.failed`), so an app without `framework.messenger.failure_transport` still compiles its container — it just can't replay a failed message, `retry()` reporting it as not found. Only relevant if you instantiate the service yourself: that argument is nullable now.

**The `deployment` health check moved here**, along with `DeploymentClient` — it only ever reads `site-url` and probes the host over HTTP, which is this bundle's territory. Its `ssl-certificate` and `security-headers` neighbours stay in SiteBundle: both resolve a real `Page` to probe.

**The scaffolded `App\Scheduler\MaintenanceSchedule` is shipped by this bundle now**, SiteBundle's scaffold no longer carrying it — every maintenance task it runs is declared here. Same file, same path, so `c975l:scaffold:install` reports it as already up to date.

**`ManagementAuthenticationListener` is removed — add an `access_control` rule for `/management`.** The listener threw an `InsufficientAuthenticationException` on any `/management` request without an authenticated user, so that visitors landed on the login form rather than on a 403. It read the token from a `kernel.request` listener at priority 7, on the assumption that the firewall (priority 8) had already resolved it — which only holds when the firewall is *not* lazy. On the `lazy: true` firewall the Symfony skeleton ships, the token is resolved only when something first reads it, so `Security::getUser()` returned `null` even for a fully authenticated admin, and every back-office request was redirected to the login page.

Declare the rule instead:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }
```

Behaviour is unchanged: an anonymous visitor is still redirected to your login form, and an authenticated user without the `site-role-admin` role still gets a 403 from the controllers' `denyAccessUnlessGranted()`. Nothing else to do — no code of yours referenced the listener, which was registered through its own `#[AsEventListener]` attribute.

Note that the redirect survives even without the rule: an anonymous visitor reaching the dashboard gets an `AccessDeniedException` from the controller, and Symfony's security `ExceptionListener` turns it into the same redirect to the login form, since the token isn't fully fledged. The rule is what stops the request before the controller runs, and what keeps a lazy firewall from deferring the token past the point where the back-office needs it.

**The bundle now requires PHP 8.4 and Symfony 8.** It used to declare `"php": ">=8.0"` and `"symfony/*": "*"`, which described nothing: the code has needed PHP 8.1 since its first promoted `readonly` property, and an unbound `*` let Composer resolve Symfony against whatever PHP the application ran on - so an application on PHP 8.2 silently got Symfony 7 with a bundle only ever tested against Symfony 8. The requirements now say what is actually built and tested: `"php": ">=8.4"` and `"symfony/*": "^8.0"`.

If your application is still on Symfony 7, stay on the previous release until you migrate - `composer update c975l/config-bundle` will simply refuse to move rather than break anything. Nothing in the bundle's own code changes with it: no new syntax, no removed method.

**Your `App\Entity\User` must now implement `c975L\ConfigBundle\Contract\UserInterface`.** `Config::$user` was typed `App\Entity\User`, a class that lives in app-space and that a standalone bundle checkout cannot reference; it is now typed against this new interface, which `c975LConfigBundle::prependExtension()` maps back onto `App\Entity\User` through Doctrine's `resolve_target_entities` - so there is nothing to declare in your app's configuration, but the PHP property type rejects a user entity that doesn't implement the interface (`TypeError` on hydration, and on saving a config from the back-office):

```php
// src/Entity/User.php
use c975L\ConfigBundle\Contract\UserInterface;

class User implements UserInterface
{
    // ...
}
```

The interface extends `Symfony\Component\Security\Core\User\UserInterface` (which your `User` already implements) and adds `getId(): int|string|null` - satisfied by the getter Doctrine entities carry anyway, whether the identifier is an auto-increment integer or a uuid. Nothing else changes: no migration, the column and the join stay identical.

**`ThemePresetProviderInterface` and `ThemePresetRegistry` are removed**, along with the `c975l.theme_preset_provider` tag. The admin action applying a preset had already been removed (see the note below), leaving the interface with no consumer of its own; a site now carries one theme it owns outright rather than a catalog to switch between. Delete any provider of yours implementing it - nothing needs to replace it: a site's design tokens live in its own `assets/styles/themes/theme.css` (see `c975l/site-bundle`'s readme).

**New required Composer dependency: `symfony/messenger`**, and the **"Run health check now" button no longer runs the check in your request** - it dispatches one `RunCommandMessage` per registered kind (`c975l:health-check:run --kind=…`, the command the scheduler already runs) and returns immediately. A single provider can hold thousands of urls (`c975l/site-bundle`'s `DeclaredUrlsHealthCheckProvider` declares one per photo of a gallery), and a run that times out mid-way persists nothing at all. To actually get the asynchronous behaviour, route the message and consume the transport:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Symfony\Component\Console\Messenger\RunCommandMessage: async
```

```bash
php bin/console messenger:consume async scheduler_site
```

Without the routing, Messenger handles the message synchronously and the button blocks the request exactly as before - nothing breaks, it just gains nothing. `HealthCheckController`'s constructor gained a `MessageBusInterface` argument (autowired, only relevant if you extend or instantiate it yourself), and the `flash.health_check_run_success` translation is replaced by `flash.health_check_queued` - override it again if you had overridden the old one.

The new `HealthCheckAlertProvider` raises a dashboard alert when the last run left errors (danger) or warnings only (warning), with the date of that run - which is what tells you a queued run is done. It's auto-registered like any `AlertProviderInterface`, nothing to wire; it stays silent while nothing has been checked or nothing is left to fix.

**New required Composer dependency: `symfony/ux-chartjs`** (pulled in for the Health check page's trend chart, see `HealthCheckTrendChartBuilder`). Unlike the other notes below, this one breaks the container at *compile* time, not just a missing feature - `composer update symfony/ux-chartjs` (or `composer update c975l/config-bundle`) in your app right after upgrading, so `Symfony\UX\Chartjs\Builder\ChartBuilderInterface` actually exists for autowiring. If Symfony Flex is active in your app it should also register `ChartjsBundle` in `config/bundles.php` and add its own `importmap.php`/`chart.js` entries automatically; if it doesn't (recipe declined, or an app not using Flex), add `Symfony\UX\Chartjs\ChartjsBundle::class => ['all' => true]` there by hand.

Added the `HealthCheckResult` entity (`site_health_check_result` table, see the new "Health check" dashboard page): run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate`.

Added a "Guided tour" button on `/management`, highlighting menu items that declare a `description` (see `MenuProviderInterface`) - it's ConfigBundle's first bundle-shipped admin JS, so it needs the same one-time `importmap.php` entry as `c975l/ui-bundle`'s own `admin.js` (see the README's [JS assets loaded on the dashboard](README.md#js-assets-loaded-on-the-dashboard) section):

```php
'@c975l/config-bundle/controllers-admin.js' => [
    'path' => './vendor/c975l/config-bundle/assets/controllers-admin.js',
    'entrypoint' => true,
],
```

Without it, `/management` still works exactly as before - the button just doesn't render (`OnboardingStepBuilder::getSteps()` still runs, but no bundle contributes a `description` yet unless you add one, see the README).

`ConfigCrudController`'s constructor gained two arguments, `ConfigRepository` and `EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator` (both autowired, nothing to configure) — only relevant if your app extends or manually instantiates this controller.

The Config list's EasyAdmin index is no longer a single flat table listing every group's entries together - it now shows a "pick a group" screen first (one row per distinct group, with its entry count), then the familiar grid filtered to that group (`?group=...`). Nothing to migrate: existing config rows work as-is, this only changes the admin UX. The EasyAdmin "group" filter is removed from the grid (redundant with the new screen); if you relied on it (e.g. a saved/bookmarked filtered URL using EasyAdmin's own `filters[group][...]` query format), switch to the plain `?group=<slug>` query param instead. If you link directly to the CRUD's index (bypassing the dashboard menu), append `?group=<slug>` to land straight on a given group's grid instead of the group picker.

**`ThemeCrudController` and its "Theme" dashboard menu entry are removed.** It existed to keep the `theme` group's CSS-variable entries out of the general Config list before that list could be filtered by group - now that Config's own "pick a group" screen does exactly that, the dedicated page is redundant. Theme entries (colors, fonts, light/dark mode) are edited from **Config → theme** like any other group. Concretely:

- `/management/theme` (and any bookmarked link to it) is gone - link to Config's `theme` group instead (`?group=theme` on the Config CRUD's index route).
- **Permission changed**: theme entries were viewable/preset-applicable at `site-role-editor` and hand-editable at `ROLE_SUPER_ADMIN`; they're now gated like every other Config entry, at `site-role-admin` for both viewing and editing. A site relying on an editor-level role to manage theme colors/fonts must grant it `site-role-admin` instead (or wait for the Presets UI's eventual rework, see below).
- The "Presets" admin action (apply a vetted preset in one click) and its `applyPreset` route are removed - it was already hidden pending a rework (`// $actions->add(Crud::PAGE_INDEX, $presetsGroup);` was commented out) and had no working entry point. Both `ThemePresetProviderInterface` and `ThemePresetRegistry` have since been removed too - see the note at the top of this file.
- `label.theme` (the removed page's title) is unused but still translated - harmless, not removed.
- If your app extended `ThemeCrudController` or linked to it directly (custom dashboard menu override, etc.), update accordingly - there is no replacement class, `ConfigCrudController` handles every group generically.

### UiBundle

**This bundle now declares the `ui_form` rate limiter itself.** `FormController` takes it as `@?limiter.ui_form`, and a `Form` built in the back office has no dedicated service of its own to bind a named limiter to — so a site that never declared `ui_form` served **every public Form with no rate limiting at all**, registration and password reset included, and nothing said so. That default is prepended here from now on: `sliding_window`, 5 attempts per 10 minutes.

**`symfony/rate-limiter` moves from `require-dev` to `require`** for the same reason: the prepended limiter is worth nothing without the component, and leaving it to each site is what made the protection optional in the first place. Composer pulls it in on update, nothing to install by hand.

**Nothing to run.** A site declaring its own `ui_form` keeps deciding, its `config/packages/rate_limiter.yaml` being merged over the prepended default.

**Check yours for a dead limiter**, though: sites predating the generic Form mechanism declared `registration` and `reset_password`, which the scaffold controllers consumed by name. Those controllers are gone — if your `rate_limiter.yaml` still lists those two and nothing else, they limit nothing, and your forms were running unprotected until this release. Delete them once the site is on this package:

```diff
 framework:
     rate_limiter:
-        registration:
-            policy: sliding_window
-            limit: 5
-            interval: '10 minutes'
-        reset_password:
-            policy: sliding_window
-            limit: 5
-            interval: '10 minutes'
```

Do this **after** migrating, not before: on a site still running the old scaffold controllers, those two limiters are the ones actually in use.

**The theme, the site graphics and the cookie banner moved here from SiteBundle.** All three are what a site cannot go live without, and none of them had anything to do with having pages: an app running Config + Ui plus a shop compiled no theme at all (so every `--c975l-*` token this bundle's CSS reads was missing), had no screen to upload a favicon from, and shipped no GDPR banner.

| Was, in SiteBundle | Now |
| --- | --- |
| `Listener\ThemeVariablesCssListener` | `c975L\UiBundle\Listener\ThemeVariablesCssListener` |
| `Twig\ThemeVariablesExtension` (`theme_variables_css()`) | `c975L\UiBundle\Twig\ThemeVariablesExtension` |
| the ten `theme-*` configs | declared here |
| `Controller\Management\SiteGraphicCrudController` | `c975L\UiBundle\Controller\Management\SiteGraphicCrudController` |
| `Management\SiteGraphic{Alert,Export,Import}Provider` | `c975L\UiBundle\Management\*` |
| `Form\OgImageType` | `c975L\UiBundle\Form\OgImageType` |
| `templates/components/General/CookieConsent.html.twig` | `<twig:c975LUi:Cookie:Consent />` |
| `site-enable-cookie-consent`, `url-cookies-policy` | declared here |
| `Management\SvgFontsHealthCheckProvider` | `c975L\UiBundle\Management\SvgFontsHealthCheckProvider` |

**Nothing to run** — same slugs, same `Media` roles, same `theme_variables_css()`/`site_media()` function names, same generated `bundles/build/site-theme.css`. Four things worth knowing:

- **`bundles/build/site-theme.css` is contributed by `Service\ThemeVariablesStylesheetProvider`**, tagged at priority `0`: after every bundle's compiled defaults (100) and before the app's own `assets/styles/themes/*.css` (auto-tagged at -100). It used to ride along in SiteBundle's own provider, where its position relative to this bundle's sheets was luck rather than intent.
- **The cookie banner carries its own `site-enable-cookie-consent` guard.** A layout renders `<twig:c975LUi:Cookie:Consent />` unconditionally; drop the surrounding `{% if %}` if you had one.
- **The library files moved with it**: `bundles/c975lui/css/cookieconsent.css` and `bundles/c975lui/js/cookieconsent.umd.js` (were `c975lsite`). The Stimulus controller is registered lazily, so it only loads on a page that actually renders the banner.
- **The keys moved domain too**: the site-graphic ones (`label.favicon`, `label.logo`, `label.site_graphic*`, `label.role`…) and the cookie ones (`text.cookies_banner`, `label.cookies_*`) are in `ui` now, the `theme-*` labels in this bundle's `site_config`. Move any app-level override accordingly.

**`templates/layout.html.twig` is no longer a bare shell.** It now renders the theme mode, the site graphics (favicon, apple-touch icon, og:image, logo), the share tags, the canonical link, the robots meta, the font preloads and the cookie banner — the minimum a site running without SiteBundle has to serve. Its blocks (`head`, `meta`, `title`, `fontPreload`, `stylesheets`, `javascripts`, `importmap`, `body`, `flashes`, `content`) mirror SiteBundle's, so a template written against one extends the other unchanged.

**`EmailSendRequest` gained `bcc` and `wrapLayout`, and `EmailService` an `EmailLayoutRegistry` argument.** Both exist so a bundle can ship the *body* of its email and nothing else, the layout staying in one place:

```php
$emailService->send(new EmailSendRequest(
    subject: $subject,
    context: ['basket' => $basket],
    template: '@c975LPayment/emails/confirm_order.html.twig',  // the body alone, no {% extends %}
    wrapLayout: true,
    to: $basket->getEmail(),
    bcc: $shopArchiveAddress,
));
```

`wrapLayout: true` renders the template and wraps the result through whichever `EmailLayoutProviderInterface` is registered — SiteBundle's branded layout when installed, the bare body otherwise. It is the path for **structured** email content (a basket recap, a ticket list) that an `EmailTemplate` cannot express: block substitution is literal, with no loop, and a customer never edits a table of order lines anyway. Editable prose keeps going through `EmailTemplate`/`renderNamed()`.

`bcc` is a real blind copy, distinct from `copyToEmail` which sends a **second**, separate message with its own Reply-To stripped.

**Update your instantiation** of `EmailService` if you build it by hand rather than through the container — the registry is its fourth argument now.

**Nine shared pieces moved out of SiteBundle.** None of them had anything to do with the notion of a site, and several were being hand-duplicated by bundles that require this one and not SiteBundle. What lands here:

| Was in SiteBundle | Is now |
| --- | --- |
| `Form\VichImageOptions` | `c975L\UiBundle\Form\VichImageOptions` |
| `Controller\Management\Trait\UniqueSlugTrait` | `c975L\UiBundle\Service\UniqueSlug` (static) |
| `Controller\Management\Trait\BlockMoveRowAttrTrait` | `c975L\UiBundle\Service\BlockMoveRowAttrBuilder` (service) |
| `Management\BlockFocusUrlTrait` | `c975L\UiBundle\Service\BlockFocusUrl` (static) |
| `Listener\AbstractBlockCacheInvalidationListener` | `c975L\UiBundle\Listener\AbstractBlockCacheInvalidationListener` |
| `Management\BlockDataExporter` / `BlockDataImporter` | `c975L\UiBundle\Management\*` |
| `Controller\DownloadController` | `c975L\UiBundle\Controller\DownloadController` |

The three traits became classes: a trait shared across bundles is only ever analysed against the users living in the same package, so its callers' own properties read nowhere else look dead to PHPStan. `BlockMoveRowAttrBuilder` is a service rather than a static class because it needs a url generator, a CSRF token manager and a translator — which the trait used to borrow from whichever controller used it.

**`BlockDataImporter` no longer knows SiteBundle.** It used to take `DefaultPagesImporter` to backfill the Form/EmailTemplate a `form`-kind Block points at. It now asks `FormBlockDependencyRegistry`, and **every** provider answers in turn — SiteBundle owns `contact`, ConfigBundle owns `register`/`reset_password_request`, and a satellite can own its own. Implement `Contract\FormBlockDependencyProviderInterface` to join in; auto-discovered, nothing to tag.

**`DownloadController` was moved, not merged.** `PrivateFileResponseFactory` serves the digital items bought through ShopBundle/CrowdfundingBundle, from outside `public/` and behind its own access checks; this controller only puts a `Content-Disposition: attachment` on a file the web server already serves. Two different jobs, both here now, still separate.

**Eight config labels were translated nowhere.** `label.ai_assistant_*` (seven keys) and `label.block_showcase_url` had their `description.*` counterpart in `site_config.*.xlf` but no `label.*` entry, so those rows showed the raw key in the back-office. Added in the three locales.

**`site-form-delay` and `site-form-gdpr` moved here from SiteBundle.** This bundle's `FormBotProtection` and `FormSubmissionType` are what read them, and both already fell back to a hardcoded default when SiteBundle wasn't installed — so a Config + Ui + satellite app had a working anti-spam delay and consent checkbox, just no way to configure either. Same slugs, same group, **nothing to run**.

**The legal models moved here from SiteBundle**, along with the `legal_model` block, its customization screen and its drift health check. A site running this bundle with a shop, a book catalogue or a gallery but no page management still owes its visitors a privacy policy — and nothing in a legal document is about pages. The 18 templates are reached at `@c975LUi/models/{country}/{model}.{locale}.html.twig`, the screen at `management_ui_legal_models`, and every `label.legal_*` key moved to the `ui` domain. Blocks keep their kind and their whole `data`, so **nothing to run**; see `c975l/site-bundle`'s UPGRADE for the full before/after table. The two configs those models read on their own, `site-other-copyright` and `site-other-cookies`, are declared here now — same slugs, same `legal` group.

Two things are new around it:

- **`Contract\BlockLocationProviderInterface`** — implement it (auto-discovered, no tag) and return `['label' => …, 'url' => …, 'published' => …]` keyed by `Block::$id` for the blocks your bundle owns. That is what fills the "Legal models" screen's first column and gives the drift check the public url it tests. Not implementing it means those screens list the blocks with no location, which is what an app with no page management gets.
- **`Service\LegalModelEditUrl::build()`** — call it first in your own `BlockEditUrlProviderInterface`, before falling back on `BlockFocusUrl`: a `legal_model` block is edited on its own screen, not on its row in your form. It answers `null` for everything else.

**`twig/intl-extra` is a new requirement**, the four dated models formatting their "latest update" with `format_datetime()`. Composer pulls it in on its own.

**Fonts moved here from SiteBundle.** This bundle already owned `FontProviderInterface`, `FontRegistry` and `FontChoiceType`, and its blocks are what pick a font — the entity and its back-office were the only half living elsewhere. `Entity\Font`, `FontRepository`, `FontCrudController`, `FontBulkImportController`, `FontService`, `FontFilenameParser`, `FontCssListener`, `FontPreloadExtension`, `Font{Export,Import}Provider` and the four `font_*` templates are `c975L\UiBundle\*` now, and the `label.fonts`/`label.font_*`/`flash.font_*` translations moved to the `ui` domain with them. The `site_font` table and the compiled `bundles/build/site-fonts-uploaded.css` are untouched, so **nothing to run**.

**This bundle declares its own menu entries now.** "Media library", "Forms", "Email templates" and "Fonts" were contributed by SiteBundle's `MenuProvider` on this bundle's behalf, so an app running Config + Ui + a satellite bundle had none of those four screens in its back-office. They come from `c975L\UiBundle\Management\MenuProvider` from now on. Entries are sorted by translated label at merge time, so the sidebar is unchanged on a site running SiteBundle.

**Five generic Twig helpers moved here from SiteBundle**: `nl2br` (overriding Twig's own so it emits `<br>` and not `<br />`), `linkify`, `route_exists`, `template_exists` and `asset_exists`. Same names, same behaviour. `asset_exists` was already being called from BookBundle templates, which require this bundle and not SiteBundle — those calls only worked when a site happened to install both.

**`EmailTemplateRenderer` gained an `EmailTemplateRepository` argument** and a `renderNamed()` method: the full standalone document for a template designated by its name rather than held as an entity, which is what a bundle sending a transactional email has. It returns `null` when no template carries that name, so the caller decides what a missing template means. **Update your instantiation** if you build the service by hand rather than through the container.

**Added `Service\FormSeeder`**, the persistence half of what SiteBundle's `DefaultPagesImporter` used to do alone: `ensureForm()` and `ensureEmailTemplate()`, idempotent and backfilling, so each bundle only carries its own field and block definitions. SiteBundle keeps `contact`, ConfigBundle takes `register` and `reset_password_request`.

**Added `FormPageUrlProviderInterface` + `FormPageUrlRegistry` + the `form_url()` Twig function**, answering "where is this named Form really reachable on the front end": the richer page a bundle contributes (SiteBundle's `Page` carrying the matching `form` Block, an admin-editable per-locale slug), else this bundle's own `ui_form_submit` route. It always returns something, so a template no longer has to know which bundles are installed.

**`Contract\HasBlocksInterface` now declares `reorderBlocks(): void`.** `BlockRelocator` has always called it after detaching a block, so an entity that did not have it already fataled there; the interface now says so out loud. An entity using `Entity\Trait\HasBlocksTrait` has nothing to do — the trait implements it. One writing its own accessors has to add:

```php
public function reorderBlocks(): void
{
    $position = 0;
    foreach ($this->blocks as $block) {
        $block->setPosition($position++);
    }
}
```

**Added `Service\BuildFileWriter`**, replacing SiteBundle's `Listener\Trait\BuildFileWriterTrait` — a trait shared across bundles is only ever analysed against the users living in the same package, so its callers' own `$projectDir` looked write-only to PHPStan. Static and stateless, same behaviour. Its `ArchiveFileTrait` counterpart became `c975L\ConfigBundle\Management\ArchiveFileRegistrar` for the same reason.
