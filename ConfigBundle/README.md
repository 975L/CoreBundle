# c975L ConfigBundle

Symfony bundle providing the EasyAdmin dashboard and database-backed configuration at the root of the c975L ecosystem — the shared hub every satellite bundle plugs into for menus, exports/imports, alerts, and other cross-bundle dashboard contributions.

[![GitHub](https://img.shields.io/github/license/975L/CoreBundle)](https://github.com/975L/CoreBundle/blob/main/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/core-bundle)](https://packagist.org/packages/c975l/core-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/core-bundle)](https://packagist.org/packages/c975l/core-bundle)

> This bundle ships inside **[`c975l/core-bundle`](../README.md)**, alongside
> [UiBundle](../UiBundle/README.md) — one package, two bundles, one release. `composer require
> c975l/config-bundle` is superseded; see the package [README](../README.md) and
> [UPGRADE.md](../UPGRADE.md). The namespace `c975L\ConfigBundle\` is unchanged.

## Why ConfigBundle

![ConfigBundle](../.github/images/ConfigBundle.svg)

The root of the c975L ecosystem — every other bundle ([UiBundle](../UiBundle/README.md), [SiteBundle](https://github.com/975L/SiteBundle), [ShopBundle](https://github.com/975L/ShopBundle), [BookBundle](https://github.com/975L/BookBundle), [GalleryBundle](https://github.com/975L/GalleryBundle), [SocialBundle](https://github.com/975L/SocialBundle)...) depends on it, directly or through UiBundle. It's the single place for application configuration: no per-app `.env` for business config, no duplicated dashboard entry mechanism — a satellite bundle just implements `MenuProviderInterface` (or one of its siblings) and gets an EasyAdmin entry for free.

See it in action at [bundles.975l.com/pages/config-bundle](https://bundles.975l.com/pages/config-bundle).

---

> **TL;DR** — Application configuration lives in the database (`site_config`), not in `.env`: a bundle declares its entries in `config/configs.json`, `c975l:config:load-all` inserts them, the site owner edits the values in EasyAdmin, and any code reads them back through `ConfigServiceInterface` or the `config()` Twig function. ConfigBundle also owns the `/management` dashboard the whole c975L ecosystem plugs into — menus, alerts, shortcuts, health check, backup — which is why most of this README is extension points for other bundles.

## Contents

- **Config entries** — [declare](#defining-config-entries-for-your-bundle) · [load](#loading-config-entries-into-the-database) · [prune](#pruning-entries-no-longer-declared) · [set from the CLI](#setting-values-from-the-command-line) · [encrypt](#encrypting-sensitive-values) · [read in PHP/Twig](#reading-config-values) · [timezone](#timezone)
- **Dashboard** — [EasyAdmin interface](#easyadmin-interface) · [export for deployment](#deploying-to-production--export) · [ROLE_SUPER_ADMIN-only entries](#restricting-configs-to-role_super_admin) · [Export button in another CRUD](#adding-an-export-button-to-another-bundles-crud-controller)
- **Users & access** — [scaffold and first account](#installing-the-scaffold-and-the-first-account) · [users and roles](#users) · [ROLE_SUPER_ADMIN configs](#restricting-configs-to-role_super_admin) · [disabling registration](#disabling-registration) · [registration anti-spam](#registration-anti-spam-protections) · [login throttling](#login-throttling) · [back-office access control](#back-office-access-control) · [account activation](#account-activation-isenabled)
- **Site maintenance** — [Maintenance mode](#maintenance-mode) · [Messenger cleanup](#messenger-cleanup) · [Sessions cleanup](#sessions-cleanup) · [Health check](#health-check) · [Backup](#backup) · [Spreading scheduled commands](#spreading-scheduled-commands-across-installs) · [Status report](#status-report--letting-another-system-read-what-this-site-runs) · [Dev profile](#dev-profile--automating-what-the-dev-toolbar-shows)
- **Extension points for other bundles** — [menu items](#contributing-menu-items-from-other-bundles) · [dashboard alerts](#contributing-dashboard-alerts-from-other-bundles) · [shortcuts](#contributing-dashboard-shortcuts-from-other-bundles) · [essential actions](#contributing-essential-actions-from-other-bundles) · [widgets](#contributing-dashboard-widgets-from-other-bundles) · [guided projects](#contributing-guided-projects-from-other-bundles) · [health check providers](#contributing-health-check-providers-from-other-bundles) and [advice](#contributing-health-check-advice-from-other-bundles) · [maintenance tasks](#contributing-maintenance-tasks-from-other-bundles) · [status data](#contributing-status-data-from-other-bundles) · [sitemaps](#contributing-a-sitemap-from-other-bundles) · [urls to describe](#contributing-urls-to-describe-from-other-bundles) · [importmap entries](#contributing-importmap-entries-from-other-bundles) · [import](#contributing-import-providers-from-other-bundles) and [export providers](#contributing-export-providers-from-other-bundles) · ["What's new" entries](#contributing-whats-new-entries-from-other-bundles) · [linkable routes](#contributing-linkable-routes-for-sitebundle-menus) · [dev profile paths](#contributing-dev-profile-paths-from-other-bundles) · [AI assistant procedures](#contributing-procedures-for-the-dashboard-ai-assistant)
- **For coding agents** — [AI agent skills](#ai-agent-skills)

## Features

- Key-value config entries stored in the database (`site_config` table)
- EasyAdmin CRUD interface to manage values
- `c975l:config:set` to fill values from the command line or a JSON file, for provisioning, deployment and tests, and `c975l:config:get` to read them back
- "Obsolete configs" dashboard page and `c975l:config:prune` to delete entries no `configs*.json` declares anymore
- Export button (SQL/CSV/JSON/Sync-zip) for production deployment, reusable from any bundle's CRUD controller
- Zip-based content import/export for syncing nested bundle content across environments, extensible via `ImportProviderInterface`/`ExportProviderInterface`
- Twig and PHP service to read values anywhere
- 1-hour cache with automatic invalidation on change
- A `site-timezone` entry setting the hour every template shows, on requests and on the console alike, PHP going on writing in its own
- "What's new" dashboard section aggregating release notes declared by every c975L bundle
- Dashboard alerts (danger/warning/info) aggregating what needs attention, declared by every c975L bundle
- Dashboard "Essential actions" checklist, a permanent quick-access entry point to the handful of settings every site needs
- Dashboard widgets contributed by other bundles (e.g. UiBundle's Donovan card)
- Dashboard "Guided tour" walking through every sidebar item that declares a `description`
- Dashboard "Guided projects" walking through a whole task across the admin screens it spans, extensible via `GuidedProjectProviderInterface`
- "Health check" dashboard page (Lighthouse scores, security headers, W3C/accessibility checks...) with history, a trend chart, and CSV export, extensible via `HealthCheckProviderInterface`/`HealthCheckAdviceProviderInterface`
- `c975l:config:backup`, dumping the database table by table and archiving `public/`+`private/`, with archive integrity verification, a retention window on the server, a dashboard alert when a backup stops running, and a weekly digest email for the sites whose dashboard you don't open daily
- `c975l:config:messenger-cleanup`, purging failed Messenger messages past their retention and emailing a digest of the ones worth an admin's attention, with a dashboard screen to read, replay or delete them
- `c975l:config:sessions-cleanup`, deleting the expired rows of the `sessions` table nightly, PHP's own garbage collection being a dice roll a managed host can simply never throw
- Maintenance mode closing the site to its visitors, answering the search-engine-friendly 503 they expect from a temporary outage, with a dashboard alert turning to danger once it has lasted long enough to cost indexing
- Sitemap generation (one sub-sitemap per bundle plus the sitemap index), extensible via `SitemapProviderInterface`
- `c975l:seo:files:create`, writing `robots.txt`, `humans.txt` and `llms.txt` from the `seo` configs and from the urls those same providers declare, with a monthly check reporting the AI crawlers that appeared in the community list
- Url redirects and `410 Gone` rows (`site_redirect` table, EasyAdmin CRUD, export/import, chain/loop check), answering before the router, a `*` on both sides renaming a whole url tree
- Url descriptions for the pages no entity carries (`site_url_metadata` table, EasyAdmin CRUD, export/import), read by the layouts, listed by `c975l:url-metadata:sync` from what each bundle declares via `UrlMetadataProviderInterface`
- The site-wide half of the health check: TLS certificate, security headers, `robots.txt`/sitemaps and the two cross-checked, redirect chains, deployment, and the content quality of every url any bundle declares
- A weekly intrusion check looking for the traces rather than for the doors: an executable file where only uploads are written, a working tree no longer matching what was deployed, and a privileged account more than the run before
- A Turbo-safe CSP nonce generator, holding the nonce in a signed cookie rather than in the session, and the `site_copyright()` Twig function
- `/status/report`, serving what a site runs (versions, installed bundles, health check summary) to whoever presents its key — answers nobody unless configured, extensible via `StatusProviderInterface`, dumped locally by `c975l:status:dump`
- Scheduled maintenance tasks declared by each bundle (`MaintenanceTaskProviderInterface`) rather than listed by the app, and spread over each install's own minutes (`ScheduleSpreader`) so sites sharing a server don't all run them at once
- `c975l:dev-profile:run`, a dev-only command listing what the Symfony dev toolbar would flag on every page (n+1 queries, deprecations, missing translations...), extensible via `DevProfilePathProviderInterface`
- The ecosystem's account layer: `User` CRUD, registration, email confirmation and password reset, on forms and emails seeded once and editable from the back-office afterwards
- `c975l:scaffold:install`, installing every installed c975L bundle's scaffold files into the app and backing up whatever it would replace, and `c975l:config:user-create` to bootstrap the first admin on an app with no site foundation

## Installation

Requires PHP 8.4 and Symfony 8.

```bash
composer require c975l/core-bundle
```

Make your user entity implement `Contract\UserInterface` — that's the interface the c975L bundles relate to instead of `App\Entity\User`, which they cannot reference. It extends Symfony's own `UserInterface` and only adds `getId(): int|string|null`, the getter your entity already has:

```php
// src/Entity/User.php
use c975L\ConfigBundle\Contract\UserInterface;

class User implements UserInterface
{
    // ...
}
```

Doctrine resolves the interface to your entity on its own — `c975LConfigBundle::prependExtension()` declares it through `resolve_target_entities`, there is nothing to add to your configuration.

Run the database migration to create the `site_config` table:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Protect the dashboard in `config/packages/security.yaml`:

```yaml
access_control:
    - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }
```

`IS_AUTHENTICATED_FULLY` rather than an admin role: which role grants the back-office is `site-role-admin`, a config entry editable from the dashboard itself, so it belongs in the controllers' `denyAccessUnlessGranted()` rather than frozen in a yaml file. The rule only states that `/management` is off-limits to anonymous visitors, which is what sends them to your login form instead of a bare 403.

It also matters on the `lazy: true` firewall the Symfony skeleton ships: a lazy firewall defers resolving the token until something actually reads it, and this rule is what makes the firewall authenticate the request up front.

### Installing the scaffold and the first account

`c975l:site:create` is SiteBundle's wizard, and an app running Config + Ui plus a satellite bundle doesn't have it. Two commands cover the same ground here:

```bash
php bin/console c975l:scaffold:install --dry-run
php bin/console c975l:scaffold:install
php bin/console c975l:config:user-create
```

The first copies every installed c975L bundle's `scaffold/` into the app — `App\Entity\User` and its repository, `App\Security\UserChecker`, the security/registration/reset-password controllers and their templates, `App\Scheduler\MaintenanceSchedule`, the `validators` catalog. A target already identical to the source is left untouched, so re-running it is a no-op; `--path=src/Scheduler` restricts a run to one path when propagating a single upgraded file across sites.

Among the tests it installs, `tests/Deploy/DeployWorkflowTest.php` is the one looking outside PHP: it reads every `.github/workflows/*.yml` and resolves each `bin/console <command>` they call against the commands this site actually has — through the console's own resolution, so an abbreviation like `doctrine:migration:migrate` passes. A command a bundle renamed, or one written against a version `composer.lock` does not carry yet, then fails the suite instead of stopping a deployment halfway through. It also fails on any `vendor/` package installed as a symlink — a Composer `path` repository — which would have the whole suite answer for a working copy the deployment never sees.

**A file you customized is never overwritten.** The command records the hash of everything it delivers in `.c975l-scaffold.json` — commit it, like `symfony.lock` — which is what lets a later run tell the two cases apart, indistinguishable by content alone:

| The target is | What happens |
| --- | --- |
| still bit-for-bit what was delivered | only the scaffold moved on: refreshed silently, no backup needed for a file the bundle can reproduce |
| anything else | your own work: left exactly as it is, and named in the output next to the scaffold source to compare it against |

So upgrading a bundle brings the boilerplate along and hands you the short list of files whose upgrade only you can do — typically `templates/security/login.html.twig` once a site has given it a design. `--force` takes the new version anyway, backing yours up to `existingFiles/<same path>.old`; narrow it with `--path` rather than adopting a whole scaffold blind.

A site predating the manifest has nothing to do: every file still identical to its source is recorded on the way past, and only what already differs is reported the first time.

**A file a bundle stopped shipping is deleted under the same rule.** A bundle declares what it withdrew in `scaffold/removed.json`, mapping each path to the hashes of the versions it ever delivered:

```json
{
    "src/Security/EmailVerifier.php": [
        "47da689e3d0d30a280950d94a568a2beb152526715218641e988d1b8d37d27b4",
        "b777580a54fc70dabf083370bb995e27310befee9f23db1faa0dd7845397c78b"
    ]
}
```

Matching one of them (or the manifest entry) means the site never touched the file: it is deleted and named in the output. Anything else is the site's own work, is left exactly where it is, and is reported with the bundle that withdrew it so its `UPGRADE.md` says what replaced it — `--force` deletes those too, the file going to `existingFiles/<same path>.old` first. `--dry-run` shows the list before anything happens, and a path some installed bundle still ships is never deleted: it moved between bundles rather than being withdrawn.

Declaring it rather than deducing it from the manifest is what keeps `composer remove c975l/shop-bundle` from taking that bundle's scaffolded files with it — and what reaches a file withdrawn before the manifest existed, which no site has an entry for.

The second creates an admin account (`--email`/`--password`, asked interactively when omitted) and seeds the `register`/`reset_password_request` Forms and their emails around it, so the account lands in a working login and password-reset flow. An email that already exists is reported and left alone.

## Defining config entries for your bundle

Create a `config/configs.json` file in your bundle. Each entry will be inserted into the database on first load (duplicates are skipped):

```json
[
    {
        "label": "Site Name",
        "slug": "site-name",
        "sensitive": false,
        "value": null,
        "kind": "text",
        "group": "general",
        "description": "Name of the website"
    },
    {
        "label": "Maintenance Mode",
        "slug": "site-maintenance",
        "sensitive": true,
        "value": "false",
        "kind": "bool",
        "group": "system",
        "description": "Set to true to enable maintenance mode"
    },
    {
        "label": "Stripe Secret Key",
        "slug": "stripe-secret-key",
        "sensitive": true,
        "restricted": true,
        "value": null,
        "kind": "text",
        "group": "payment",
        "description": "Stripe secret key (sk_live_...)"
    }
]
```

Supported `kind` values: `text`, `html`, `int`, `bool`, `date`, `json`, `font`, `choice`.
`text` is edited as a plain textarea (URLs, ids, emails...); `html` is for rare configs needing rich content and is edited with EasyAdmin's own rich text editor (same widget as UiBundle blocks).
`choice` is for a value the code reads back against a fixed list — a theme mode, a watermark corner, an API provider. The accepted values go in a `choices` key on the entry, and the admin picks one from a `<select>` instead of typing it:

```json
{
    "label": "label.theme_mode",
    "slug": "theme-mode",
    "value": "auto",
    "kind": "choice",
    "choices": ["auto", "light", "dark"],
    "group": "theme"
}
```

The values are offered as-is, untranslated: they are what gets stored, and what the code comparing them expects. A value off the list is rejected on save (and by `c975l:config:set`) rather than stored — every consumer of such a setting falls back on a default for anything it doesn't recognize, so a typo would otherwise be invisible until someone wondered why the setting did nothing. A value stored **before** the entry became a `choice`, or since dropped from the list, stays selectable in the form so the entry can still be opened and fixed. Declaring `kind: "choice"` without `choices` falls back to a plain text field, an empty `<select>` being worse than free text.
`font` renders a `<select>` (UiBundle's `FontChoiceType`/`FontRegistry`) combining `Config::GENERIC_FONT_FAMILIES` (`serif`, `sans-serif`, `monospace`, always offered) with whatever custom font-family names a registered `FontProviderInterface` knows about (e.g. SiteBundle's `FontService`, parsed from a CSS file's `@font-face` declarations) — falls back to only the 3 generics when no provider is registered. A value no longer offered by either source (e.g. removed from `@font-face`) is kept selectable instead of being silently dropped on the next save.
For `json`, `value` is the raw JSON-encoded string (e.g. `"[\"ROLE_ADMIN\",\"ROLE_EDITOR\"]"`); `ConfigService::get()` returns it already decoded into a PHP array (`[]` if empty/invalid).
Set `sensitive: true` for any entry that holds secrets (API keys, passwords, etc.) — the value is encrypted at rest and masked in the admin list.
Set `restricted: true` on top of that for secrets shared across the whole install rather than per-site data — see [Restricting configs to ROLE_SUPER_ADMIN](#restricting-configs-to-role_super_admin).

`label` and `description` are displayed through the `site_config` translation domain, but neither has to be a key. The label is looked up as `label.<slug with underscores>` (so `console-digest-mailto` → `label.console_digest_mailto`) and the description as whatever string it holds (`description.xxx` by convention); when the lookup finds nothing, the text written in the json is displayed as-is. A bundle shipping configs for several locales therefore keeps its `translations/site_config.*.xlf`, while an application declaring its own configs can simply write both in clear.

`group` is optional and clusters entries on the "pick a group" screen (see below). It must be one of the fixed values in `Config::GROUPS`, each backed by a `label.group_*` translation key:

| Value | Meaning |
| --- | --- |
| `system` | Access control, maintenance mode |
| `general` | Site identity (name, logo, favicon, URL...) |
| `legal` | Terms of use, cookies, legal notice, DPO |
| `credits` | Hosted-by / made-by links and logos |
| `analytics` | Matomo and other tracking |
| `backup` | Database backup settings |
| `email` | Sender/recipient addresses |
| `form` | Contact form behavior (anti-spam delay, GDPR consent) |
| `security` | ReCaptcha and similar anti-abuse keys |
| `shop` | Currency, shipping, shop identity |
| `payment` | Payment provider keys (Stripe...) |
| `theme` | Theme CSS variables (colors, fonts, light/dark mode) |
| `ai` | AI-related settings (LLM providers, prompts...) |
| `messenger` | Symfony Messenger cleanup settings |

This list is closed on purpose so filtering stays useful; if none fits, leave `group` unset rather than inventing a new value (adding one requires extending `Config::GROUPS` and the matching translations in ConfigBundle itself).

`severity` is optional and flags an entry that needs an admin's attention as long as its `value` is empty — it never affects front-end rendering, `ConfigService::get()` still returns `null`/empty as before. It must be one of `Config::SEVERITIES`: `danger`, `warning`, `info`. Any entry with a severity and no value is listed on the `/management` dashboard as a colored alert with a direct link to fill it in; once a value is set, the alert disappears on its own (no flag to unset).

## Loading config entries into the database

Auto-discovers the `config/configs*.json` of every c975L bundle registered in `config/bundles.php` **plus the application's own `config/configs*.json`**, and loads them in one shot — a bundle can ship several files (e.g. `configs.json` plus `configs-css.json` for theme variables), each loaded independently:

```bash
php bin/console c975l:config:load-all
```

The application file is loaded exactly like a bundle's one, so an app needing a setting no bundle declares (its own API keys, feature flags...) just drops a `config/configs.json` at its root and gets it in the dashboard, with no command of its own to write.

New entries (new `slug`) are inserted with their `value` from the JSON. For entries that already exist, only the metadata fixed by the bundle author — `label`, `kind`, `choices`, `group`, `severity`, `description`, `restricted`, `sensitive` — is re-synced from the JSON on every run; the `value` carries production state and is never overwritten, so editing a `configs.json` file (e.g. moving a config to a new group, fixing a typo in a label) and re-running `load-all` is enough to propagate the change, without risking an admin-set value.

### Seeded defaults

One exception to that, and only one: **a row holding nothing takes the value its declaration carries**. A site installed before an entry gained a default held it empty where a fresh install held the value, so two sites on the same version answered differently — and the back office showed an empty field whose real answer lived in a fallback (a CSS `var(--x, …)`, a `?: DEFAULT` in a service) the admin had no way of reading. Seeding puts the answer where it is asked about.

Anything an admin typed is left alone, empty being the only state that is ever written to. Which is what an entry whose *emptiness* carries a meaning has to reckon with: clearing a field writes `null`, the very state this reads as "never filled in", so such an entry names its meaning with a value of its own rather than by being cleared — `0` for the retention entries ("keep everything"), `none` for `seo-robots-ai-crawlers-source` ("review this list by hand"). An entry with nothing sensible to seed — an API key, a site's own url, anything carrying a `severity` so the dashboard asks for it — simply declares no value and keeps its empty row.

A value seeded into a `sensitive` entry is encrypted on the way in, exactly as a fresh install's would be.

`sensitive` is the one flag whose change also touches the value, because the two can't be separated: an entry that becomes sensitive gets its value encrypted, one that stops being sensitive gets it decrypted. Without that, dropping `"sensitive": true` from a declaration would leave a `C975L:…` string sitting in what is now a plain-text setting. When the conversion can't be done — no `C975L_VAULT_KEY`, or a value encrypted with a different one — the flag is left as it was rather than storing something unusable, and the next run picks it up once the key is in place.

A file that can't be loaded — an unparsable JSON, a database refusing the insert — is reported with a `✗` and the run carries on to the next one, so one broken bundle doesn't hide what the others would have said. The command then exits with a failure code, one unloaded file being enough: it runs unattended in deployment scripts, where that single line would otherwise scroll by and the batch carry on with the entries that file declares missing from the site.

`site-role-admin` is the one entry read before it can exist: every `/management` permission derives from it, so a database missing that row — a fresh install where `load-all` hasn't run yet, an entry deleted by mistake — would deny the dashboard to everyone, including whoever would fix it. `ConfigService::loadAll()` therefore falls back on its declared default, `ROLE_ADMIN`, as long as the row is absent.

## Pruning entries no longer declared

An entry dropped from a `configs*.json` (a setting replaced by a proper entity, a bundle uninstalled) stays in database forever: `load-all` only ever inserts and syncs metadata, it never deletes — and it says nothing about those leftovers either, being a deployment step whose output nobody reads. Removing them is an explicit step of its own. From the dashboard, the **Obsolete configs** shortcut (`ROLE_SUPER_ADMIN`) lists them with the value each deletion would take with it, and deletes the ones ticked. Or, without a browser:

```bash
php bin/console c975l:config:prune            # lists them, deletes nothing
php bin/console c975l:config:prune --force    # deletes them, after confirmation
```

Both share the same safeguards, because "undeclared" is only meaningful when the declarations are all there: neither reports a single orphan when no `configs*.json` is found at all, an unfinished `composer install` otherwise making every entry look orphaned, nor when one exists but can't be parsed, a single misplaced comma otherwise turning everything that file declares into an orphan. A third case is reported apart rather than as an orphan: an entry declared by a c975L bundle Composer installed but `config/bundles.php` does not register — a bundle disabled for a while, registered for `dev` only, or pulled in as another bundle's dependency and never enabled. Only registered bundles are read (see the loading section above), so their entries would otherwise look abandoned while their bundle is one line away from declaring them again. They are listed, never offered for deletion. The command adds a confirmation prompt in interactive mode, the page its list of what is about to go. Deletion takes the stored value with it — export your configs first if a bundle is only temporarily uninstalled.

## Setting values from the command line

`load-all` declares the entries, the EasyAdmin interface fills them in. To fill them in without a browser — provisioning a fresh environment, a deployment pipeline, a test fixture, restoring a site — use:

```bash
php bin/console c975l:config:set site-name "My Site"
```

Several entries at once, from a JSON file holding a `{"slug": "value"}` object:

```bash
php bin/console c975l:config:set --file=values.json
```

```json
{
    "site-name": "My Site",
    "site-form-delay": 3,
    "user-roles-available": ["ROLE_ADMIN", "ROLE_EDITOR"],
    "stripe-secret": "sk_live_..."
}
```

Booleans, numbers and arrays are converted to the string stored in database, and each value is checked against its entry `kind` (`bool` only accepts `true`/`false`, `int` an integer, `json` valid JSON, `date` a parsable date, `choice` one of the values the entry declares).

| Option | Effect |
| --- | --- |
| `--if-empty` | Only fills entries whose value is still empty, never overwrites one already set |
| `--dry-run` | Lists what would change without writing anything |
| `--ignore-unknown` | Skips the slugs no installed bundle declares, instead of failing on them |

The command is meant to be re-run: an empty value is always skipped (an incomplete file never blanks out a live setting), an unchanged value is skipped too (no pointless `modification` date), and `--if-empty` makes a whole file idempotent — which is what a deployment pipeline wants, filling in whatever new entry the last `composer update` brought in while leaving production values alone.

Entries are never created here: an unknown slug is reported and the command exits non-zero, so a typo doesn't pass silently. `--ignore-unknown` turns that failure into a skip, for a file shared by several sites where a slug belongs to a bundle this one doesn't install. Sensitive entries are encrypted with `C975L_VAULT_KEY` exactly as the back-office does, are masked in the output so no secret lands in a CI log, and are refused rather than stored in plain text when no key is defined.

## Reading values from the command line

`c975l:config:get` reads back what `c975l:config:set` writes — checking on a server what a setting actually holds, without a browser and without hand-writing SQL against a table whose name is easy to get wrong (it is `site_config`, and the column is `slug`):

```bash
php bin/console c975l:config:get site-name                  # one entry
php bin/console c975l:config:get 'site-backup-offsite*'     # every entry of a family
```

The trailing `*` matches any end of slug (a `%` is accepted too, being the wildcard a hand-written query would have used) — quote it, or the shell expands it against the files of the current directory before the command ever sees it. Values come from the database, not from `ConfigService`, whose cache is exactly what a diagnostic is meant to see past.

| Option | Effect |
| --- | --- |
| `--show-sensitive` | Decrypts sensitive values with `C975L_VAULT_KEY` instead of masking them |
| `--raw` | Prints the values alone, one per line, without table nor decoration |

`--raw` is what feeds a shell variable, `SITE=$(php bin/console c975l:config:get site-name --raw)`. An unknown slug, or a prefix matching nothing, exits non-zero, so a typo in a script doesn't read as an empty value.

## Encrypting sensitive values

Sensitive config values can be encrypted at rest (AES-256-GCM) using a `C975L_VAULT_KEY` defined in `.env.local`. Generate a key:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add it to `.env.local`:

```dotenv
C975L_VAULT_KEY=<generated_key>
```

Then run the following command to encrypt any sensitive value still stored in plain text — it is idempotent and safe to run multiple times, skipping empty or already-encrypted values:

```bash
php bin/console c975l:config:encrypt-sensitive
```

The same command also converts values written before AES-256-GCM, which were held in AES-256-CBC under the same
`C975L_VAULT_KEY` and the same `C975L:` prefix. Nothing is re-keyed: the key never moves, only the algorithm
holding the value does, so there is no key to generate and nothing to change in `.env.local`. Both formats are
read, so a site works before it converts — but only the current one authenticates what it decrypts, which is
what tells a wrong key from a right one instead of letting the padding decide. Conversion is therefore worth
running once per environment, on the environment's own database:

```yaml
# .github/workflows/deploy.yml, right after c975l:config:load-all
php bin/console c975l:config:encrypt-sensitive --no-interaction --env=prod;
```

A value the environment's key cannot read is reported and left as it stands, the command still exiting `0`: a
secret lost to a changed key must not hold a release back.

The site itself is just as forgiving at runtime: a sensitive row that cannot be decrypted is served **empty**,
as an unfilled setting is, and the reason goes to the log (`Sensitive setting left empty, it could not be
decrypted`, with the slug). Every sensitive value is decrypted inside one cache callback, so letting the
failure through used to take the whole configuration down with it — a 500 on every page for a setting nothing
on that page needed. The logged reason tells the two causes apart: nothing read at all points at
`C975L_VAULT_KEY`, while a legacy CBC value that came back as something other than text points at the value
itself, which no key will bring back — re-encrypt it from its source. Such a row is also raised as a **danger
alert on the `/management` home page**, linking to its edit form: the entry shows as filled everywhere, so
nothing else said that the site was running without it.

## EasyAdmin interface

The bundle registers a management dashboard at `/management`. Navigate to **Config** to view entries and edit their `value` — `label`, `slug`, `kind`, `group`, `severity`, and `description` are fixed by the bundle's `configs.json` and shown read-only; there is no manual creation or deletion, entries only come from `configs.json`.

**Config** opens on a "pick a group" screen (one row per distinct `group`, with its entry count) rather than one flat table of every entry — picking a group filters the familiar EasyAdmin grid down to just that group's entries, with a "← Config" action to go back. This keeps the list readable as more bundles/groups accumulate; the entry count shown per group respects both the current sensitive/non-sensitive view and, below `ROLE_SUPER_ADMIN`, excludes restricted entries the viewer wouldn't see anyway. That screen carries the "show sensitive data" button itself, a group holding only sensitive entries (the payment keys) being otherwise reachable only by turning them on from another group's listing first.

Theme CSS variables (colors, fonts, light/dark mode, declared by a bundle like any other entry) sit under the `theme` group — reachable the same way, via **Config**'s "pick a group" screen, at the same `site-role-admin` permission as every other group (no dedicated page, no separate permission tier).

Any entry with a `severity` and an empty `value` shows up as a colored alert (danger/warning/info) right on the `/management` home page, each linking directly to its edit form — as does a `sensitive` entry holding a value the site can no longer decrypt.

### Rows per page

Every list of the back-office — this bundle's, and every other c975L bundle's — shows **20 rows** and offers **20 · 50 · 100** as links under its paginator. EasyAdmin only knows a fixed page size (`Crud::setPaginatorPageSize()`), so the choice is read from a `pageSize` query parameter by `c975L\UiBundle\Management\PaginatorPageSize` and applied in `DashboardController::configureCrud()`, the single Crud config every CRUD controller inherits from. Nothing to wire in your own controllers: they get it for free, media library included (a gallery of thumbnails is exactly where 20 runs short).

The value is validated against that list of three before it reaches a `LIMIT`, and a link goes back to page 1 (page 7 of a 20-row list is out of range once 100 are shown). Sorting and filtering are kept: EasyAdmin rebuilds each admin URL from the current query parameters, which carries the choice along on its own.

The choice is also **remembered in the session**, for the links that don't carry it: an action link (edit, delete, the clickable row) is regenerated by EasyAdmin's `ActionFactory` through `unsetAllExcept()`, a closed whitelist of its own parameters where anything else is dropped — so editing a record and coming back would otherwise always land on 20 rows again. One value for the whole back-office, not one per CRUD; a `pageSize` in the url always wins and becomes the one remembered, while a value the whitelist refuses is never stored.

To offer other sizes, override `@c975LUi/management/paginator.html.twig` in your app **and** the `PaginatorPageSize` service — the template only offers what the service accepts. A single CRUD controller wanting a different default keeps setting its own `->setPaginatorPageSize(...)`, which then wins over the admin's choice for that list.

### JS assets loaded on the dashboard

The `/management` dashboard loads dedicated AssetMapper entries (not your site's main `app` entry), so that satellite bundles needing Stimulus controllers in the back-office don't drag your site's front-end stylesheet into EasyAdmin. `c975l/ui-bundle` contributes one for its block editor — see the [UiBundle README](../UiBundle/README.md#installation) for how to define that entry.

ConfigBundle contributes its own, `@c975l/config-bundle/controllers-admin.js`, for the dashboard's guided tour (see [Contributing menu items from other bundles](#contributing-menu-items-from-other-bundles) below for how a bundle's own menu entries feed into it) and its Health check trend chart (see below). This entry (and any other c975L bundle's own admin JS) is added to your `importmap.php` automatically — see [Contributing importmap entries from other bundles](#contributing-importmap-entries-from-other-bundles) below, nothing to add by hand.

**`symfony/ux-chartjs`** is a regular Composer dependency (not something to add manually) - Symfony Flex registers `ChartjsBundle` and its own `importmap.php`/`chart.js` entries automatically the first time you `composer update` after installing/upgrading ConfigBundle.

That same Flex recipe also writes an **eager** entry into your app's `assets/controllers.json`, which you should turn off:

```json
{
    "controllers": {
        "@symfony/ux-chartjs": {
            "chart": {
                "enabled": false,
                "fetch": "eager"
            }
        }
    },
    "entrypoints": []
}
```

`startStimulusApp()` statically imports every `enabled`+`eager` controller listed there, so leaving it on has two costs. On the **front-end**, your `app.js` pulls `chart.js` (~66 KiB transferred) onto every public page, where no chart is ever rendered. On the **`/management` dashboard**, each admin entry starts its own independent Stimulus app (see `DashboardController::configureAssets()`) and each one registers the chart controller again — with four c975L bundles installed, four applications call `new Chart()` on the same `<canvas>`, which Chart.js rejects with *"Canvas is already in use"*.

On the dashboard, disabling it costs nothing: `controllers-admin.js` registers the chart controller explicitly, once. Use `"enabled": false` rather than `"fetch": "lazy"` — lazy fixes the front-end bytes but still lets every admin Stimulus app register the controller on its own. `c975l:config:check-importmap` warns when it finds the entry still enabled — the warning is about the dashboard, so ignore it if your app calls `render_chart()` on a public page too (that page does need the front-end controller, and `"fetch": "lazy"` is then the right trade-off).

### Deploying to production — Export

On the config list page, click the **Export** dropdown and pick **SQL**, **CSV**, or **JSON**. The browser downloads a `site_config_YYYYMMDD_HHMMSS.{sql,csv,json}` file — nothing is written to disk or version control.

Import the SQL export on your production server:

```bash
mysql -u user -p dbname < site_config_20260626_120000.sql
```

**Behavior per entry type (SQL export only):**

| `is_sensitive` | SQL statement | Effect on production |
| --- | --- | --- |
| `false` | `INSERT … ON DUPLICATE KEY UPDATE` | Creates or updates label, value, kind, group, description, severity |
| `true` | `INSERT IGNORE INTO` | Creates if missing; **preserves existing production value** |

This means non-sensitive values (labels, descriptions, default content) are kept in sync, while live API keys and secrets already set on production are never overwritten.

A fifth **SQL + secrets** export (`ROLE_SUPER_ADMIN`) drops that last safeguard and upserts the sensitive rows too, so an environment where the secrets are already filled in can hand them over instead of having them typed again. The exported value stays encrypted — it is therefore only usable on a target sharing the **same `C975L_VAULT_KEY`**, and on any other target it would replace working secrets with strings that environment cannot decrypt. Use the plain **SQL** export whenever the keys differ. A sensitive entry left empty on the source keeps its `INSERT IGNORE` even there: it has nothing to hand over, and an upsert would only empty the secret filled on the target.

CSV and JSON exports are a straight dump of the table (no upsert logic) — useful for backups, audits, or feeding another tool.

The SQL export is also available as a `/management` dashboard shortcut ("Export (SQL) the configuration", `site-role-admin`), downloading the same file without opening **Config** first.

A fourth **Sync** export produces a zip (`manifest.json` plus any referenced files) instead of a flat table dump — re-upload it on another environment via **Import content** to upsert the same rows there, matched by `slug` rather than by database id. See [Contributing import providers from other bundles](#contributing-import-providers-from-other-bundles) below.

On import, sensitive entries follow the same safeguard as the SQL export: one already holding a value on the target keeps it, since it is encrypted with that environment's own key. One sitting there empty — the blank row `load-all` creates from a declaration — takes the export's value instead, otherwise a secret could never be handed over to an environment that had run `load-all` first.

The `/management` dashboard also has an **Export sync (everything)** shortcut (`site-role-admin`), bundling every registered `ExportProviderInterface`'s whole content (Config plus whatever other bundles contribute, e.g. pages, fonts) into a single zip — the "sync everything to prod in one click" counterpart to the per-bundle **Sync** export above, re-uploaded via **Import content** the same way. See [Contributing export providers from other bundles](#contributing-export-providers-from-other-bundles) below.

## Users

`App\Entity\User` is managed in the EasyAdmin dashboard via `UserCrudController`. The menu entry is registered automatically through `MenuProvider`. Access is controlled by `UserManagementVoter` (`setEntityPermission()`), which grants the `site-role-admin` key, same as pages — except on a `ROLE_SUPER_ADMIN`'s own account, which only another super admin may act on (see [ROLE_SUPER_ADMIN and restricted configs](#role_super_admin-and-restricted-configs)).

The controller relies on EasyAdmin's auto-discovery of the app's own `User` fields (which vary per app), except for:

- The hashed password field, excluded so it's never displayed or overwritten from the backoffice
- The `roles` field, added explicitly as a multiple-choice field, since JSON columns are never auto-discovered by EasyAdmin
- The `creation` / `modification` fields, made readonly since they're set automatically
- The `isVerified` field, made readonly since it must only be set by `EmailVerifier` upon email confirmation, never edited by hand from the backoffice

`ROLE_USER` is always excluded from the choices (every user already has it by default, see `User::getRoles()`). The other selectable roles come from the `user-roles-available` ConfigBundle key (`json` kind, e.g. `["ROLE_ADMIN","ROLE_EDITOR"]`) — add roles for your app there, no code change needed.

A role the edited account already holds is kept in its choices even when the config no longer lists it: Symfony's `ChoiceType` drops a value missing from the choices, so the account would come back from the next save without it. That doesn't make the role grantable — it is only ever offered on the edit page of a user who already has it.

The detail page is disabled (not useful on top of the index and edit pages).

### ROLE_SUPER_ADMIN and restricted configs

Requires `c975l/config-bundle` >= v5.4.

Some configs are shared server-level secrets rather than per-site application settings — for
example `site-backup-db-user`/`site-backup-db-password`, used by ConfigBundle's `c975l:config:backup`: a single privileged MySQL user reused to back up the database, not
something a client's own site admin should ever be able to read or overwrite. ConfigBundle flags
these with `"restricted": true` in
`configs.json`; any config so flagged is hidden entirely (index, edit, and export) from
every user except one holding `ROLE_SUPER_ADMIN`, regardless of `site-role-admin`.

`c975l:site:create` grants `ROLE_SUPER_ADMIN` (together with `ROLE_EDITOR` and `ROLE_ADMIN`) to the
bootstrap user automatically, since whoever runs it owns the site. No `role_hierarchy` is shipped, so
each role is granted explicitly: `ROLE_ADMIN` never implies `ROLE_EDITOR`, and an account holding only
the former would fail every `site-role-editor` gated action. When you (the producer) deploy a client's site,
run `site:create` yourself to become its super-admin, then create the client's own users with plain
`ROLE_ADMIN` via the User CRUD — they get full access to pages, menus, general configs, etc., but
the `backup` config group stays out of their reach. A standalone install where you're the only user
is never affected, since your own bootstrap account already holds both roles.

To make your own bundle's configs restricted the same way, just add `"restricted": true` next to
`"sensitive"` in its `configs.json` entry. `site-role-admin` and `user-roles-available` are
restricted too: they gate the whole admin and decide which roles exist, so a plain `ROLE_ADMIN`
must never be able to touch them.

That last point matters for the role picker itself: `ROLE_SUPER_ADMIN` is decided by
`UserCrudController`, not read from the config. It's stripped from whatever `user-roles-available`
holds — which no longer declares it at all, it being the owner's role, granted once by
`c975l:site:create` — and put back, first in the list, only for an acting user who already holds it.
Server-side, not just visually: out of the choices means out of the submitted form's allowed values
too, so Symfony's `ChoiceType` rejects a crafted submission trying to sneak it in anyway. Without
this, a site that listed `ROLE_SUPER_ADMIN` in `user-roles-available` would let any `ROLE_ADMIN`
grant it to themselves through the User CRUD and bypass every restricted config in one step.

The reverse move is blocked too. `UserManagementVoter` (handed to EasyAdmin as the entity permission,
so it's evaluated per row: on the index, where an inaccessible row keeps its place minus its actions,
and again before the edit/delete page is built at all) keeps a plain `ROLE_ADMIN` off a super admin's
account entirely — not just off their roles, but off their email, their password reset and their
deletion. `ROLE_SUPER_ADMIN` is granted before the `site-role-admin` check, not after: with no
`role_hierarchy` shipped it doesn't imply that role on its own, and an account holding only the
highest role would otherwise be refused every row of the very screen that could grant it the one it's
missing. On top of that, the `roles` field itself is rendered disabled whenever a lesser admin opens a
super admin's record — Symfony's `ChoiceType` *displays* a value missing from the choices by silently
dropping it (where it rejects it on submit), so saving the record would otherwise have posted a set
without `ROLE_SUPER_ADMIN` and demoted them, with neither of them seeing it.

### Disabling registration

Registration/reset-password-request are plain `c975L\UiBundle\Entity\Form` rows ("register"/"reset_password_request"), processed by UiBundle's generic `c975L\UiBundle\Controller\FormController` exactly like "contact" - no dedicated `RegistrationController`/`ResetPasswordController` action builds or displays them anymore. To turn registration off without a deployment, uncheck the "register" Form's `enabled` field from the admin's Forms screen (or toggle it from the dashboard shortcut) - `FormController` then shows a generic "not available" notice instead of the form, on both the standalone `/form/register` route and the "form" Block wherever it's embedded.

The dashboard shortcut and the `registration` section of the [status report](#status-report--letting-another-system-read-what-this-site-runs) find that row by its **action** (`UserFormSeeder::REGISTER_ACTION`), not by its name: the name is editable from the Forms screen, and a site renaming it to "Inscription" would otherwise take the tile away with it while the site went on registering people.

### Registration anti-spam protections

Registration/reset-password-request reject bots at several layers, so a public form doesn't turn into a way to farm confirmation emails towards throwaway domains. Their fields ("email"/"plainPassword"/"cgu" for registration, "email" for the reset request) are the `register`/`reset_password_request` rows of `c975L\UiBundle\Entity\Form` (`site_form`/`site_form_field`) - the same mechanism as "contact", editable (label/placeholder/order) from the admin's Forms screen, with `type` and deletion locked on these core fields - built into a plain Symfony form by `c975L\UiBundle\Form\FormSubmissionType`, which is what actually adds the protections below. Processing itself (password hashing, verification email, reset token) is scaffold's `App\Service\RegisterFormAction`/`ResetPasswordRequestFormAction` (a `c975L\UiBundle\Contract\FormActionInterface` each, auto-registered - see UiBundle's own README for the mechanism), not a controller.

- **`Assert\Email` + `c975L\UiBundle\Validator\Constraints\DnsEmail`** on every email-typed field — format check, then a live MX/A DNS lookup (via `egulias/email-validator`) rejecting domains that can't receive mail at all (e.g. `something@dominatingkeywords.com`). Applies to any generic Form's email field (contact/register/reset-password-request alike), plus `User::$email` itself on every entity validation (including the User CRUD in the backoffice, which still carries its own `#[DnsEmail]`).
- **Honeypot + minimum submit delay** — an invisible rotating-name field (hidden inline, no CSS dependency), and a minimum delay between displaying the form and submitting it, tracked in session. Either one failing silently redirects back (same "form_submitted" flash as a real submission) without creating an account or sending any email, giving no signal back to the bot. The delay is the shared `site-form-delay` ConfigBundle key (seconds, default `3`) - one setting for every public form (contact, register, reset-password-request) instead of one per bundle.
- **GDPR consent checkbox** - shown on both forms (unmapped `gdpr` field, using the bundle's own `text.gdpr` translation) when the shared `site-form-gdpr` ConfigBundle key (bool, default `true`) is enabled. The registration form also carries a `cgu` field (terms-of-use acceptance), enforced the same way.
- **Duplicate email** - `RegisterFormAction` silently succeeds (same flash, no account created, no email sent) when the submitted email already has an account, same non-revealing stance `ResetPasswordRequestFormAction` already has for "no such account".
- **Rate limiting by IP** — shared with every other generic Form (`limiter.ui_form`), not a dedicated `registration`/`reset_password` limiter anymore. UiBundle prepends it itself (`sliding_window`, 5 attempts per 10 minutes), so there is nothing to add for it to apply; write this to decide otherwise:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        ui_form:
            policy: sliding_window
            limit: 5
            interval: '10 minutes'
```

Your own config is merged over the prepended one, so it is what applies. Should a site strip `symfony/rate-limiter` anyway, `c975L\UiBundle\Service\RateLimiterGuard` fails open rather than erroring.

### Login throttling

`c975l:site:create` also inserts `login_throttling: { max_attempts: 5 }` onto the `main` firewall in `config/packages/security.yaml` (same step as `user_checker` above), using Symfony's built-in rate limiter for `/login` - no custom code involved. If your site predates this or uses a differently-named firewall, add it yourself:

```yaml
security:
    firewalls:
        main:
            login_throttling:
                max_attempts: 5
```

`LoginRequestSubscriber` sits in front of that, needing no configuration: a POST to the `app_login` route carrying no usable `_username` is sent straight back to the form. Scanners post to `/login` with none of the expected fields, and Symfony's `FormLoginAuthenticator` answers that with a `BadRequestHttpException` — a legitimate 400, but one the kernel logs at `ERROR` level, so a few bots a night are enough to bury the real errors of a production log. Nothing is let through: such a request could never authenticate anyone, it just gets the redirect a failed login would have gotten anyway. A site whose login route is named otherwise than `app_login` (the scaffold's own name) simply never sees the subscriber act.

### Back-office access control

`c975l:site:create` also declares `- { path: ^/management, roles: IS_AUTHENTICATED_FULLY }` under `access_control` in `config/packages/security.yaml` (same step again), so an anonymous visitor gets the login form instead of a bare 403. On the skeleton's `lazy: true` firewall it also makes the token resolve up front, without which `c975l/config-bundle`'s dashboard runs before the firewall has restored it. `IS_AUTHENTICATED_FULLY` rather than a role, on purpose: which roles grant the back office is editable from the dashboard (`site-role-editor`, `site-role-admin`), so the screens check it themselves. If your site predates this, add it yourself:

```yaml
security:
    access_control:
        - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }
```

Standing in the back office at all is `BackOfficeAccessVoter::ACCESS` (`C975L_ACCESS_BACK_OFFICE`), the floor the dashboard, the "What's new" page and the guided-project panel are gated by — every other screen states its own bar on top, and every block of the dashboard filters itself by role (menus, links, alerts, shortcuts, guided projects), so what a user gets is the part of the back office that is theirs rather than a 403 on the way in. It grants on any of `site-role-editor`, `site-role-admin` or `ROLE_SUPER_ADMIN`, **held outright**: no `role_hierarchy` is shipped (same reason as `UserManagementVoter` above), so a plain `denyAccessUnlessGranted($configService->get('site-role-editor'))` would lock out an account holding only the admin role. Use the attribute — not one of the two config values — wherever a screen is open to the whole back office, EasyAdmin's `setPermission()` included, which goes through `isGranted()` just the same.

The essential-actions checklist is the exception that is not gated but simply not built: it walks the site's own setup screen by screen, an editor has the role for none of them, and an empty progress bar under its heading would be worse than no heading at all.

### Account activation (`isEnabled`)

`App\Entity\User::isEnabled` gates login independently from `isVerified`. `c975L\ConfigBundle\Service\EmailVerifier::handleEmailConfirmation` (a bundle service, called from the scaffolded `RegistrationController`) sets both `isVerified` and `isEnabled` to `true` once the user confirms their email — `c975l:site:create` does the same for the bootstrap admin account, since there's no email to confirm. Registration itself (hashing the password, persisting the user, sending the confirmation email) goes through the bundle's `UserRegistrar` service, called from the scaffolded `App\Service\RegisterFormAction` (see [Registration anti-spam protections](#registration-anti-spam-protections)); `PasswordResetter` is its equivalent for the reset-password flow.

The scaffold ships `App\Security\UserChecker`, which refuses login with Symfony's built-in `DisabledException` ("Account is disabled.", already translated in `security.*.xlf` for en/fr/es, and rendered for free by the scaffolded `login.html.twig`) as soon as `isEnabled` is `false` — before the password is even checked. `c975l:site:create` registers it on the `main` firewall automatically (step 1, right after the scaffold install), by inserting `user_checker: App\Security\UserChecker` into `config/packages/security.yaml` if it isn't already there. If your site predates this or uses a differently-named firewall, add it yourself:

```yaml
security:
    firewalls:
        main:
            user_checker: App\Security\UserChecker
```

This lets you disable a user from the backoffice (`isEnabled` isn't readonly, unlike `isVerified`) to lock them out without deleting their account — a verified user with `isEnabled = false` still can't log in.

### Being notified of new accounts

Every account created through `UserRegistrar` also sends a short plain-text notification to the site itself, so the owner knows their site is being signed up to without having to watch the User screen. It goes to the site-wide `email-to` address (the one every other c975L email already goes to, see UiBundle's `EmailService`) — there's no separate address to keep in sync — and it's written in `kernel.default_locale`, not in whatever language the visitor was browsing in.

Uncheck the `user-creation-notification` config (`bool`, default `true`, `email` group) to turn it off:

```bash
php bin/console c975l:config:set user-creation-notification false
```

It never gets in the way of the registration itself: notification off, `email-from`/`email-to` not seeded yet, mailer down — the account is created and the visitor gets their confirmation email regardless. Only accounts created by the registration flow are announced, not the bootstrap admin `c975l:config:user-create`/`c975l:site:create` build (you're at the console when it happens).

---

---

## Restricting configs to ROLE_SUPER_ADMIN

Some configs are secrets shared across the whole install rather than per-site application data —
a database backup user, a payment provider's live API key. Anyone with `site-role-admin` access
to the Config admin can normally see and edit every entry (encrypted `sensitive` values are masked
in the list but still revealed in clear on the edit page). Flagging an entry
`"restricted": true` in its `configs.json` takes it a step further: that config disappears
entirely — from the index list, the edit page, and every export (SQL/CSV/JSON) —
for anyone who isn't granted `ROLE_SUPER_ADMIN`, regardless of what `site-role-admin` is set to.

This is opt-in per entry (not per `group`), so a bundle only restricts the specific secrets that
need it, leaving the rest of its configs manageable by a regular site admin. `ROLE_SUPER_ADMIN` is
a plain Symfony role, not declared or granted by ConfigBundle itself — the consuming app (or a
bundle like `c975l/site-bundle`) decides who holds it.

## Adding an Export button to another bundle's CRUD controller

`c975L\ConfigBundle\Service\Export\TableExporter` is generic: give it a table name and an array
of associative rows (e.g. from `Connection::fetchAllAssociative()`), it returns a ready-to-serve
`Response` encoded as SQL, CSV, or JSON (via Symfony's Serializer — `CsvEncoder`/`JsonEncoder`
plus a custom `SqlEncoder`). Wire it into your own `AbstractCrudController` the same way
`ConfigCrudController` does:

```php
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\DBAL\Connection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;

class MyEntityCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
    ) {}

    public function configureActions(Actions $actions): Actions
    {
        $exportGroup = ActionGroup::new('export', 'Export', 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        return $actions->add(Crud::PAGE_INDEX, $exportGroup);
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        // Set 'primary_key' to enable ON DUPLICATE KEY UPDATE; omit it for a plain INSERT-only dump
        return $this->tableExporter->export(ExportFormat::Sql, 'my_table', $this->fetchRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        return $this->tableExporter->export(ExportFormat::Csv, 'my_table', $this->fetchRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        return $this->tableExporter->export(ExportFormat::Json, 'my_table', $this->fetchRows());
    }

    private function fetchRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `my_table`');
    }
}
```

`export()`'s 4th argument is an optional context array, forwarded to the encoder — only `SqlEncoder`
reads it:

| Key | Type | Effect |
| --- | --- | --- |
| `primary_key` | `string` | Unique column; adds `ON DUPLICATE KEY UPDATE` on every other column. Omit for a plain `INSERT INTO` per row. |
| `exclude_from_update` | `string[]` | Columns never rewritten by the `UPDATE` clause (e.g. an immutable `creation` date). |
| `insert_ignore_when` | `callable(array $row): bool` | When true for a row, emits `INSERT IGNORE INTO` instead of the upsert — see `ConfigCrudController::exportSql()` for the sensitive-value use case. |

## Contributing import providers from other bundles

`c975L\ConfigBundle\Service\Export\ContentExporter` is the counterpart to `TableExporter` above, for content that doesn't fit a flat table dump — nested structures (e.g. a Page with its Blocks) and real files (e.g. a Block's Media), shipped as a zip (`manifest.json` plus any referenced files) instead of a single SQL/CSV/JSON payload. `ConfigCrudController::exportContent()` is the reference caller, producing the **Sync** export mentioned above.

To accept that zip back on another environment, implement `ImportProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;

class MyImportProvider implements ImportProviderInterface
{
    // $kind is the string embedded in the export payload (see ContentExporter::export()), stable across dev/prod (e.g. "site_page")
    public function supportsImport(string $kind): bool
    {
        return 'my_entity' === $kind;
    }

    // $items are the payload's raw "items" array, one entry per exported entity. $filesDir is the directory the export's zip was extracted into — any 'file' reference inside $items is relative to it, null for a kind that never carries files. Match by a natural key (slug/name...), never a raw id: dev and prod ids never need to match. Returns ['created' => int, 'updated' => int]
    public function import(array $items, ?string $filesDir = null): array
    {
        // ...
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

Uploaded zips are accepted at the **Import content** dashboard link (`ROLE_SUPER_ADMIN` — it writes arbitrary content straight into the database, unlike the export side which stays at `site-role-admin`), which extracts the zip, reads `manifest.json`'s `kind`, and dispatches to whichever registered provider's `supportsImport()` matches it.

## Contributing export providers from other bundles

`ExportProviderInterface` is the export-side mirror of `ImportProviderInterface` above — same "kind" values, same natural-key philosophy. Implementing it makes your bundle's content part of the **Export sync (everything)** dashboard shortcut, without touching that shortcut's own code:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;

class MyExportProvider implements ExportProviderInterface
{
    // The string embedded in the export payload for this provider's items (see ContentExporter), stable across dev/prod (e.g. "my_entity")
    public function getKind(): string
    {
        return 'my_entity';
    }

    // Same shapes ContentExporter::export() expects: 'items' (JSON-able array, one entry per exported entity) and 'files' (archive-relative path => disk path, empty for a kind that never carries files)
    public function exportAll(): array
    {
        return ['items' => $this->fetchItems(), 'files' => []];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` below.

`SyncAllExporter` collects every registered `ExportProviderInterface` into a single zip (same `manifest.json`-plus-files shape as a single-kind **Sync** export, just with several `{kind, items}` blocks under `exports`) — a bundle that isn't installed simply doesn't contribute a section, no configuration needed on either side. On import, `ContentImportController` detects that multi-section shape automatically and dispatches each section to its own `ImportProviderInterface`, same as a single-kind zip.

## Contributing menu items from other bundles

Satellite bundles add entries to the `/management` dashboard by implementing `MenuProviderInterface` — no manual service tagging needed, `MenuProviderPass` auto-detects any class implementing it.

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\MyBundle\Controller\Management\MyCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.my_section',
            'translation_domain' => 'my_bundle',
        ];
    }

    public function getMenus(): array
    {
        return [
            'my_entity' => [
                'controller' => MyCrudController::class,
                'label' => 'label.my_entity',
                'translation_domain' => 'my_bundle',
                'icon' => 'fas fa-star',
            ],
        ];
    }

    // Links to plain routes (not EasyAdmin CRUD controllers); return [] if none
    public function getLinks(): array
    {
        return [];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**Section merging:** if several bundles declare the same `getMenuSection()` (identical `label` + `translation_domain`), their menus are merged under a single section header instead of being duplicated.

**Alphabetical ordering:** within a section, menu items are always sorted alphabetically by their translated label.

**Role:** each entry in `getMenus()` accepts an optional `'role'` key, defaulting to the `site-role-admin` value every entry used to be given. Set it to the bar the entry's own screen states — its CRUD's `setPermission(Action::INDEX, …)`, or the `denyAccessUnlessGranted()` of its `#[AdminRoute]` action — whenever that screen opens below the admin one: a media library or a redirects list an editor is meant to reach. Nothing here can read a CRUD's own `setPermission()`, so the entry has to say it. Too high and it goes missing from a sidebar its screen would have answered; too low and it leads that user to a 403, and the guided tour walks them to it — `OnboardingStepBuilder` skips a menu the current user lacks the `role` for exactly as it already skips a link.

**Advanced tier:** both `getMenuSection()` and each entry in `getMenus()` accept an optional `'tier' => 'advanced'` key (default `'essential'`). Items opting into it are pulled out of their section and collected into one collapsed "Advanced" submenu at the bottom of the sidebar, instead of staying under their own section header — set it on `getMenuSection()` to move every item of that provider's section, or on an individual entry in `getMenus()` to move just that one (its section keeps its other items at the top level). Several providers commonly share one section (e.g. Config/Site/UiBundle all merge into "management"), so an item's own `tier` never drags along another provider's items sharing that same section.

**Non-CRUD screens:** `controller` is usually a CRUD controller, whose index action the entry opens. It can also be a plain controller carrying an `#[AdminRoute]` method — for a screen belonging in a section next to the CRUD items it reads from (an overview of what a CRUD lists, say) rather than in the "Links" section below them. Name its action with a `index()` method, or point at another one explicitly:

```php
'overview' => [
    'controller' => SiteOverviewController::class,
    'action' => 'show',
    'label' => 'label.overview',
    'translation_domain' => 'my_bundle',
    'icon' => 'fas fa-list-check',
],
```

Anything outside the dashboard (a public page, another app) is a link, not a menu — see below.

**Links section:** `getLinks()` exposes links to plain routes (e.g. a public page), each entry shaped like:

```php
public function getLinks(): array
{
    return [
        'shop' => [
            'name' => 'shop_index',
            'label' => 'label.shop',
            'translation_domain' => 'shop',
            'icon' => 'fas fa-shop',
        ],
    ];
}
```

Links from every bundle are merged into a single "Links" section, sorted alphabetically. `name` is a route name resolved to its real URL through the app's own router (not EasyAdmin's dashboard routing, so it also works for a route outside the dashboard, e.g. a public page). Use `url` instead for a literal, already-absolute URL — it's used as-is, no route resolution at all, and takes precedence when both are set:

```php
'showcase' => [
    'url' => 'https://example.com/showcase',
    'label' => 'label.showcase',
    'translation_domain' => 'my_bundle',
    'icon' => 'fas fa-shapes',
],
```

A few more optional keys: `role` (e.g. `'ROLE_EDITOR'`) hides the link from users lacking it — omit it for links with no access restriction of their own; `target` (e.g. `'_blank'`) is for a link leaving the admin entirely — it gets an external-link glyph automatically, and (for a `name`-based link) resolves to a full absolute URL instead of a relative path; `pinned` (bool) sorts the link after every non-pinned one regardless of its label — ConfigBundle's own "Visit the site" link (using the `site-url`/`site-name` configs) uses it to always stay at the very bottom of the links section; `label_parameters` (array) is passed through to the translator alongside `label`, for a translated label embedding a runtime value (e.g. `['%name%' => $siteName]`) — omit it for a plain translation key with no placeholder, the usual case; `tier` (`'essential'`/`'advanced'`, default `'essential'`) moves the link into the same collapsed "Advanced" submenu as the advanced menu items above, instead of the "Links" section — that section is not rendered at all if every link opted into it.

**Guided tour:** any entry in `getMenus()`/`getLinks()` can add an optional `'description'` key — a one-line "what is this for" sentence, same `translation_domain` — to feed the `/management` dashboard's "Guided tour" button. It highlights every described item in turn with a short explanation, matched against the sidebar's own rendered link (see `OnboardingStepBuilder`), so there's nothing else to wire up. It's entirely optional and can be filled in bundle by bundle: an entry without a `description` is simply skipped, it never breaks anything.

## Contributing linkable routes for SiteBundle menus

SiteBundle lets site admins add navbar/footer menu items that link to an existing database `Page`, or to a route contributed by another bundle (e.g. ContactFormBundle's `/contact`). This interface lives here (not in SiteBundle) precisely so that bundles which don't depend on SiteBundle (ContactFormBundle, ShopBundle, BookBundle...) can still expose a route, by implementing `LinkableRouteProviderInterface` — no manual service tagging needed, `LinkableRouteProviderPass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above.

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;

class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    // Route name => ['label' => translation key, 'translation_domain' => domain]; return [] if none
    public function getLinkableRoutes(): array
    {
        return [
            'my_bundle_display' => [
                'label' => 'label.my_page',
                'translation_domain' => 'my_bundle',
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

Routes are checked live: if the contributing bundle is later removed (or its provider stops returning that route), any menu item pointing to it simply disappears from the rendered menu instead of producing a broken link.

### One entry per row

A route taking a parameter (`/galerie/{category}`) is not a single target but one per row of your own data. Such an entry keys itself on that row's **id** rather than on a route name, names the `route` to generate with the `params` to fill it, and carries the row's own title as a literal label — `translation_domain` at `false`, nothing to translate:

```php
$gallery = $this->translator->trans('label.gallery', [], 'gallery');

foreach ($this->categoryRepository->findAllOrdered() as $category) {
    $routes['gallery_category.' . $category->getId()] = [
        'label' => (string) $category->getTitle(),
        'translation_domain' => false,
        // Shown by the back office's target select alone
        'picker_label' => $gallery . ' - ' . $category->getTitle(),
        'route' => 'gallery_category',
        'params' => ['category' => (string) $category->getSlug()],
    ];
}
```

`picker_label` is optional and already translated. The select holds these entries among every page of the site, where a bare "Paysages" says nothing of what it is, while the rendered navbar item has to read the row's own title — hence the two. An entry without one is shown under its `label` in both places.

Keying on the id is what makes a renamed row keep its menu items: the slug and the title are both read again at each render, and the url is generated, never stored. Providers are only walked when the registry is actually read, so listing rows this way costs a query on the pages that render such a link, none on the others.

## Contributing importmap entries from other bundles

If your bundle ships its own Stimulus controller for the `/management` dashboard (or any other AssetMapper entry the consuming app needs in its `importmap.php`), implement `ImportmapProviderInterface` — no manual service tagging needed, same `TaggedInterfacePass` mechanism as `MenuProviderInterface` above.

The interface has two methods, mirroring `c975l/ui-bundle`'s own `BundleScriptAdminProviderInterface`/`BundleScriptProviderInterface` admin/non-admin split: `getAdminImportmapEntries()` for scripts loaded on the `/management` dashboard only, `getImportmapEntries()` for anything else (a front-end Stimulus controller, or any other AssetMapper dependency). Both end up in the same `importmap.php` — the split only matters to keep each entry's purpose explicit at the declaration site. Return `[]` from whichever one doesn't apply.

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\ImportmapProviderInterface;

class ImportmapProvider implements ImportmapProviderInterface
{
    // Import name => ['path' => string, 'entrypoint' => bool]. 'path' is relative to your bundle's own directory: ImportmapRegistry prefixes it with wherever that bundle sits under vendor/, so you never spell it out yourself
    public function getAdminImportmapEntries(): array
    {
        return [
            '@c975l/my-bundle/controllers-admin.js' => [
                'path' => 'assets/controllers-admin.js',
                'entrypoint' => true,
            ],
        ];
    }

    public function getImportmapEntries(): array
    {
        return [];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

`entrypoint` is optional, and belongs only on a file loaded as a `<script type="module">` of its own. Leave it out for a module another bundle imports **by name** — UiBundle's `@c975l/ui-bundle/pointer-sort.js` is the case in point. Such a module still needs its entry: a bare specifier the importmap doesn't resolve doesn't merely fail on its own, it takes down the whole module that imported it, and every Stimulus controller that module was going to register with it.

Entries contributed this way aren't written to `importmap.php` on their own — nothing hooks into Composer from inside a bundle. Wire the collecting command into each consuming app's `composer.json`, in the same `auto-scripts` block that already runs `importmap:install`:

```json
"auto-scripts": {
    "cache:clear": "symfony-cmd",
    "assets:install %PUBLIC_DIR%": "symfony-cmd",
    "importmap:install": "symfony-cmd",
    "c975l:config:check-importmap": "symfony-cmd"
}
```

`c975l:config:check-importmap` then runs on every `composer install`/`composer update`: it adds any entry contributed by an `ImportmapProviderInterface` that's missing from `importmap.php`, and never touches one that's already there (so a manually customized `path` survives). What makes a path survive is AssetMapper being able to serve it, not the file merely being on disk: an entry pointing outside the mapped paths — a c975L bundle symlinked into `vendor/` for development, whose repository path stays written once Composer puts the real package back — is repointed at what its provider resolves, and reported when no provider claims it. This is a one-time addition per app — after that, a new bundle (or a new provider in an existing one) picks up its `importmap.php` entry on the next `composer update` with no further action.

It also covers the **third-party packages the c975L bundles' own JS imports by bare specifier** — `@symfony/ux-chartjs`, imported by this bundle's `controllers-admin.js` for the health check trend chart, being the one that actually bites. That entry is normally written by the package's own Flex recipe, which doesn't always run; when it's missing, the browser can't resolve the specifier, the **whole module fails**, and every Stimulus controller it was going to register is silently lost — back-office block drag-and-drop and duplication included, with nothing but a console error to show for it. The command scans each installed c975L bundle's `assets/**/*.js`, and for any bare specifier with no entry it resolves the path from the package's own `assets/package.json` (`name` + `main`, the Symfony UX convention) and adds it as a non-entrypoint. A specifier it can't find under `vendor/` is reported instead of guessed at — install the package, or add the entry by hand.

## Contributing a sitemap from other bundles

If your bundle has public urls of its own (a book catalogue, a shop, a gallery…), implement `SitemapProviderInterface` — no manual service tagging needed, same `TaggedInterfacePass` mechanism as `MenuProviderInterface` above.

`SitemapWriter` then writes one `public/sitemap-<getSitemapName()>.xml` per provider **and** the `public/sitemap-index.xml` declaring them all, so a bundle never renders or writes a sitemap itself, and the consuming app has nothing to list by hand. It runs from the `c975l:sitemaps:create` command (schedule it, see `c975l/site-bundle`'s scheduler section) and from the "Create sitemaps" dashboard shortcut. Both the writer and the two Twig templates live here rather than in SiteBundle, so any combination of bundles gets its sitemaps and its index, SiteBundle installed or not.

> [!TIP]
> Implementing this interface also gets your urls **health-checked**, at no extra cost: `DeclaredUrlsHealthCheckPass` registers one health check provider per `SitemapProviderInterface`, under its own `urls-<getSitemapName()>` kind (see [Health check](#health-check)). Nothing else to implement, and each bundle's urls stay schedulable on their own. A sitemap whose urls already have a check of their own opts out by implementing `SelfCheckedSitemapProviderInterface` instead — SiteBundle's pages do, `content-quality` reporting them in more detail.

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\SitemapProviderInterface;

class MySitemapProvider implements SitemapProviderInterface
{
    // Gives public/sitemap-my-bundle.xml - keep it short and stable, it ends up in a public url
    public function getSitemapName(): string
    {
        return 'my-bundle';
    }

    public function getUrls(): array
    {
        return [[
            'loc' => 'https://example.com/my-thing/some-slug',
            'lastmod' => '2026-07-26',
            'changefreq' => 'monthly',
            'priority' => 8,
        ]];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

`priority` is an integer on the admin's own `0`-`10` scale (the same one as a page's priority), converted by `SitemapWriter` to the `0.0`-`1.0` the sitemap protocol accepts — so a provider never does that conversion itself. A value outside the scale is bounded, and a missing `lastmod`/`changefreq`/`priority` is defaulted (today, `weekly`, `5`), so an incomplete url degrades instead of producing an invalid sitemap. `getSitemapName()` has to be unique across every installed bundle: two providers sharing it would overwrite each other's file, so it throws a `LogicException` instead.

Return `[]` when there's nothing to declare (a bundle installed but with nothing published yet): no file is written and nothing is added to the index — an indexed empty `urlset` is just a crawl error, and any file left by a previous run is removed so nothing stale keeps being served. Same when `site-url` isn't configured, since a sitemap only accepts absolute urls: no provider can build one, so no index is written either.

Two more keys are accepted and ignored by the sitemap itself, `title` and `description`: they are what the site's `llms.txt` is built from, one section per provider — see [robots.txt, humans.txt and llms.txt](#robotstxt-humanstxt-and-llmstxt) below.

Point Google Search Console at `sitemap-index.xml` only, never at the sub-sitemaps — installing or removing a bundle then changes what's crawled with nothing to update on Google's side. Both templates are overridable: `@c975LConfig/sitemaps/sitemap.xml.twig` (a sub-sitemap, gets `urls`) and `@c975LConfig/sitemaps/sitemap-index.xml.twig` (the index, gets `sitemaps`).

## robots.txt, humans.txt and llms.txt

`c975l:seo:files:create` writes the three of them into `public/`, from the `seo` config group and from the urls the sitemap providers already declare — the "Create the SEO files" dashboard shortcut runs the same `SeoFilesWriter`. Schedule it right after `c975l:sitemaps:create`: `robots.txt` only declares the sitemap index once that command has really written one, a `Sitemap:` line pointing at a 404 being a Search Console error.

They are **generated static files, not routes**, for the same reason the sitemaps are: served by the web server, they keep answering `200` during a maintenance, where a controller-rendered `robots.txt` would `503` and stop the crawl of the whole site (see [Maintenance mode](#maintenance-mode)).

| Config | What it holds |
| --- | --- |
| `seo-robots-private` | Keeps the site out of search engines altogether. **Off by default** |
| `seo-robots-disallow` | Paths `robots.txt` forbids, a JSON array (e.g. `["/*.pdf$"]`). Empty, the whole site is crawlable |
| `seo-robots-block-ai` | Blocks the crawlers harvesting pages to train models. **On by default** |
| `seo-robots-ai-crawlers` | The list those are taken from, in config rather than in the template: it ages every few months, and a site updates it without waiting for a release |
| `seo-robots-extra` | Written as typed inside the `User-agent: *` group, a blank line closing no group in [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309#name-grouping-of-rules) — appended last, these lines would bind to the AI crawlers already blocked instead of to everyone. A private site, declaring nothing besides its own rule, leaves them out |
| `seo-humans-from` | The country in `humans.txt` |
| `seo-humans-thanks` | Its `THANKS` block, one credit per line |
| `seo-llms-summary` | The one-sentence summary quoted at the top of `llms.txt` |

All eight are `restricted`, so they stay invisible below `ROLE_SUPER_ADMIN` (see [Restricting configs to ROLE_SUPER_ADMIN](#restricting-configs-to-role_super_admin)). The rest of `humans.txt` comes from configs the site already fills — `site-name`, `site-author` (falling back on `site-director`), `site-contact-email` — plus the kernel's default locale, and a `Last update` that is simply the day the file was written: the one date nobody has to remember to bump.

`seo-robots-private` is the one setting that overrides all the others: a site that asked to stay out of search engines gets a `robots.txt` holding nothing but `User-agent: * / Disallow: /`, no `llms.txt` at all, and no `Sitemap:` line — paths, AI crawlers and a sitemap all describe a site meant to be indexed. It cannot be expressed by putting `/` in `seo-robots-disallow`: the file would then carry both `Allow: /` and `Disallow: /`, two rules of equal length, and [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309#name-the-allow-and-disallow-line) settles that tie in favour of the least restrictive one — the site would stay open. It also tells `SeoFilesHealthCheckProvider` that the global `Disallow: /` it reports as the worst misconfiguration there is was asked for, so the row turns `ok`; what it warns about on such a site is the opposite — a `robots.txt` still open, `c975l:seo:files:create` not having run since the config was set. None of this is a security measure: what must not be reached has to sit behind authentication, `robots.txt` being advisory and publicly readable.

`seo-robots-block-ai` is on by default, and blocks harvesting without blocking reading. The two are different crawlers: the ones in `seo-robots-ai-crawlers` fetch pages in bulk to train a model, and give the site nothing back; the answer engines that fetch a page to answer a question and cite it back (`Claude-User`, `OAI-SearchBot`, `PerplexityBot`, `ChatGPT-User`…) are never in that list — blocking them only costs visibility, and they are also what reads the `llms.txt` this bundle writes. The generated `robots.txt` names them in a comment under the blocked group, so the file says what it allows and not only what it blocks; a site that adds one of them to the blocked list by hand sees it drop out of that comment rather than be claimed as allowed. They are named rather than given a `User-agent:` group of their own, which would take them out of the `User-agent: *` rules and so out of `seo-robots-disallow`. Either way `robots.txt` is advisory: it is honoured by the operators listed, nothing enforces it.

**`llms.txt`** ([llmstxt.org](https://llmstxt.org)) is a curated Markdown index of the site, built from the optional `title`/`description` keys of `SitemapProviderInterface::getUrls()` — one `##` section per provider, named after its sitemap. A bundle opts in by filling those two keys for the urls that belong in such an index, and nothing else; an url with no title is skipped, and a provider whose urls have none contributes no section at all, which is what keeps this from becoming the sitemap in Markdown. With no section and no `seo-llms-summary`, no file is written and any file a previous run left is removed.

> [!NOTE]
> No major crawler officially consumes `llms.txt` today — Google has said publicly that it doesn't. It costs nothing and is well placed if the convention takes; don't count it as an SEO lever. `robots.txt` is the only one of the three with a real effect.

A site that hand-wrote one of these files before this existed doesn't lose it: a file not carrying the generated marker is moved to `existingFiles/public/<name>.old` before the first run replaces it, so its content is still there to copy into the configs. The three templates are overridable, like the sitemaps' — `@c975LConfig/seo/robots.txt.twig`, `@c975LConfig/seo/humans.txt.twig` and `@c975LConfig/seo/llms.txt.twig`, the last one being where a site renames the section headings or changes the technology colophon.

The three are added to the app's `.gitignore` by `c975l:scaffold:install`, being rewritten from this environment's own configs on every deployment. A site that used to commit them untracks them once with `git rm --cached public/robots.txt public/humans.txt public/llms.txt` — a `.gitignore` rule never untracks a file git already follows.

### Keeping the AI crawler list current

`seo-robots-ai-crawlers` is the one thing here that goes stale on its own: the community list gains a handful of names every few months while nothing in the site changes. So the `ai-crawlers` health check runs **monthly** against `seo-robots-ai-crawlers-source` ([ai.robots.txt](https://github.com/ai-robots-txt/ai.robots.txt) by default) and reports what appeared upstream that this site doesn't block — `c975l:seo:crawlers:update`, or the "Update the AI crawlers" dashboard tile, then merges it in. Set the source config to `none` to keep the list by hand: nothing is fetched at all then. Emptying it says the same until the next `c975l:config:load-all`, which writes the declared url back into any row left empty (see [seeded defaults](#seeded-defaults)) — the word is what makes the choice stick.

The merge is **additive** — a name this site added itself, or one upstream has since dropped, is never removed by an update it didn't ask for — and **never imports the answer engines** (`Claude-User`, `OAI-SearchBot`, `PerplexityBot`, `ChatGPT-User`, `Googlebot`…, see `AiCrawlerListUpdater::ANSWER_ENGINES`), which the upstream list carries alongside the harvesters. They are named in the command's own output rather than silently skipped, a site staying free to block one by hand.

> [!WARNING]
> Nothing here imports the upstream list unattended, and that is deliberate: it marks each crawler with a free-text `function` field — two dozen distinct wordings — so a new bot can't be sorted into "harvests to train" or "answers a question and cites you" by any rule that will keep working. Blocking a citation engine by mistake costs exactly the visibility this setup is trying to keep, which is why applying the diff stays a `ROLE_SUPER_ADMIN` decision and the health check only ever reports it.

## Contributing "What's new" entries from other bundles

The `/management` dashboard shows the 5 latest release notes merged from every c975L bundle, with a link to the full list at `/management/whatsnew`.

This is a marketing-style feed for non-developer back-office users, not a developer changelog (see `ChangeLog.md` for that) — there's no `version` or `bundle` field, and entries should read as user-facing benefits, not technical changes.

Declare your bundle's entries in a `config/whatsnew.json` file:

```json
[
    {
        "date": "2026-07-04",
        "description": [
            {
                "en": "Added a new XYZ block",
                "fr": "Ajout d'un nouveau bloc XYZ",
                "es": "Añadido un nuevo bloque XYZ"
            }
        ]
    }
]
```

Expose them via a `WhatsNewProvider` implementing `WhatsNewProviderInterface` — no manual service tagging needed, `WhatsNewProviderPass` auto-detects any class implementing it (same pattern as `MenuProviderInterface`):

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\WhatsNewJsonReader;
use c975L\ConfigBundle\Management\WhatsNewProviderInterface;

class WhatsNewProvider implements WhatsNewProviderInterface
{
    public function getEntries(): array
    {
        return WhatsNewJsonReader::read(\dirname(__DIR__, 2) . '/config/whatsnew.json');
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**UiBundle exception:** `UiBundle` cannot depend on `c975l/config-bundle` (the dependency already runs the other way, ConfigBundle → UiBundle), so it doesn't implement `WhatsNewProviderInterface`. It contributes entries through its own `WhatsNewRegistry` (same pattern as `ScriptAdminRegistry`) — see the UiBundle README for how to register entries there; `WhatsNewBuilder` merges them in automatically alongside every other bundle's entries.

## Contributing dashboard alerts from other bundles

The `/management` dashboard, and each CRUD's own index page, can show a severity-grouped alert list (danger/warning/info) pointing at whatever needs attention — e.g. configs missing a value.

Satellite bundles contribute alerts by implementing `AlertProviderInterface` — no manual service tagging needed, `AlertProviderPass` auto-detects any class implementing it (same pattern as `MenuProviderInterface`):

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;

class MyAlertProvider implements AlertProviderInterface
{
    public function getAlerts(): array
    {
        return [
            [
                'label' => 'My entity label',
                'description' => 'Why it needs attention',
                'severity' => Config::SEVERITY_WARNING,
                'url' => '/management/my-entity/edit/1',
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**Dashboard aggregation:** `AlertBuilder::getAlerts()` merges every provider's alerts and groups them by severity for the main `/management` dashboard.

**Restricting an alert:** add an optional `'role' => 'ROLE_SUPER_ADMIN'` to an entry and `AlertBuilder` drops it for anyone lacking that role, same key as a shortcut tile's. Use it when the configs behind the alert are themselves `restricted` (see [Restricting configs to ROLE_SUPER_ADMIN](#restricting-configs-to-role_super_admin)) — `BackupAlertProvider` does exactly that: an admin who can't even read the backup settings can do nothing about a backup that stopped running, so the alert would be noise on their dashboard rather than information. Omit the key for an alert every admin should act on.

**Own CRUD index:** a controller that only wants its own provider's alerts (not every bundle's) calls `AlertBuilder::groupBySeverity()` directly on that provider's flat list — see `ConfigCrudController` for an example. That path does no role filtering, the controller having already gated its own page.

**Rendering:** both cases are rendered with the shared `templates/management/_alerts.html.twig` partial, which expects a severity-grouped `alerts` array and a translated `title`.

## Contributing dashboard shortcuts from other bundles

The `/management` dashboard shows quick-action tiles (e.g. clearing a cache, toggling maintenance mode) contributed by any bundle, on one titled row per category.

Satellite bundles contribute shortcuts by implementing `ShortcutProviderInterface` — no manual service tagging needed, `ShortcutProviderPass` auto-detects any class implementing it (same pattern as `MenuProviderInterface`):

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\MyBundle\Controller\Management\MyShortcutController;
use Symfony\Contracts\Translation\TranslatorInterface;

class MyShortcutProvider implements ShortcutProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getShortcuts(): array
    {
        return [
            [
                'label' => $this->translator->trans('label.toggle_maintenance', [], 'my_bundle'),
                'icon' => 'fas fa-wrench',
                'route' => MyShortcutController::TOGGLE_MAINTENANCE_ROUTE,
                'active' => $this->isMaintenanceOn(),
                'role' => 'ROLE_SUPER_ADMIN',
                'category' => ShortcutProviderInterface::CATEGORY_TOGGLE,
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**Unlike menus/links, shortcuts trigger an action, not just navigation.** `route` must accept a `POST` request and validate its own CSRF token (`csrf_token(route)` is the token id used by the shared template) — see `ConfigShortcutController::clearCache()` for a one-shot reference implementation that clears the config cache.

**`active`:** says the thing the tile toggles is currently **on**, so clicking it turns that thing off — one-shot actions, and anything the tile does not toggle, always return `false`. See `MaintenanceShortcutController::toggle()` for a toggle reference implementation flipping the `site-maintenance` config used by `MaintenanceListener`, with `ConfigShortcutProvider::getShortcuts()` reading that same config to decide `active` and pick the right label ("Enable"/"Disable"). **The flag paints the tile**: an `active` tile wears `shortcut-tile-warning` (Bootstrap's subtle warning tokens, so it follows the admin theme), which is how an admin reads what the site currently has switched on — maintenance, registration, a bundle's test mode — without going through every label. A tile in "Enable" mode stays neutral, on purpose: nothing is on, there is nothing to notice.

**`role`:** optional — omit it for a shortcut with no access restriction of its own, set it (e.g. `'ROLE_SUPER_ADMIN'`) to hide the tile from users lacking it.

**`category`:** optional too — one of `ShortcutProviderInterface`'s `CATEGORY_EXPORT`/`CATEGORY_MAINTENANCE`/`CATEGORY_SITE`/`CATEGORY_TOGGLE` constants, or a custom `['label' => string, 'translation_domain' => string]` pair. Shortcuts sharing the same category (across bundles) are rendered on **one titled row of their own** — every export-related tile on the "Export" row, every on/off tile on the "Enable / Disable" one, whichever bundles they come from. Put a tile flipping something on or off in `CATEGORY_TOGGLE`: that row is what an admin scans to know the state of the site. Omit the key to fall into the generic "Other" category.

**`method`:** optional, `'POST'` by default. Set it to `'GET'` for the rare tile that opens a page instead of acting — it is then rendered as a plain link, with no form and no CSRF token, and its route must be a regular `GET` page. See `ConfigPruneController::index()`, the "Obsolete configs" listing, for the reference implementation. Anything that changes state stays `POST`.

**Rendering:** shortcuts are merged across every provider and grouped into categories by `ShortcutBuilder::getCategories()` — categories ordered by their translated label, tiles ordered by their own label inside each — then rendered with the shared `templates/management/_shortcuts.html.twig` partial, one `<h3>` plus one grid per category, each tile its own small `<form method="post">` (or an `<a>` for a `GET` one). A row whose tiles are all hidden by their `role` is not rendered at all, heading included.

## Contributing essential actions from other bundles

The `/management` dashboard shows an "Essential actions" checklist — not a one-time onboarding wizard, but a permanent quick-access entry point to the handful of settings every site needs, always linking straight to the relevant Config screen so a value can be reviewed or redone at any time.

Satellite bundles contribute their own actions by implementing `EssentialActionProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\EssentialActionProviderInterface;

class MyEssentialActionProvider implements EssentialActionProviderInterface
{
    public function getEssentialActions(): array
    {
        return [
            [
                'slug' => 'my-action',
                'label' => 'label.my_essential_action',
                'description' => 'description.my_essential_action',
                'translation_domain' => 'my_bundle',
                'url' => '/management/my-entity',
                'isDone' => $this->isConfigured(),
                'order' => 50,
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

`isDone` only drives the status icon (a checkmark once true) — the link itself is always shown, even once done. `order` decides the checklist's display order across every provider (low to high), unlike menus/alerts which sort alphabetically. `EssentialActionBuilder::getProgress()` (`{done, total}`) drives the panel's "X/Y configured" subtitle.

## Contributing guided projects from other bundles

The `/management` dashboard shows a "Guided projects" button next to the guided tour. Where the tour *shows* the back office, a project puts the user to work in it: a real task to carry out — create a page, add a block to it, put it in a menu — with a panel following them from screen to screen.

`ConfigGuidedProjectProvider` ships this bundle's own three, opening the order sequence the satellite bundles continue (SiteBundle picks up at 50, UiBundle at 90): **"Régler la configuration du site"** (find a setting, change it, and see what the dashboard makes of it), **"Lancer un bilan de santé"** (run it, read what is reported, know where to start) and **"Mettre le site en maintenance"** (rehearse the switch on a quiet day rather than discover it on the day it is needed).

A project is a **replayable exercise**, not a wizard to get through once. Nothing is derived from the site's own data, so a project is still worth following on a site already full of pages, and still worth replaying once done. Consequently it carries no `isDone`: nothing is ever detected server-side, the user says when a step is done. Whatever they create along the way stays on the site — deleting the practice page is their call.

Satellite bundles contribute their own projects by implementing `GuidedProjectProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;

class MyGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function getGuidedProjects(): array
    {
        return [
            [
                'slug' => 'creer-page',
                'label' => 'label.guided_project_creer_page',
                'description' => 'description.guided_project_creer_page',
                'translation_domain' => 'my_bundle',
                'order' => 10,
                'steps' => [
                    ['label' => 'label.step_open_pages', 'url' => '/management/page'],
                    ['label' => 'label.step_click_new', 'description' => 'description.step_click_new', 'highlight' => '.action-new'],
                    ['label' => 'label.step_done'],
                ],
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**`order`** decides the display order across every provider (low to high) — a deliberate sequence, the one the user is meant to follow, unlike menus/alerts which sort alphabetically. **`role`** is optional: a project needing a role the current user lacks is dropped, the screens it walks through being out of their reach anyway. **`slug`** must be unique across every bundle contributing projects.

**Steps** set either `url` or `highlight`, never both:

- **`url`** sends the user to another screen. The panel stores the next step before leaving, and picks the parcours back up there once the page has loaded — that store-then-navigate is the whole cross-page mechanism, there is no arrival to detect.
- **`highlight`** is a CSS selector pointing at what to look at on the screen already open. A selector matching nothing — EasyAdmin renamed a class on an upgrade, the user reached the step from elsewhere — costs the highlight and nothing else: the step still reads and the parcours still runs.

**Rendering:** the list lives in `templates/management/_guided_projects.html.twig`, but the panel driving a project is `assets/js/guided-project.js`, mounted on *every* admin page through EasyAdmin's own `Assets::addHtmlContentToBody()` (see `GuidedProjectMountBuilder`) — a project spans several screens, so the panel has to survive each page load, and this reaches all of them without overriding EasyAdmin's layout. It fetches the steps from `management_guided_project_steps` only while a parcours is running, so the mount element costs no request in normal use.

**Progress is stored in the browser**, in `localStorage`, never in the database — a replayable exercise isn't a record worth a table. It is scoped per user (see `GuidedProjectKeyGenerator`) so two admins sharing one browser profile don't share one parcours, through an HMAC of the user identifier rather than the identifier itself: a `localStorage` key outlives the session, and that identifier is usually an email. The dashboard says as much to the user — progress won't follow them to another computer.

## Contributing dashboard widgets from other bundles

Any bundle can render an arbitrary block on the `/management` dashboard (e.g. UiBundle's Donovan card) by implementing `DashboardWidgetProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\DashboardWidgetProviderInterface;

class MyDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public function getDashboardWidgets(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return [
            ['template' => '@MyBundle/management/_my_widget.html.twig', 'context' => ['foo' => 'bar']],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

The dashboard template only loops and includes each widget's own `template` with its own `context` — it never contains business logic about what a widget is. Return `[]` when there's nothing to show (e.g. an unconfigured feature) so it stays entirely absent rather than showing a disabled placeholder.

## Testing your contributions to the management interface

Every provider above names a target nothing else verifies: a menu entry names a CRUD controller class, a link, a shortcut, a guided step or a linkable route names a route. Rename that route or move that controller and everything still compiles — the failure only surfaces when an admin clicks the entry, and a broken sidebar link takes the whole back office down rather than just its own entry.

`ManagementTargetsTestCase` checks all of it, with no kernel and no database: route names are read off the `#[AdminRoute]`/`#[Route]` attributes your controllers already carry. Extend it in your bundle and hand it your providers:

```php
namespace c975L\MyBundle\Tests\Management;

use c975L\ConfigBundle\Test\ManagementTargetsTestCase;
use c975L\MyBundle\Management\MenuProvider;
use c975L\MyBundle\Management\MyGuidedProjectProvider;

class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            new MenuProvider($this->createStub(ConfigServiceInterface::class)),
            // A provider generating urls takes these two recorders, so the targets behind its urls can be read back
            new MyGuidedProjectProvider($this->adminUrlGenerator(), $this->urlGenerator()),
        ];
    }

    // ConfigBundle's own controllers are watched by default (every bundle links to its screens); add yours
    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller'];
    }
}
```

That's the whole file — the case then checks your menu entries, links, shortcuts, essential actions, guided steps and linkable routes, and refuses to pass on an empty list should a stub end up too tight to make a provider return anything.

It lives in `src/` rather than `tests/` because a bundle can't autoload another bundle's test files. If your bundle's `services.yaml` scans `src/` as a resource, exclude your `Tests` helpers the same way ConfigBundle excludes its `Test` folder — the scan reflects on every class it finds, and PHPUnit isn't installed in production.

## Maintenance mode

Setting the `site-maintenance` config to `true` — from the config list, or from the dashboard's own toggle tile — closes the site to its visitors: `MaintenanceListener` answers every public request with the `@c975LConfig/maintenance/index.html.twig` page. `/management` and `/login` stay reachable so an admin can always get back in and lift it, as does anyone already authenticated with the `site-role-admin` role, or holding the `site-maintenance-hash` token (`?t=…`, which opens a 6-hour session).

The dashboard toggle generates that token when it closes the site and the entry is still empty — an empty one grants nobody anything, so a site closed without it could only be visited by logging into the back-office. The dashboard then shows the ready-made url alongside the maintenance alert: hand it over to a client signing off on the work or to whoever has to see the site as its visitors will, without giving them an account.

That page is served with **HTTP 503** and a `Retry-After` header, which is what search engines expect from a temporary outage — a `200` would get the maintenance page indexed in place of the real ones, a `404`/`410` would drop them from the index, and a `noindex` on a 503 risks the same. `Retry-After` is deliberately short (one hour, whatever the real length of the outage): it's only a hint, so a crawler coming back too early just meets another 503 and applies its own backoff, whereas too long a delay keeps it away after the site is back up. A `Cache-Control: no-store` keeps any proxy or CDN from serving the maintenance page once it's over.

`robots.txt`, `humans.txt`, `llms.txt` and the sitemaps are static files under `public/` (see [robots.txt, humans.txt and llms.txt](#robotstxt-humanstxt-and-llmstxt)), served by the web server without going through the listener — they keep answering `200` during maintenance, which matters: a `robots.txt` answering 503 stops crawling on the whole site.

**Don't leave it on for more than a day or two.** Past that, search engines stop reading the 503 as temporary and start dropping the pages from their index. `MaintenanceAlertProvider` puts that on the dashboard: an `info` alert while the site is closed, turning to `danger` past two days, both dated from the moment the mode was switched on. For a closure that has to last, publishing a real home page answering `200` ("closed until…", contact details) keeps the site indexed where maintenance mode wouldn't.

## Redirects

A url that changed needs a redirect whether it was a page's or a product's, and the rows answer **before the router** — so they live here rather than in whichever bundle happens to serve the content.

`Entity\Redirect` (table `site_redirect`) carries `fromPath`, `toUrl`, `permanent` and `gone`; `EventSubscriber\RedirectSubscriber` resolves it on `kernel.request` at priority 33, just above `RouterListener`. Managed from *Management → Advanced → Redirects*, exported/imported through the **Export sync (everything)** shortcut and the **Import content** screen (matched by `fromPath`, its own unique constraint).

- **`gone`** answers `410 Gone` instead of redirecting — for content removed with no equivalent to send anyone to. Search engines drop a 410 far faster than the plain 404 the same url would otherwise return. `toUrl` is required on every other row, a conditional constraint on the entity rather than a form-level one.
- **`fromPath` accepts a trailing `*`**: `/apidoc/*` covers every url below it, however deep. An exact row always wins over a prefix covering it, and among prefixes the longest one wins — so `/apidoc/c975L/*` still beats a broader `/apidoc/*`. A convention resolved in `RedirectSubscriber`, not a SQL wildcard.
- **`toUrl` accepts one too, and that pairing is what renames a tree**: `/character/*` → `/personnages/*` carries the tail over, sending `/character/tuor` to `/personnages/tuor`. A destination *without* the `*` keeps folding the whole tree onto that single url, which is what a tree removed rather than renamed needs — both are wanted, and the `*` is what tells them apart. So a renamed url tree is a handful of rows edited in the back office, not a redirecting route per old url deployed with the code. A `*` on the destination of an exact row means nothing and is left alone.
- **A path the web server answers itself is refused**: `fromPath` rejects anything under `/assets` or `/bundles` carrying a file extension (`Redirect::STATIC_PATH_PATTERN`), and `RedirectSubscriber` returns on those without querying at all - a missing asset would otherwise be the one thing turning a 404 into a database connection, and a page full of stale image urls into a burst of them. Uploads under `/medias` are deliberately left out: a removed file there is a url someone did publish, and stays redirectable.
- **The site root is left alone** by design.

`RedirectChainHealthCheckProvider` walks the rows for chains and loops, from the database alone.

## Url metadata — what a listing says of itself

A book, a product, a photo, a page each states its own title and summary from its columns. The urls **no entity carries** — a listing, a filtered listing, a tool page — had nowhere to state theirs, so a search result or a shared link showed the url and nothing more, unless the site wrote the sentences into its templates.

`Entity\UrlMetadata` (table `site_url_metadata`) holds them: `title`, `summarySocialNetwork` and `ogImage`, the same three names a `Page` already carries. Managed from *Management → Social → Descriptions d'urls*, exported/imported through the **Export sync (everything)** shortcut and the **Import content** screen (matched by `path`, its share image travelling in the archive beside it).

- **Keyed by the path, not by the route name.** `/caste/{caste}` is one route and twelve listings with twelve different things to say, so each of them gets its own row — same shape, and same reason, as `Redirect::$fromPath`. Paths are normalised on the way in and on lookup (`/animaux/` and `animaux` both being `/animaux`).
- **A row only ever fills a silence.** Both layouts (`@c975LUi/layout.html.twig` and SiteBundle's) read it last, for whatever the rendering template left unset — an entity always speaks first.
- **Every field is nullable.** A site describes its listings as it writes them, and an url with no row emits exactly what it emitted before.
- **The table is created by the app**, like `site_redirect` (`doctrine:migrations:diff` then `migrate`). A site that updates without migrating keeps its pages: the rows resolve to nothing rather than failing.

In a template needing the text itself, `url_metadata()` hands back the row of the page being rendered, or of the path given to it — for a template serving several urls where only one is described:

```twig
{{ url_metadata().title }}
{{ url_metadata(path('my_listing', {caste: 'guerrier'})).summarySocialNetwork }}
```

Nothing is ever typed by hand there: the rows come from what the bundles declare, so `Action::NEW` is disabled and the path is shown read-only.

The edit screen carries a note on the cache a network keeps of a page's preview from the first share on, with a link opening Facebook's debugger on that very url — an image chosen afterwards only ever shows up after a re-scrape. `templates/management/_sharing_debugger.html.twig` is that note, taking the `url` to check as a path, so any other screen deciding a share image can include it:

```twig
{{ include('@c975LConfig/management/_sharing_debugger.html.twig', {url: entity.instance.path}) }}
```

### Contributing urls to describe from other bundles

Implement `UrlMetadataProviderInterface` — no manual service tagging needed, same `TaggedInterfacePass` mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;

class MyUrlMetadataProvider implements UrlMetadataProviderInterface
{
    // Paths absolute from the site root, one per page and not per route
    public function getUrlMetadataPaths(): array
    {
        return ['/animaux', '/caste/guerrier', '/caste/mage'];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

What a provider declares is **which urls exist, never what they say**: the paths are structure and live in the code, the sentences are content and live in the database. Only urls no entity carries belong here — for the others, the entity answers and a row would never be read.

`c975l:url-metadata:sync` turns those declarations into empty rows waiting to be described — run it at deployment, beside `c975l:sitemaps:create`:

```bash
php bin/console c975l:url-metadata:sync
```

It only ever **creates**: a row already written is left untouched, and a row whose url no longer appears in any declaration is reported rather than deleted — an url can leave a listing for one release and come back, and the sentence written for it is work no synchronisation may throw away. Remove those from the screen, by hand, once they are gone for good. Two bundles declaring the same url (one serving the listing, the other linking to it) produce a single row.

---

## Health check

`/management/health-check` gives a technical health snapshot of the site — TLS certificate, security headers, server misconfiguration, `robots.txt`/sitemaps, redirect chains, deployment, and the content quality (title, meta description, `<h1>`, `alt` text, share tags, canonical url, `noindex`, broken links) of every url any installed bundle declares — without needing Node/Lighthouse-CLI or any other JS tooling: everything runs server-side over plain HTTP calls. `c975l/site-bundle` adds six page-level providers on top (Lighthouse scores, W3C markup validation, mixed content), see its own README.

This bundle's own providers:

| Provider | `getKind()` | Checks |
| --- | --- | --- |
| `SslCertificateHealthCheckProvider` | `ssl-certificate` | TLS certificate expiry (warns at 30 days left, errors at 7) — one check for the whole site, the certificate being issued for the host. Recorded under the site root as `SiteUrlResolver::siteRoot()` spells it, so it shares a dashboard row with anything else checking that url. Skipped if `site-url` isn't `https://` |
| `SecurityHeadersHealthCheckProvider` | `security-headers` | HSTS, CSP (or its `frame-ancestors` in place of X-Frame-Options), X-Content-Type-Options, Referrer-Policy, Permissions-Policy, wildcard CORS — reimplemented directly (securityheaders.com has no public API for automated use). Set once for the whole site, so only the site root is fetched |
| `SecurityMisconfigurationHealthCheckProvider` | `security-misconfig` | What a deployed site hands to an anonymous visitor: `/_profiler` and `/_wdt` reachable, the profiler's `X-Debug-Token` left on the response, `/.env`, `/composer.json`, `/composer.lock` and `/.git/config` actually served, directory listings on `/vendor/` and `/var/`, a session cookie missing `Secure`/`HttpOnly`/`SameSite`, and the `X-Powered-By`/`Server` banners |
| `SeoFilesHealthCheckProvider` | `seo-files` | `robots.txt`, `humans.txt` and `sitemap-site.xml` reachable, well-formed, not empty, not stale, `robots.txt` not accidentally blocking every crawler, and `llms.txt` listing something when it is deployed at all |
| `AiCrawlersHealthCheckProvider` | `ai-crawlers` | Monthly, and only on a site blocking them: the AI crawlers that appeared in the community list since `seo-robots-ai-crawlers` was last updated |
| `RedirectChainHealthCheckProvider` | `redirect-chains` | Chains and loops among your own `Redirect` rows, walked from the database alone (no HTTP call) |
| `SitemapRobotsHealthCheckProvider` | `sitemap-robots` | Every url the sitemap providers declare, tested against the `robots.txt` actually deployed — the contradiction of declaring an url to search engines and forbidding it to them in the same breath |
| `DeploymentHealthCheckProvider` | `deployment` | http→https redirect, that an unknown url actually answers 404, and that the other spelling of the host (`www` vs apex) either serves nothing or redirects here — including the case where it resolves and refuses the connection, its certificate not covering it |
| `DeclaredUrlsHealthCheckProvider` | `urls-<bundle>` | The content-quality checks over the urls each bundle declares for its sitemap — one kind per bundle, each schedulable at its own cadence |
| `DatabaseLoadHealthCheckProvider` | `database-load` | Table sizes and row counts against the host's own limits |
| `IntrusionHealthCheckProvider` | `intrusion` | Weekly, three rows: an executable file (`.php`, `.phtml`, `.sh`, `.htaccess`… in the name, not only at its end) under any directory a bundle declared for the backup, the working tree against the repository it was deployed from, and the number of accounts holding `site-role-admin` against the count the previous run recorded |
| `BackupHealthCheckAdviceProvider` | — | Advice lines for the backup alerts |

**Where the OWASP checks stop**: `security-headers` and `security-misconfig` cover what only the deployed site can answer for — misconfiguration, exposed debug tooling, missing cookie flags. A vulnerable dependency (OWASP A06) is *not* among them, on purpose: it is written in `composer.lock`, which the CI reads long before a deployment. Add it to your site's workflow rather than waiting for a health check run to say a site already in production ships a known CVE:

```bash
composer audit --locked --abandoned=report
```

`--locked` is what makes it answer for the versions actually deployed, and `--abandoned=report` keeps a transitive package someone stopped maintaining from failing a deployment over something no CVE covers. This bundle runs the same check on itself, as the first entry of its `composer qa`.

**What `intrusion` looks at, and what it deliberately doesn't**: every other security check here answers "is this closed", which says nothing about whether someone already walked in. This one looks for traces instead, chosen for having no innocent explanation on a deployed site. The upload directories it walks are the ones bundles already declare for the backup (`BackupPathProviderInterface`) — the same list read for the opposite reason, those being everywhere the site writes what visitors and editors send it — so a bundle added later is covered without this provider knowing it exists. The working-tree row runs `git status --porcelain --untracked-files=no` and only ever reads: a site deployed by rsync or by hand, or one whose host disabled `exec()`, gets a `skipped` row saying so rather than a green one. Files the site generates must be gitignored for that row to stay quiet, which is what `c975l:scaffold:install` writes (`public/sitemap*`, the SEO files, the media directories). The accounts row keeps a **count**, never a list, and compares it to what the previous run recorded: a count that dropped is somebody doing their job, a count that rose without you creating an account is the row worth reading tonight. None of the three proves an intrusion on its own, and none is meant to — what they have in common is that a site nobody touched produces none of them.

`ContentQualityAnalyzer` is what does the content work behind `urls-<bundle>` **and** behind SiteBundle's own `content-quality`. It reports each offence with a link to the screen that fixes it whenever a `ContentOffenceLocatorInterface` recognizes the entry's source — SiteBundle registers one tracing a page's image or link back to the block holding it. Without any locator the offence is still reported, just unlinked.

Two of its checks answer for whether the url is in the results at all, before any of the others answer for how it reads there. **The canonical url** the page declares for itself is compared to the url that was checked: naming another one hands the whole page over to it, which is what a `site-url` spelled `www` where the sitemap declares the apex does to every page at once. **A `noindex`** (in `robots` or `googlebot`, `none` included) is only ever reported on an entry the caller marks `'indexable' => true` — the urls a bundle hands to search engines through its own sitemap, as `DeclaredUrlsHealthCheckProvider` does. A caller listing every page it holds leaves the key out, a page meant to stay out of the results carrying those directives on purpose.

**The two ways a site keeps crawlers out without meaning to** are what `sitemap-robots` and the host-variant half of `deployment` answer for, and neither shows anywhere on the site itself. The first is a `robots.txt` forbidding a path the sitemaps declare: `seo-files` only ever reports the blanket `Disallow: /`, since a scoped rule is a normal thing for a file to carry — it is only a defect *against the urls the site hands out*, which is why the two have to be read together. The second is subtler: the other spelling of the host resolving, and refusing the connection. A certificate issued for the apex alone doesn't cover its own `www` alias — adding the alias to a hosting panel doesn't reissue it — so `https://www.example.com/robots.txt` answers nothing, and an unreachable `robots.txt` is not read as "allowed": crawlers treat it as a blanket refusal and leave that whole host alone. Search Console, whose `sc-domain:` properties cover every spelling of the host, then reports "Blocked by robots.txt" on urls the site blocks nowhere. The row names what the certificate does cover, the fix being to reissue it for both spellings.

**Reading the table**: the page lists one row per url *and* per kind, its rows grouped by url. The row opening each group carries that page's name and a heavier top border, separating one page from the next. Status is read off each row's own pill and nowhere else — a group used to be tinted with its worst status, which contradicted the pill sitting on that very row, and read as plain wrong once a sort had scattered a page's rows across the table.

**Refreshing results**: `php bin/console c975l:health-check:run` runs every registered provider and appends their results (never triggers a live check from a page load). Two options narrow a run, and they combine:

```bash
php bin/console c975l:health-check:run                                    # every provider
php bin/console c975l:health-check:run --frequency=weekly                 # every provider of that cadence
php bin/console c975l:health-check:run --kind=pagespeed --kind=w3c        # only these two
```

`--frequency` is **what a cron entry should ask for**: each provider declares its own cadence (see [Scheduling a provider](#scheduling-a-provider) below), so installing or removing a bundle never means editing a schedule. `--kind` pins a run to named providers, which is handy from the command line but brittle in a cron entry — a kind no installed bundle provides is skipped, and the command now says so rather than reporting a silent success.

**Run it at the end of a deployment**, as its last step, after the assets are compiled and the cache is warmed:

```bash
nohup php bin/console c975l:health-check:run --frequency=weekly --env=prod > var/log/health-check.log 2>&1 &
```

`nohup` and the trailing `&` matter over ssh: the run calls the validators once per page and takes minutes, which no deploy script should wait for, and a plain `&` job is killed with the session that spawned it.

`--frequency=weekly` rather than no filter at all: a provider declaring itself monthly did so because it is heavy — GalleryBundle holds one url per photo — and a deployment is no reason to make it heavy more often. Everything a deployment actually invalidates (markup, stylesheets, headers, redirects) is weekly by default, so the filter costs nothing here and keeps a push to `main` from validating a few thousand photo pages.

A deployment is the one moment the site's markup, stylesheets and headers all change at once, and it's rare — which is exactly what makes it worth a full run. Between two deployments the weekly cadence is enough. Saving a page is *not* such a moment: composing a page means saving it a dozen times to see the rendering, and each save would queue a run for a state nobody has finished editing.

Left to the cadence alone, a page shows the verdict of a run up to a week old, and the "full report" link next to it revalidates *live* — so a defect fixed this morning reads as the validator contradicting the dashboard. `lastCheckedAt`, above the table, is what tells the two apart, and `c975l/site-bundle`'s advice lines list the validator's own messages **as that run recorded them** rather than as they are now.

There's also a **"Run health check now"** button directly on the page. It doesn't run the check in your request: it dispatches one `RunCommandMessage` per registered kind (`c975l:health-check:run --kind=…`, the very command above) and returns immediately. A single provider can hold thousands of urls — a gallery declares one per photo — and a run that times out mid-way persists nothing at all.

This needs `RunCommandMessage` routed to an asynchronous transport, and a worker consuming it:

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

A queued run shows a **progress banner** ("3/12 check(s) done") that reloads the page as each job records its results, so the tables fill in under it and the banner disappears on its own once the run is over — the alternative being a page that states the results are coming and never moves again, with no way of telling a run still going from a worker that was never started. Progress is read off the recorded rows themselves (`HealthCheckRunProgress`, polling `/management/health-check/progress` every 5 seconds), the rows being the only thing the web request and the worker share, and the queued run is kept in the session, so it's followed by the admin who started it and by no one else. A run whose remaining kinds have recorded nothing after 15 minutes is given up on, the banner then saying to check the worker: a provider with no url to check at all (a gallery with no photo yet) records nothing to be counted, and would otherwise be waited on forever.

If it isn't routed, Messenger handles the message synchronously — the button then behaves as it did before, blocking the request, and the page comes back with every result already in: there is nothing left for the banner to wait on, so none is shown.

`HealthCheckAlertProvider` raises what needs attention (errors, then warnings, with the date of the last run) on the dashboard and on this page.

**History, not just a snapshot**: every run appends new `HealthCheckResult` rows rather than overwriting — the page itself only shows the latest one per (url, kind), but the full history feeds a trend chart (ok/warning/error counts over time, via `symfony/ux-chartjs` — a regular Composer dependency, Flex wires it up automatically) and an **Export (CSV)** button producing a dated snapshot, useful as an audit-trail artefact (e.g. accessibility declarations).

**Retention**: each health check run starts by purging the rows older than `site-health-check-retention-days` (90 by default, `0` keeping everything), the **latest row of each (url, kind) always surviving** whatever its age — otherwise a check that hasn't run in a while would lose the very line the dashboard shows for it. The table was written as pure history on the assumption that weekly and monthly runs stay a modest row count for years, which stopped holding once `BackupResultRecorder` started appending a row carrying a full `details` payload every six hours: some 1 500 rows a year, and every SQL dump carries them. Purging from the health check run rather than from the backup means it happens on the scheduled weekly `c975l:health-check:run` and on demand, and it runs *before* the providers do, so an install with no provider at all — the one still collecting those backup rows — is purged too.

The table itself can be sorted (click a column) and filtered (free-text search, status, kind) client-side — hand-rolled (`assets/js/health-check-table.js`), no DataTables/jQuery dependency.

The page also shows the same dashboard-wide alerts as `/management` (e.g. a health check provider's own missing API key, flagged via its config's `severity`), so anything blocking a full check is visible without leaving the page.

### Database load

Every other check looks at the site from the outside, over HTTP. `DatabaseLoadHealthCheckProvider` (kind `database-load`, one site-wide row) instead reads the database server's own counters — `SHOW GLOBAL STATUS` — and watches **transactions**, not queries: a page firing an n+1 is already caught by [dev profile](#dev-profile--automating-what-the-dev-toolbar-shows), while transactions opened around nothing at all are invisible everywhere. They cost no page a millisecond anyone notices, and they are what saturates a database server first, long before its CPU.

It takes two readings, five seconds apart, and subtracts the counters the previous run stored in its own row, which gives two numbers the row shows side by side:

- **the average since the last run** — the load over the days separating them, traffic included
- **the rate during the run itself** — measured over those five seconds, at 4am when the [weekly cron](#scheduling-a-provider) is what triggered it

The second is the one that answers the question a "transactions per HTTP request" ratio gets wrong: a transaction opened by a Messenger worker polling a Doctrine transport belongs to no request at all, and costs exactly as much at 4am as at noon. When the instant rate holds at 70% or more of the average, the advice under the row says so — the load is a background process, not the visitors, and the fix is that process's polling interval rather than any page's code.

The row warns only when transactions run at more than one per second *and* over half of them hold no write at all (`Com_commit` against the `Com_insert`/`update`/`delete`/`replace` family). That share is a floor, never an overstatement: writes made outside any transaction count against it. Slow queries, InnoDB lock waits and refused connections over the same window each get their own advice line when they're not zero.

Rows are skipped rather than failed when the site runs on another platform, when a managed host refuses the statement to the application user, or when the server restarted since the previous run and its counters went back to zero. The very first run has nothing to subtract and reports its five-second sample as a baseline.

## Backup

`c975l:config:backup` dumps the database table by table and archives what no rebuild can bring back, replacing the shell scripts this used to need. It lives here rather than in `c975l/site-bundle`, where it started: backing up is what every install needs whichever satellite bundles it happens to have, and none of ShopBundle, GalleryBundle, BookBundle or CrowdfundingBundle depends on SiteBundle — a shop-only or gallery-only install used to have no backup at all. The former name `c975l:site:backup` is kept as an alias, so schedulers and crontabs already deployed keep working.

```bash
php bin/console c975l:config:backup                  # dump + archive + send offsite, silent unless something fails
php bin/console c975l:config:backup --report         # same, plus a summary email of that run
php bin/console c975l:config:backup:offsite          # mirror the declared upload folders offsite
php bin/console c975l:config:backup:offsite --ack    # transfer nothing, record that an outside machine pulled
php bin/console c975l:config:backup:digest           # no backup: emails a digest of the last 7 days
php bin/console c975l:config:backup:digest --days=30 # any other window
php bin/console c975l:config:backup:digest --dry-run # print the digest, send nothing
```

Everything is configured through the `backup` config group (all `restricted`, see [Restricting configs to ROLE_SUPER_ADMIN](#restricting-configs-to-role_super_admin)): `site-backup-database`, `site-backup-db-host`/`-db-user`/`-db-password`, `site-backup-mailto`, `site-backup-retention-days`, `site-backup-max-age-hours`, and the three offsite entries covered [below](#the-offsite-copy). The emails also read `email-from` — declared here rather than in SiteBundle, an install having backups to report whichever satellite bundles it happens to have — and fall back to `site-backup-mailto` when it's empty, rather than failing to send at all.

### What is backed up, and what deliberately isn't

Three kinds of state, three treatments — and the third is the one that decides the shape of everything else.

| State | Treatment |
| --- | --- |
| Code, templates, asset sources | **Nothing.** It's in git and comes back with a clone plus `composer install` |
| Configuration, content | Covered by the database dump, `site_config` included |
| Files neither in git nor in the database | Declared through `BackupPathProviderInterface`, in one of two modes |

A `BackupPath` is declared in `archive` mode or in `mirror` mode:

- **`archive`** — small, and wanted with a history: it goes into the dated `FILES_-_…tar.bz2` of every run. `.env.local` is the case that matters. It's git-ignored *and* outside the database, so a server restored with every photo and no `APP_SECRET` doesn't start — the discovery nobody wants to make on the day of the incident.
- **`mirror`** — large and written once: uploads. Never tarred, never compressed, never dated. It is copied as-is by `c975l:config:backup:offsite`, because a photo doesn't need a version history, it needs a copy.

That second mode replaces what this command used to do: roll `public/` and `private/` whole into a monthly `tar.bz2`. On a media-heavy site that meant compressing nine gigabytes of JPEG for about one percent of gain, an hour of CPU against a one-hour timeout, and a copy of it all kept for the whole retention window — to produce an archive whose only use was to be extracted whole. It also made this bundle's business to know where every *other* bundle stores things, so a site with an unusual layout was backed up wrongly rather than differently.

ConfigBundle declares `.env.local` and nothing else — declaring `public/medias` from here would cover every other bundle's uploads, which is the habit this interface exists to break. Each bundle declares its own: UiBundle its `medias/site` and `medias/fonts` folders *and* the site-wide graphics (`favicon.ico`, `apple-touch-icon.png`, `og-image`, `logo`, the two watermarks), which `UiMediaNamer` deliberately writes at the root of `public/` under the role's own name — so no folder declaration ever reaches them, and the list is read off `Media`'s own roles rather than written out by hand. A path that isn't on disk is skipped rather than failing the run, and a folder already covered by a declared ancestor is dropped rather than mirrored twice:

```php
namespace c975L\ShopBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;

class ShopBackupPathProvider implements BackupPathProviderInterface
{
    public function getBackupPaths(): array
    {
        return [new BackupPath('private/invoices', BackupPath::MODE_MIRROR)];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered — the compiler pass does the rest, and nothing has to be listed anywhere else.

The c975L convention puts a bundle's uploads under `public/medias/<bundle>/`, and whatever the document root must never serve under `private/medias/<bundle>/` — the same substructure on both roots. Declare both: a path that isn't on disk is skipped, so a bundle can declare its private folder before it ever writes one, and the declaration is already in place the day it does. What must *not* be declared is anything derived — a folder of generated copies is rebuilt, not restored.

Each run also writes `var/backup/manifest.json`, naming the archives folder, the mirrored paths and the local retention window. Whoever copies this server offsite reads that file instead of knowing the layout, so a bundle installed tomorrow brings its folders along without a line changed on the machine doing the copying.

```json
{
    "site": "example.com",
    "generatedAt": "2026-08-07T06:07:03+02:00",
    "archives": "var/backup",
    "mirror": [
        "public/medias/site",
        "public/medias/fonts",
        "private/invoices"
    ],
    "retentionDays": 15
}
```

Every path is relative to the project directory, as everything else here is. `site` is the site's domain, falling back to the database name on an install that declares none. `retentionDays` is there so the puller knows how long the server keeps its own archives, and can keep a longer window rather than downloading again what production has just purged.

**Verifying rather than assuming**: every archive is read back and checked (`bzip2 --test`) before being counted, its size recorded, and the number of tables actually dumped compared against `INFORMATION_SCHEMA`. A table is reported only once its dump exists, with its size — a table listed in the report used to prove nothing about it having been saved. Anything discarded as empty is named in the report instead of vanishing silently.

**Retention on the server**: each run purges the dated `var/backup/YYYY/YYYY-MM/YYYY-MM-DD` folders older than `site-backup-retention-days` (15 by default, `0` keeping every archive). Whoever copies the archives offsite should keep a *longer* window than this one — otherwise the next copy downloads again what it has just purged locally. The point is that production always holds a rolling set of restorable archives: deleting them as soon as they were copied off left a gap where the only surviving copy was the offsite one.

### The offsite copy

A backup that never leaves the machine it protects is not a backup. Until now nothing here said whether anything had left, and "backup ok" read exactly the same either way. Two models are supported, and the bundle is deliberately agnostic between them.

**Push** — the site sends, through [rclone](https://rclone.org). Set `site-backup-offsite-target` to a remote as rclone spells it, `storagebox:975l.com`. Archives go to `<target>/backup` on every run; the mirrored folders go to `<target>/files/<path>` on the nightly `c975l:config:backup:offsite`.

**Pull** — an outside machine fetches the backups over SSH/SFTP and calls `c975l:config:backup:offsite --ack` afterwards. Leave `site-backup-offsite-target` empty: nothing is sent, no task fails, and the dashboard still knows the files left. This is the safer of the two — a server holding no credentials to its own backup is a server that can't destroy it — at the cost of a machine to keep. What that machine runs, nothing of it living on the site:

```bash
#!/bin/sh
SITE=site@example.com
ROOT=/home/site/example.com
HERE=/srv/backups/example.com

# The dated archives, kept here for longer than the site's own retention window
rsync -a "$SITE:$ROOT/var/backup/" "$HERE/archives/"

# The mirrored folders, read off the manifest rather than written out here: a bundle
# installed tomorrow brings its own along without this script changing
ssh "$SITE" "cat $ROOT/var/backup/manifest.json" | jq -r '.mirror[]' | while read -r path; do
    rsync -a "$SITE:$ROOT/$path/" "$HERE/files/$path/"
done

# Without this the dashboard reports a site that never backs up offsite
ssh "$SITE" "php $ROOT/bin/console c975l:config:backup:offsite --ack"
```

Run it after the site's own backup rather than at the same hour, and give the account pulling read-only access: the whole point of this model is that neither machine can destroy what the other holds.

**Where rclone's own configuration is read from**: `rclone.conf` at the root of the project if the install has one, rclone's default `~/.config/rclone/rclone.conf` otherwise. Prefer the first. Left to itself rclone resolves `HOME`, which works in an interactive SSH session and often doesn't under a task scheduler — it then starts with no remote configured and reports the target as unknown, a failure that reads exactly like "rclone doesn't work on this host". The root rather than `var/`: `var/` is a runtime scratch folder nobody writes to by hand, and the backup scripts that skip it would skip this file too — the one file whose loss stops the backups from leaving. The path is fixed in code, so no back-office entry can aim rclone at a file of someone else's choosing, and `c975l:scaffold:install` adds it to `.gitignore`.

```bash
rclone --config rclone.conf config create storagebox sftp \
    host=uXXXXXX.your-storagebox.de user=uXXXXXX port=22 \
    pass="$(rclone obscure 'the-sub-account-password')"
```

**Where the binary is looked up**: the `PATH` first, then the project's `bin/rclone`. A managed host with no rclone of its own usually gets a static binary dropped in the account's own `~/bin`, which an interactive SSH session finds and a task scheduler doesn't — so a scheduled command that reports `rclone was not found` while the same command works over SSH is a `PATH`, not an install. Either give the scheduled entry its own (`PATH=$HOME/bin:$PATH php bin/console …`) or put the binary in the project's `bin/`.

What the bundle never holds, in either model, is a **credential**. `site-backup-offsite-target` names a remote and nothing more; the secrets live in rclone's own configuration, outside the application and outside the database. The binary's location isn't configurable either — it's looked up in the `PATH` and in the project's `bin/`. A free-form command path read from a back-office entry is an arbitrary code execution offered to any admin account that gets compromised, and the target itself is validated against `remote:path` before it ever reaches a `Process`.

| Config | Default | What it does |
| --- | --- | --- |
| `site-backup-offsite-target` | *(empty)* | The rclone remote. Empty means "an outside machine pulls" |
| `site-backup-offsite-max-age-hours` | 30 | Past this without anything leaving, the run warns and the dashboard alerts |
| `site-backup-offsite-keep-days` | 15 | How long the destination keeps the previous version of an overwritten or deleted file |

**Not destructively.** The mirror runs `rclone sync`, so a destination that has drifted comes back in line — but `--backup-dir` moves what would be overwritten or deleted into a dated `previous/` folder instead of losing it, and `--max-delete` aborts the run outright past 100 deletions. The failure that actually happens is not exotic: a gallery emptied by mistake, or a hacked site, faithfully reproduced onto the backup within hours. Aborting costs a night's mirroring and a look from a human, which is the cheaper of the two.

**What that leaves at the destination**, one folder per site and nothing to know beyond it:

```text
<target>/
├── backup/                        the dated archives, copied by c975l:config:backup
│   ├── MYSQL_-_…_-_12-58_-_Tables.sql.tar.bz2
│   ├── FILES_-_…_-_12-58.tar.bz2
│   └── manifest.json
├── files/                         the mirrored folders, as they are right now
│   └── public/medias/…
└── previous/2026-08-05/           what that day's mirror overwrote or removed
    └── public/medias/…
```

The database has one file per run and keeps its own history in their names, so the last four hours and last week are both there. The mirrored folders have no dated copies on purpose — a version history of nine gigabytes of JPEG costs nine gigabytes a day — and `previous/<date>/` holds what changed instead, which is the question actually asked on the day it matters: *where is the file that was there yesterday*.

**Versioning is the destination's job, not this bundle's.** Where the destination takes its own snapshots, use them: on a Hetzner Storage Box they sit in a read-only ZFS directory that the server couldn't touch even if its credentials leaked — which no purge run from the server can claim. `site-backup-offsite-keep-days` is the portable fallback for destinations that offer nothing of the sort.

**The first run transfers everything.** On a media-heavy site that's hours, and the Symfony Scheduler has a single worker to block. Seed it once by hand from an SSH session; every run after that carries only the files added since, which is the whole point of mirroring content that never changes.

Verification follows the same rule as the archives: `rclone size` reads the destination back rather than trusting an exit code, and what it counts is what the dashboard row reports — so the volume kept out of the archives is a number on the row, not an omission nobody sees.

### Seeing that it actually ran

Every run — not only the one carrying `--report` — records a `HealthCheckResult` row of kind `backup`, so it shows up in the site-wide section of [the Health check page](#health-check), in its trend chart and in the CSV export, with no extra screen to maintain. Those same rows are what [the weekly digest](#the-weekly-digest) reads back. The row's summary carries the numbers a "backup ok" message never gives: tables dumped, archive sizes, duration.

`BackupAlertProvider` then reads that row live at every dashboard load and raises, for `ROLE_SUPER_ADMIN` only (its alerts carry `'role' => 'ROLE_SUPER_ADMIN'`, every config behind them being `restricted` too — an admin who can't read the backup settings can't act on them either):

| Situation | Severity |
| --- | --- |
| No backup recorded for longer than `site-backup-max-age-hours` (30 by default) | danger |
| Nothing has left the server for longer than `site-backup-offsite-max-age-hours`, or ever | warning |
| The last run failed | danger |
| The last run has warnings, or its SQL archive lost more than half its size since the previous run | warning |
| Backup configured but never run | warning |

The first line is the one no report email can ever cover: an email only exists when the command ran far enough to send one, so a dead scheduler consumer, a crontab lost on a server move or a PHP fatal mid-dump all produce the same signal — nothing at all — and a missing email is precisely what nobody notices. Staleness is checked whatever the last run's own status was, a backup that succeeded a fortnight ago being a worse problem than one that failed this morning.

The size-drop check compares against the previous run rather than a fixed threshold: what a healthy archive weighs is entirely site-specific, and a dump holding half of last week's is the failure mode no per-table error ever reports — every table having dumped "successfully" into a truncated result.

None of this proves a *restore* works. Only restoring does, and that stays a manual exercise worth doing on the offsite copy once in a while.

### Restoring

No command does this — a restore is deliberate, and a `c975l:config:restore` on a live site is a foot-gun nobody needs. The four sources come back in the order they depend on each other:

```bash
# 1. The code, which was never backed up because it never had to be
git clone git@github.com:you/example.com.git && cd example.com && composer install

# 2. The database, into an empty schema
mysql -e 'CREATE DATABASE example'
tar -xjf MYSQL_-_example_-_2026-08-07_-_12-58_-_Tables.sql.tar.bz2
cat example_-_*.sql | mysql example

# 3. What is neither in git nor in the database - .env.local first among them
tar -xjf FILES_-_example.com_-_2026-08-07_-_12-58.tar.bz2 -C /path/to/example.com

# 4. The uploads, from wherever they were mirrored
rclone copy storagebox:975l.com/files/public/medias public/medias
```

Three things about step 2. The archive holds **one `.sql` file per table**, not a single dump, which is what lets a run report each table's size and lets a single table be restored on its own. Each file disables foreign key checks around itself, so `cat *.sql` in alphabetical order is safe and no dependency ordering has to be worked out. And the dumps carry `CREATE TABLE` without `DROP TABLE` — restoring over a schema that still has its tables fails rather than half-overwriting them, hence the empty database.

Step 4 is a copy, not a sync: `rclone copy` never deletes at the destination, so a restore run against a folder that has already been partly repopulated adds to it instead of emptying it. Restore into a scratch database and a scratch folder when the point is to *test* the backup — which is the exercise the previous section says is the only proof, and it takes fifteen minutes.

### The weekly digest

The dashboard covers the site you happen to be looking at. `c975l:config:backup:digest` covers the week you didn't look at it, on every site at once: scheduled weekly, it mails what the last 7 days of `backup` rows say, one mail per site, its verdict in the subject line so a mailbox full of them is read without opening any:

```text
[OK] Backups over the last 7 days - example.com

Site example.com - last 7 days (since 22/07/2026 03:07)

28 run(s): 28 ok, 0 warning(s), 0 error(s)
Last run on 29/07/2026 06:07: 42 tables · SQL 18.4 MB · Files: 1 file(s), 2.1 KB · Offsite: 3h ago, mirror 48210 file(s), 8.4 GB · 12 s
SQL archive over the period: 17.9 MB -> 18.4 MB
Retention (15 days): 15 run(s) kept on the server, oldest 2026-07-14
```

It **runs no backup of its own** — it reads the rows back — and that's the whole point of it being a separate command rather than a bigger `--report`: `--report` rides on a backup run and only exists if that run reaches its last line, so a dead consumer, a lost crontab or a fatal mid-dump send nothing at all. The digest goes out either way, and when nothing ran it says exactly that (`[ALERT] No backup over the last 7 days`) and exits non-zero, so the scheduler's own logs carry the verdict too. On an install where `site-backup-database` is empty nothing is sent: a site that deliberately doesn't back up from here shouldn't get a weekly false alarm.

Beyond the counts, it reports what a single run can't see:

| Reported | Why a per-run report misses it |
| --- | --- |
| The longest stretch without a backup, once past `site-backup-max-age-hours` | A scheduler that stopped on Wednesday and restarted Friday leaves every row saying "ok" |
| The SQL archive at both ends of the window | The shrink warning only compares a run against the one before it, so a slow drift never trips it |
| Errors and warnings, deduplicated with their count | The same table failing every 6 hours is one problem, and listing it 28 times is how a report stops being read |

The stretch *before* the first run of the window is deliberately not counted: a site whose backups started three days ago hasn't skipped the four days before that.

### Scheduling it

`c975l:config:backup` is a plain command; schedule it with [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html) alongside `c975l:sitemaps:create`/`c975l:health-check:run`, or from a crontab. `c975l/site-bundle` ships a ready-made `MaintenanceSchedule` in its scaffold; on an install without it:

```php
->add($this->spreader->spread('# */6 * * *', new RunCommandMessage('c975l:config:backup')))
->add($this->spreader->spread('# #(1-3) * * *', new RunCommandMessage('c975l:config:backup:offsite')))
->add($this->spreader->spread('# #(2-5) * * 1', new RunCommandMessage('c975l:config:backup:digest')))
```

Keep `site-backup-max-age-hours` comfortably above the interval you pick, so an ordinary late run doesn't alert. The digest is scheduled on its own line rather than as `c975l:config:backup --report` on the Monday run, so the week's summary doesn't depend on that particular run getting through. The mirror runs nightly and on its own: uploads weigh far more than everything else here and are written once, so they have no business on the 6-hourly cadence.

The `#` are placeholders `ScheduleSpreader` draws from this install's own identity, so that two sites sharing a server don't dump their databases at the same minute — see below. Writing `'7 */6 * * *'` with a plain `RecurringMessage::cron()` still works, and is what to do when a command has to run at a fixed time.

## Messenger cleanup

Purges failed `messenger_messages` rows (`queue_name = 'failed'`) older than `site-messenger-cleanup-retention-days` days (default 30, `0` keeping them all — the same reading as the two other retention configs, and the only safe one here, a zero handed to the purge meaning "delete every failed message" rather than none). Each failure is classified minor (spam/blacklist-related, matched against the exception message) or important; new important failures since the last alert trigger a single digest email to `site-messenger-cleanup-mailto` (both configs `restricted`, the mailto also `sensitive`, same pattern as the backup mailto — see [ROLE_SUPER_ADMIN and restricted configs](#role_super_admin-and-restricted-configs)), never more than once per new batch.

A dashboard alert (ConfigBundle's `AlertProviderInterface`) also surfaces important failures — full detail (recipient, subject, error) to `ROLE_SUPER_ADMIN`, a plain "already reported" message to `ROLE_ADMIN` — linking to a management page listing them, with a "Purge now" button (`ROLE_SUPER_ADMIN` only) that runs the same cleanup immediately.

The whole stack lives here rather than in SiteBundle, where it started: any bundle queueing a message needs it, whether or not the app has a site foundation. `c975l:config:messenger-cleanup` is declared by `ConfigMaintenanceTaskProvider`, so it runs nightly without anything to add to a schedule.

Everything above reads and purges the `messenger_messages` table through Doctrine, so it works with no Messenger configuration at all. Only **replaying** a failed message goes through the transport itself: without a `framework.messenger.failure_transport: failed` (the Symfony recipe default), the "Retry" button reports the message as no longer there — nothing else changes, and the container still compiles.

**Exporting a set of tables.** `c975l:config:export-tables` dumps the data (no `CREATE TABLE`) of every table matching a prefix into one SQL file, meant to be replayed one-shot into an environment where the schema already exists — building content in dev then pushing it to prod after the migrations ran there. The file truncates each table and disables FK checks around the inserts, so it can be replayed as-is over existing data; `site_config` is always excluded, this bundle having its own non-destructive export. It uses the same DB credentials as `c975l:config:backup` (the `site-backup-db-*` keys), so it works even when the DB user your GUI tool uses lacks export privileges. The same dump is one click away as the **Export tables** dashboard shortcut, streamed back rather than written to `var/export`.

`--prefix` (default `site_`) and `site-backup-database` must both be plain identifiers — letters, digits and underscores only. Neither can be bound as a query parameter, and a `%` or `_` in the prefix would silently widen the list of tables the dump truncates on replay.

---

## Sessions cleanup

`c975l:config:sessions-cleanup` deletes the expired rows of the `sessions` table `PdoSessionHandler` writes to - the very `DELETE` the handler's own garbage collection runs, on a cadence instead of on a dice roll. That collection is probabilistic (`session.gc_probability`/`gc_divisor`) and only fires when PHP happens to call it, which on a managed host can be never: 14 331 rows had piled up on one site in ten days, 14 329 of them expired.

Declared by `ConfigMaintenanceTaskProvider`, so it runs nightly with nothing to add to a schedule. A site storing its sessions in files has no such table and the command says so rather than failing, and one that renamed the handler's table or its `sess_lifetime` column simply has nothing found here.

---

## Spreading scheduled commands across installs

Every install of these bundles schedules the same commands, from the same scaffolded lines. Written as fixed expressions, they all fire at the same minute — which is fine until several of those sites share a server, where a dozen simultaneous database dumps show up as a nightly memory spike no amount of web server tuning explains.

Symfony answers this with [hashed cron expressions](https://symfony.com/doc/current/scheduler.html): a `#` in an expression is replaced by a value drawn deterministically, rather than by a time you picked. But `RecurringMessage::cron()` draws it from the message alone, so every install computes the *same* minute for `c975l:config:backup` and they pile up exactly as before. `ScheduleSpreader` draws it from the site's own identity as well:

```php
use c975L\ConfigBundle\Scheduler\ScheduleSpreader;

public function __construct(
    private readonly ScheduleSpreader $spreader,
    private readonly CacheInterface $cache,
) {
}

public function getSchedule(): Schedule
{
    return (new Schedule())
        ->stateful($this->cache)
        ->add($this->spreader->spread('# #(0-2) * * *', new RunCommandMessage('c975l:sitemaps:create')))
        ->add($this->spreader->spread('# */6 * * *', new RunCommandMessage('c975l:config:backup')))
    ;
}
```

| Expression | Reads as |
| --- | --- |
| `# */6 * * *` | every 6 hours, at a minute of this site's own |
| `# #(0-2) * * *` | once a day, between midnight and 2 am |
| `# #(2-5) * * 1` | every Monday, between 2 and 5 am |
| `0 3 * * *` | 3 am sharp, spread by nothing — an expression without `#` is used as it stands |

The identity is `site-url`, falling back on the install path for a site not configured yet. Its value is never read back: only its being different from one install to the next matters. The draw is deterministic, so a worker restart or a redeploy never moves a site's schedule around, and `bin/console debug:scheduler` shows the times this site actually ended up with.

In practice the app doesn't call `spread()` for a bundle's commands at all — the bundles declare them, see below.

Two things it deliberately doesn't do. It spreads within the window the expression describes, so a heavy command still has to be given a window wide enough to spread *into* — `# */6 * * *` has 60 slots, `# 3 * * *` has one. And spreading is a draw, not an allocation: on a server hosting many sites, a pair landing on the same minute stays possible, which is a different problem from all of them landing on it.

## Contributing maintenance tasks from other bundles

Any bundle can have its own commands scheduled by implementing `MaintenanceTaskProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` and the others:

```php
namespace c975L\ShopBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

class ShopMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Expired download links, nightly
            new MaintenanceTask('# #(1-3) * * *', 'c975l:shop:downloads:delete'),
            // Product affinities, monthly: a full pass over the orders, too long to run nightly for what it changes
            new MaintenanceTask('# #(2-5) # * *', 'c975l:shop:affinity:calculate'),
        ];
    }
}
```

`MaintenanceScheduleBuilder` collects them all and adds them to the app's schedule, each spread as above:

```php
// src/Scheduler/MaintenanceSchedule.php, scaffolded by c975l/site-bundle
return $this->builder->addTasks((new Schedule())->stateful($this->cache));
```

**What this buys is a scaffolded schedule that lists no command at all**, and therefore stays the same file on every site: installing a bundle schedules its tasks, removing it stops them, and an upgraded scaffold can be propagated to a dozen sites rather than merged into each. Declare the task where the command lives — `c975l:shop:baskets:delete` belongs to PaymentBundle's provider, not ShopBundle's, baskets being PaymentBundle's own.

Three rules worth knowing:

- **The window is yours to pick**, and a heavy command needs a wide one (see the table above). Nothing stops a fixed expression either, for a command that must run at a set time.
- **A command declared twice is scheduled once.** `Schedule::add()` throws on a duplicate, which would take down every other task at worker start-up, so the builder drops repeats instead.
- **The site keeps the last word**: `addTasks($schedule, ['c975l:health-check:run --frequency=monthly'])` drops a declared command the site doesn't want run, without having to remove the bundle, and the app is free to `->add()` entries of its own afterwards.

## Contributing health check providers from other bundles

Any bundle can contribute a check by implementing `HealthCheckProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;

class MyHealthCheckProvider implements HealthCheckProviderInterface
{
    // Stable identifier for this provider's rows (eg. "my-check") - used for --kind= filtering and stored on every HealthCheckResult
    public function getKind(): string
    {
        return 'my-check';
    }

    // One entry per checked url: ['url', 'label', 'status' => HealthCheckResult::STATUS_*, 'summary', 'details' => array, 'editUrl']
    public function runChecks(): array
    {
        return [
            [
                'url' => 'https://example.com/pages/home/',
                'label' => 'Home',
                'status' => HealthCheckResult::STATUS_OK,
                'summary' => 'Everything checks out',
                'details' => null,
                'editUrl' => '/management/my-entity/1/edit',
            ],
        ];
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**Never call a slow/paid API from a controller** — `runChecks()` is only ever invoked from `c975l:health-check:run` (via `HealthCheckRunner`), so a page load never blocks on it. If your check needs an API key, read it via `ConfigServiceInterface` like any other config (see [Defining config entries for your bundle](#defining-config-entries-for-your-bundle) above) and degrade gracefully without one — either skip entirely (return `[]`) or, if the check is otherwise expected to be configured (see `c975l/site-bundle`'s own PageSpeed/WAVE providers), return a single explanatory row instead of one per page.

`editUrl` is optional (omit or `null` for a row with no admin CRUD counterpart, e.g. a site-wide check) — the admin edit screen for the entity behind that row (e.g. SiteBundle's Page edit screen), shown on the Health check table as a pencil link next to the tested url.

### Scheduling a provider

A provider is **weekly** unless it says otherwise, and it says so itself with `#[AsHealthCheck]` — nothing to declare site-side:

```php
use c975L\ConfigBundle\Attribute\AsHealthCheck;

// A run holding thousands of urls has no business on the same schedule as a handful of pages
#[AsHealthCheck(frequency: AsHealthCheck::FREQUENCY_MONTHLY)]
class MyHeavyHealthCheckProvider implements HealthCheckProviderInterface
```

The attribute is entirely optional: a provider is registered by its interface alone, and one that carries no attribute runs weekly. This is what lets a site's schedule ask for a cadence (`c975l:health-check:run --frequency=weekly`) instead of naming kinds — installing your bundle then puts your provider on the right cron entry with no line of code in the consuming app. `c975l/site-bundle`'s scaffold ships exactly two entries, one per cadence, and they never need editing.

The attribute sits on the **class**, which is enough for a provider registered once. A provider whose class is registered *several times over*, one instance per source — as SiteBundle's `DeclaredUrlsHealthCheckProvider` is, once per `SitemapProviderInterface` — implements `HealthCheckFrequencyAwareInterface` instead, so each instance answers for itself:

```php
class MyGeneratedHealthCheckProvider implements HealthCheckProviderInterface, HealthCheckFrequencyAwareInterface
{
    public function __construct(private readonly string $frequency = AsHealthCheck::FREQUENCY_WEEKLY)
    {
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }
}
```

`HealthCheckRunner` asks the instance first, then falls back to the class attribute, then to weekly. Only that rare generated-provider case needs the interface — everything else states its cadence with the attribute and never implements it.

### A provider that lists the whole of its domain

Results are kept per (url, kind) and the retention purge deliberately preserves the latest row of each pair, so a url that can never come back keeps its last **error** as the dashboard's current state for good. That is what happens whenever the url carries a generated filename: re-uploading a missing file names it anew, the green row lands on a url of its own, and the red one is orphaned rather than replaced.

A provider whose run enumerates its whole domain says so with `HealthCheckExhaustiveInterface`, and `HealthCheckRunner` then deletes that kind's rows for the urls missing from the run:

```php
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;

class MyUploadedFilesHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    // getKind() and runChecks() as above - the interface adds no method, it only states that a url this run did not return has nothing left to check
}
```

Only implement it on a provider that really does list its whole domain every run: an empty run is taken at face value and clears the kind entirely. A provider checking a fixed set of urls (security headers, the certificate, `robots.txt`) has no reason to — its urls never disappear, and its history is the point. A run that throws never purges, a failure telling nothing about what the provider declares.

## Contributing health check advice from other bundles

Any bundle can attach actionable advice under a Health check table row (e.g. "this page is missing an H1" linking to its edit screen) by implementing `HealthCheckAdviceProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;

class MyHealthCheckAdviceProvider implements HealthCheckAdviceProviderInterface
{
    // Keyed per result, via HealthCheckAdviceBuilder::key() (only the results this provider actually has something to say about) - $results is the same HealthCheckResult[] the current screen renders (dashboard "Health check" page or a CRUD's own scoped tab)
    public function buildAdvice(array $results): array
    {
        $advice = [];

        foreach ($results as $result) {
            if ('my-check' !== $result->getKind()) {
                continue;
            }

            $advice[HealthCheckAdviceBuilder::key($result)] = [
                [
                    'text' => '3 images are missing an alt text',
                    'url' => '/management/my-entity/1/edit',
                    // Optional - the individual offenders behind that line, rendered as a collapsed list under it
                    'items' => [
                        ['text' => 'banner.jpg', 'url' => '/management/my-entity/1/edit#block-4', 'label' => 'Edit the block'],
                    ],
                ],
            ];
        }

        return $advice;
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

Always build the key with `HealthCheckAdviceBuilder::key()` rather than concatenating it yourself — the table looks each row's advice up under that exact key, and a mismatch shows no advice at all rather than raising an error. Keying by `kind` alone isn't enough: the Health check page lists one row per url *and* per kind.

Each line needs a `text`, and may carry a `url` (rendered as a link next to the text) and an `items` list. An absolute `url` is taken as the external tool's own report for that page (PageSpeed, the W3C validators…) and opens in its own tab as "See full report"; a relative one is taken as pointing back into the back office at the very screen or field to fix, and opens in the current tab as an edit link. Adding `focusField=<property>` to such a url makes the target form open that field's own tab, scroll to it and focus it (`field-focus.js`, c975L/UiBundle) — the same idea as the `focusBlock` param, for a plain form field rather than a block row. `items` is for a line that summarizes several offenders ("3 images are missing an alt text") — each entry needs its own `text`, and may carry a `url` plus the `label` for that link (falling back to a pencil icon alone), so a dozen offenders stay collapsed instead of pushing the following rows off screen.

`HealthCheckAdviceBuilder::build()` merges every registered provider's advice; two providers with something to say about the same result have their lines appended, neither overwrites the other. It's shared by the dashboard "Health check" page and any CRUD's own "Health check" tab (both render through the same `health_check/_table.html.twig`), so advice reads identically everywhere.

## Status report — letting another system read what this site runs

A site knows its own PHP and Symfony versions, the packages it was installed with, and what its last health check run found. The `/status/report` route serves all of it as one JSON report, to whoever presents this site's key.

It is meant for whoever maintains **several** sites: one console asking each of them, in one place, turns "which of my sites is still on an unsupported PHP" into a query instead of a spreadsheet you update by hand.

**The site answers nobody until you set a key.** Installing this bundle never makes a site talk to a third party: with `site-status-key` empty there is nothing to compare a caller against, and every request gets a 404 — the same answer a wrong key gets, so a scanner finding the url learns nothing from it either way.

| Config | Role |
| --- | --- |
| `site-status-key` | Shared key a caller must present in the `X-Status-Key` header. Stored as a sensitive value. At least 32 characters, e.g. `openssl rand -hex 32` — below that, and empty (the default), the site answers nobody. |

The key is read online, not merely presented in an outgoing report: a short one is guessable against a route that says nothing but 200 or 404. Rather than accept a weak key, anything under 32 characters is treated as **no key at all**, so a site configured with `abc` answers nobody instead of answering whoever tries a dictionary. Refusals are logged as warnings with the caller's IP, which is what a `fail2ban` jail or a supervision rule can act on.

This route is declared by attribute, so the app has to import the bundle's controllers — an app that has never had a routed controller from ConfigBundle gets a 404 until it does:

```yaml
# config/routes.yaml
c975l_config:
    resource: "@c975LConfigBundle/src/Controller/"
    type: attribute
```

An app whose `access_control` covers more than `^/management` — a catch-all `^/` rule, say — also has to let the route through with `- { path: ^/status/report$, roles: PUBLIC_ACCESS }`, or its callers meet a login redirect, which says the url exists where a 404 would not.

```bash
# Read it as a console would
curl -H 'X-Status-Key: <the key>' https://example.com/status/report

# See exactly what a console would be served - needs no key and no network
php bin/console c975l:status:dump
```

The key travels in a **header**, never in the query string — an url ends up in the access log and in the `Referer` of anything the site serves, a header does not. Use a different key per site, so one compromised key only ever exposes one site's report. The answer carries `Cache-Control: private, no-store`: its body depends on a header, and a shared cache holding it would serve one caller's report to the next.

The route stays answerable while the site is in [maintenance mode](#maintenance-mode): a console reads it precisely to know a site is mid-upgrade, and serving it the HTML maintenance page would hide the very moment it is most worth reading.

**Asked, rather than sent.** A site that pushes its report can only say what was true when it last spoke, and cannot say it is down — the receiver has to infer that from a silence, which takes days to become certain and reads as stale data in the meantime. Asked instead, the answer is true at the moment it is read, no answer at all is itself an answer, and there is no cron to set up on each site nor any schedule to keep in sync.

What the report holds:

```json
{
    "version": 1,
    "site": "https://example.com",
    "generatedAt": "2026-08-01T14:22:03+02:00",
    "environment": "prod",
    "php": "8.4.3",
    "symfony": "8.0.4",
    "packages": {"c975l/config-bundle": "1.2.3", "easycorp/easyadmin-bundle": "5.1.0"},
    "checks": {
        "counts": {"ok": 42, "warning": 3, "error": 1, "skipped": 0},
        "lastRunAt": "2026-08-01T03:00:00+02:00",
        "issues": [{"kind": "ssl", "url": "https://example.com", "summary": "..."}],
        "issuesTruncated": false
    },
    "extra": {
        "capabilities": {
            "sapi": "fpm-fcgi",
            "exec": false,
            "directives": {"memory_limit": "512M", "max_execution_time": "60", "upload_max_filesize": "64M", "post_max_size": "64M", "max_input_vars": "1000"}
        },
        "registration": {"open": true, "form": "enabled"}
    }
}
```

Three deliberate limits. `packages` lists the installed **bundles** rather than the whole dependency tree, Symfony's own excluded since the `symfony` field already carries their version — whether a bundle is a direct requirement or came along with another one doesn't change what runs. `issues` carries the rows **in error** only, without their `HealthCheckResult::$details`: the receiver learns *where* it hurts and links back to the site to learn *why*, so the payload stays small and holds nothing revealing — a site merely in warning is a site to improve, and its `counts` still say so. And it is capped at 20 rows, `issuesTruncated` saying so — the counts stay exact either way, so a short list is never mistaken for a complete one.

`checks` is `null`, rather than absent or empty, on a site whose migrations haven't run yet: no health check data available is not the same thing as no issue found.

**The two sections this bundle contributes to `extra`** answer the questions a console holding a dozen sites can't get from a version number. `capabilities` (`CapabilitiesStatusProvider`, reading `Service\EnvironmentProbe`) says what the PHP process is actually allowed to do — its SAPI, whether `exec()` can be called at all, and the ini directives that silently cap an upload or a long task — because a host can withdraw a function from one site and not the next, nothing in the application changes, and the feature relying on it stops without ever raising an error. It is keyed `capabilities` and not `environment`, the report already carrying an `environment` of its own meaning `prod`/`dev`. `registration` (`RegistrationStatusProvider`) says whether a stranger can still create an account here, read off the register `Form`'s own `enabled` flag rather than off a config that could contradict it — a registration opened for a campaign and never closed again looks exactly like one that was never opened, from the outside and from the site's own dashboard. Both report readings and counts only: `disable_functions` in full names every function a host left reachable, which describes an attack surface rather than a capability, and that stays on the server.

### Contributing status data from other bundles

Any bundle can add a section to the report by implementing `StatusProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\StatusProviderInterface;

class MyStatusProvider implements StatusProviderInterface
{
    // The key this provider occupies in the report's "extra" section
    public function getStatusKey(): string
    {
        return 'shop';
    }

    // Counts and dates, nothing else: it travels over the network, and a receiver has no way to know a key is confidential
    public function getStatusData(): array
    {
        return [
            'pendingOrders' => $this->orderRepository->countPending(),
            'lastOrderAt' => $this->orderRepository->findLastDate()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

A provider that throws doesn't cost the whole report — its section carries the error message instead. A site that answers nothing reads as a much worse problem than one section that failed, so the report is always served.

## Dev profile — automating what the dev toolbar shows

`php bin/console c975l:dev-profile:run` renders every page your bundles declare **through the local kernel**, with the profiler on, and prints the list of what the Symfony dev toolbar would flag on each: n+1 queries, transactions opened around nothing, deprecations, missing translations, external HTTP calls made while rendering, and so on. It's the automation of "open every page in dev and look at the toolbar".

Everything about it is dev-only: the command, the runner, the collector and every path provider are marked `#[When('dev')]`, so none of those services even exist in prod (where the `profiler` service doesn't either). Nothing is persisted — no entity, no dashboard page, no trend chart. The output *is* the deliverable: a list to fix.

It reads its numbers from the profiler, so `symfony/profiler-pack` has to be installed (it is by default in a `symfony/skeleton` dev environment); without it the command says so on every page rather than reporting them clean.

**Why it doesn't reuse the health check**: [Health check](#health-check) fetches the *live* site over HTTP at `site-url`, which points at production even when run from a dev machine — exactly what you want to judge a deployed site, and exactly what you don't want when profiling the code you're editing. This command never builds a URL at all: providers declare local paths (`/`, `/pages/contact`), each is handed straight to the kernel like a functional test does, so what's measured is your local code against your local database.

```bash
php bin/console c975l:dev-profile:run                        # every declared page, problems only
php bin/console c975l:dev-profile:run --path=/pages/contact  # one page, repeatable
php bin/console c975l:dev-profile:run --all                  # also list the clean pages, with their numbers
```

Sample output:

```text
/ — Accueil
  HTTP 200 · 47 requêtes (31.2 ms) · 3 transactions · 68 templates (44.1 ms) · 2 dépréciations · cache 12/40 · 240 ms · 14.2 Mo
  ERREUR Doctrine       31 repeats of a same SQL (n+1), including 32 times: SELECT t0.id FROM site_block t0 WHERE t0.page_id = ?
  ALERTE Dépréciations  2 dépréciation(s) : Since symfony/framework-bundle 7.3: ...
```

The command exits non-zero as soon as one page has an **error**-level offence, so it can gate a pre-push hook the same way `c975l:site:smoke-test` gates a deployment — or your app's `composer test`, as the last entry so it runs once the test suite is green:

```json
"scripts": {
    "test": [
        "@php bin/console cache:warmup --env=test",
        "phpunit",
        "@php bin/console cache:pool:clear cache.app --env=dev",
        "@php bin/console c975l:dev-profile:run --env=dev"
    ]
}
```

`--env=dev` is not optional there: the command is `#[When('dev')]`, so it doesn't exist in the `test` environment — and the dev database is the one holding the pages you actually want profiled, where a test database would only hold fixtures.

### What's measured, and what counts as an offence

| Area | Read from | Reported when |
| --- | --- | --- |
| Doctrine | `db` collector | more than `MAX_QUERIES` (30) queries — error past 60 — or more than `MAX_DUPLICATE_QUERIES` (6) repeats of a same SQL, error past 9. The worst offender's SQL is quoted. Repeats are grouped by SQL text with the parameters left out, which is what makes a real n+1 visible at all, but also what makes a block-composed page repeat a shape by construction: every sibling block of a same kind reads its own data with the same SQL and its own parameters. Hence a tolerance set at what a page's composition can explain, and an error level at a count no composition reaches |
| Doctrine (transactions) | `db` collector | more than `MAX_TRANSACTIONS` (1) transactions opened — error past 5 — or any transaction that wrote nothing at all (warning). Doctrine opens one per `flush()`, so past one something is flushing inside a loop, or a listener is flushing on its own. Counted apart from the queries, and taken back out of the duplicate count: five identical `"START TRANSACTION"` are five flushes, not an n+1 |
| Deprecations | `logger` collector | any deprecation (warning) — the cheapest way to see what a Symfony major bump will require |
| Logs | `logger` collector | any error-level log written while rendering |
| Translations | `translation` collector | any key with no translation (error, the keys are listed) or served from the fallback locale (warning) |
| HttpClient | `http_client` collector | **any** call to an external API while rendering (error): that belongs in a command writing to the database, or at worst behind a cache |
| Twig | `twig` collector | more than `MAX_TEMPLATES` (150) templates rendered — deliberately high, a block-based theme legitimately renders dozens of small templates per page |
| Response | status code | a non-200: a redirect is a warning (usually the firewall, nothing was profiled), anything else an error |

Timings, memory and cache hits/misses are printed as context but are **never** an offence: `APP_DEBUG`, no opcache and no preloading make a dev machine's milliseconds say nothing about production, and the misses only say how warm the pools happened to be when the run started — whereas the counts above are the same numbers production would produce. The thresholds are constants on `DevProfileAnalyzer` — a site needing different ones overrides that service.

**Clear the app cache pool first** (`php bin/console cache:pool:clear cache.app`). Anything a cached block hides — a missing translation inside it, a Twig syntax error, the queries it would run — stays hidden as long as its cache entry is there, and the run reports the page as clean. It's the single biggest way to get a falsely reassuring report.

Two deliberate behaviours worth knowing: the first declared path is profiled **twice** and its first result dropped (the kernel stays booted from one path to the next, so that one would otherwise carry every warm-up cost — config read from the database, templates compiled, cache pools filled — that none of the following ones show); and `services_resetter` is called after each path, exactly as a messenger worker does between two messages, without which every page would be reported carrying the previous ones' numbers.

## Contributing dev profile paths from other bundles

Any bundle can declare the pages it owns by implementing `DevProfilePathProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above. Mark it `#[When('dev')]`, so it never reaches a production container:

```php
namespace App\Management;

use c975L\ConfigBundle\Management\DevProfilePathProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('dev')]
class MyDevProfilePathProvider implements DevProfilePathProviderInterface
{
    public function __construct(
        private readonly MyRepository $myRepository,
    ) {
    }

    // One entry per path to profile: ['path' => local absolute path, 'label' => ?string]
    public function getPaths(): array
    {
        $paths = [];
        foreach ($this->myRepository->findAllPublished() as $item) {
            $paths[] = ['path' => '/shop/' . $item->getSlug(), 'label' => $item->getName()];
        }

        return $paths;
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

**Local paths only** — `/pages/contact`, never `https://example.com/pages/contact`: the path is handed to the kernel, no HTTP request and no host involved. Two bundles declaring the same path is fine, it's profiled once. `c975l/site-bundle` already contributes `PageDevProfilePathProvider` (every published `Page`), so an app installing it has nothing to write for its own pages.

## Contributing procedures for the dashboard AI assistant

`ProcedureProviderInterface` lets a satellite bundle document its own admin workflows (e.g. "how do I create a page") for an AI assistant built into the consuming app's dashboard — ConfigBundle only collects and merges these entries, it doesn't ship the assistant itself.

Satellite bundles contribute procedures by implementing `ProcedureProviderInterface` — no manual service tagging needed, `TaggedInterfacePass` auto-detects any class implementing it, same mechanism as `MenuProviderInterface` above:

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\ProcedureJsonReader;
use c975L\ConfigBundle\Management\ProcedureProviderInterface;

class MyProcedureProvider implements ProcedureProviderInterface
{
    public function getProcedures(): array
    {
        return ProcedureJsonReader::read(\dirname(__DIR__, 2) . '/config/procedures.json');
    }
}
```

Make sure your bundle's `services.yaml` includes the `Management/` folder in its `src/` resource so the class is registered.

Declare your bundle's entries in a `config/procedures.json` file, `slug` unique across every bundle:

```json
[
    {
        "slug": "creer-page",
        "title": {
            "en": "Create a page",
            "fr": "Créer une page"
        },
        "body": {
            "en": "Go to Pages, click Add, fill in the title...",
            "fr": "Allez dans Pages, cliquez sur Ajouter, renseignez le titre..."
        }
    }
]
```

**Merging:** `ProcedureBuilder::getAll()` merges every provider's procedures, sorted by `slug` for a stable, deterministic order regardless of service registration order.

## Reading config values

### In PHP

```php
use c975L\ConfigBundle\Service\ConfigServiceInterface;

class MyService
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {}

    public function doSomething(): void
    {
        $siteName  = $this->configService->get('site-name'); // string
        $maxItems  = $this->configService->get('max-items'); // int (auto-cast)
        $isEnabled = $this->configService->get('feature-enabled'); // bool (auto-cast)
        $env       = $this->configService->getContainerParameter('kernel.environment');
    }
}
```

### In Twig

```twig
{# Read from database #}
{{ config('site-name') }}

{# Read from Symfony container parameters #}
{{ configParam('kernel.environment') }}

{# What a footer credit shows: none, logo, name or logo-name #}
{{ credits_mode('display-made-by') }}
```

`credits_mode()` is how `display-made-by` and `display-hosted-by` are read — never `config()` directly. Both
entries were a `bool` before `v1.6`, and a stored value is never rewritten by `c975l:config:load-all`: a site still
holding `"true"` gets `logo` back (all a credit could show then) and `"false"` gets `none`, where `config()`
would hand over the string `"false"`, truthy in Twig. It always answers one of the four modes, so a template
only has to ask whether the mode holds `logo`, `name`, or both.

---

## Timezone

The `site-timezone` entry (**Général**, defaulting to `Europe/Paris`) is the hour every template shows — a select of the European identifiers plus `UTC`. `TimezoneListener` applies it to Twig on `kernel.request` and on `console.command`, so a command or a Messenger worker naming a file after the hour reads it too, not requests alone. Without it PHP falls back on `UTC` as long as `date.timezone` is left out of its `php.ini`, and a back-office two hours behind the clock is one whose dates stop being read.

**Only the reading moves.** PHP itself keeps writing in its own timezone: a date stored in base has no business depending on where the server sits, and nothing already recorded is rewritten. A site that never set the entry — one upgrading to this version — keeps reading its dates exactly as it did.

An identifier `DateTimeZone` does not know is left alone rather than thrown: the value is picked from a list in the back-office, but a site restored from an older base can hold anything, and a wrong timezone is no reason to answer nothing at all.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/CoreBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/CoreBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!

## AI agent skills

The bundle ships four skills of its own, written for the coding agent of the site installing it rather than for someone modifying it. Point your agent at the directory:

```text
vendor/c975l/core-bundle/ConfigBundle/skills/
```

| Skill | Covers |
| --- | --- |
| `c975l-config` | where a setting belongs, declaring and reading one, the closed group list, sensitive/restricted/severity, the commands, maintenance mode |
| `c975l-management` | every contribution interface of the `/management` dashboard, the one wiring rule, the exports, and the test proving the targets still exist |
| `c975l-users` | the user contract, the `site-role-*` settings, `ROLE_SUPER_ADMIN`, registration and its anti-spam layers, login and access |
| `c975l-operations` | sitemaps and SEO files, redirects, url metadata, health checks, backup, status report, scheduler, dev profile |

They are split by subject rather than shipped as one file so that an agent loads the one it needs. Each holds what an agent gets wrong when left to its own habits — that a setting is a `configs.json` entry and never a `.env` variable, that a contribution class needs no service tag but does need its folder scanned, that `ROLE_SUPER_ADMIN` is never listed in a config, that a sitemap is a generated file and not a route.

Nothing is installed, nothing is copied into your project: the files sit in `vendor/` like any other part of the package and follow it at each `composer update`. A user of Claude Code wanting one to load by itself symlinks it into their own skills directory:

```bash
ln -s ../../vendor/c975l/core-bundle/ConfigBundle/skills/c975l-config .claude/skills/c975l-config
```

`Tests\SkillsTest` keeps them honest: every path, route, config slug, command, class member, Twig function, block kind and component they quote is checked against the sources, so renaming any of them fails the build rather than leaving an agent confidently wrong.

---

## License

MIT — see [LICENSE](LICENSE).
