---
name: c975l-config
description: "Use this skill for any configuration question in a Symfony application built on the c975L ecosystem — where a setting belongs, how to declare one, how to read it, and why .env and container parameters are the wrong answer here. Covers config/configs.json, ConfigServiceInterface, how a group drawer is named and labelled, sensitive and restricted values, severities, the vault key, the loading and pruning commands, and maintenance mode. Triggers on: configs.json, ConfigServiceInterface, ConfigService, config(), configParam(), c975l:config:load-all, c975l:config:set, c975l:config:get, c975l:config:prune, c975l:config:encrypt-sensitive, C975L_VAULT_KEY, sensitive, restricted, severity, ConfigAlertProvider, findSensitiveWithValue, site-maintenance, ConfigTranslator, ConfigTranslator::TRANSLATABLE, site_config owner, translate a setting, .env, parameters.yaml, TreeBuilder, ConfigGroupLabelResolver, label.group_, SiteLocales, enabled_locales, LocaleListener, default_locale, translation.yaml, multilingual, isMultilingual, setLocales, language selector."
---

# c975L ConfigBundle — configuration

> Application configuration lives in the database, is declared by each bundle in a JSON file, and is edited by the admin in EasyAdmin. Not in `.env`, not in `parameters:`, not in a Configuration class.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\ConfigBundle\` · **Twig namespace:** `@c975LConfig` · **Translation domains:** `config`, `site_config`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Config.php`, `src/Service/ConfigService.php`, `src/Service/ConfigServiceInterface.php`, `src/Command/ConfigLoadAllCommand.php`, `src/Command/ConfigSetCommand.php`, `src/Command/ConfigPruneCommand.php`, `src/Command/EncryptSensitiveCommand.php`, `src/Controller/Management/`, `src/Service/SiteLocales.php`, `src/Listener/LocaleListener.php`, `config/configs.json`

**Related skills:** `c975l-management`, `c975l-users`, `c975l-operations` in this same bundle, and `c975l-blocks`, `c975l-media`, `c975l-forms-emails`, `c975l-ui-assets` in UiBundle beside it.

## The rule

**Application configuration is a `config/configs.json` entry in the bundle that reads it**, seeded into
the `site_config` table and edited in the back office. Never:

- a `.env` variable for an application setting;
- an entry under `parameters:` in `config/services.yaml`;
- a bundle extension with a Configuration.php / TreeBuilder class exposing options.

**The one legitimate `.env` value is `C975L_VAULT_KEY`** in `.env.local` — the AES-256-CBC key
encrypting `sensitive` values. That is infrastructure, not an application setting.

**The one setting declared in a Symfony config file is the list of languages** — see below. Symfony
itself reads that list, which is what puts it outside this rule rather than beside it.

## Declaring an entry

```json
[
    {
        "label": "label.site_name",
        "slug": "site-name",
        "sensitive": false,
        "restricted": false,
        "value": null,
        "kind": "text",
        "group": "general",
        "severity": "warning",
        "description": "description.site_name"
    }
]
```

- **`kind`** — `text`, `html`, `int`, `bool`, `date`, `json`, `font`, `choice`. A `choice` entry carries
  a `choices` array and the admin picks from a `<select>`; a value off the list is refused on save
  rather than stored. For `json`, `value` is the escaped JSON string and `ConfigService::get()` returns
  the decoded PHP array.
- **`group`** — the drawer of the "pick a group" screen. **The drawer belongs to the question the
  editor asks, not to the bundle that answers it.** `Config::GROUPS` lists the ones ConfigBundle
  labels — `system`, `general`, `legal`, `credits`, `analytics`, `backup`, `email`, `form`,
  `security`, `shop`, `payment`, `theme`, `ai`, `messenger`, `seo`, `health_check` — and several
  bundles fill `legal`, `shop`, `security` and `email` on purpose: that is the one place an editor
  goes looking. Otherwise a bundle **names its own** (`book`, `gallery`, `social`, `site`, `media`,
  `reviews`, `ui`) and ships `label.group_<slug>` in the **`config`** domain, `ConfigsJsonTest`
  failing on a drawer named and not labelled. The screen reads in three bands — the site, the
  features, the technical ones — each ordered on its translated label, an unlisted drawer falling
  under the features (`Management\ConfigGroupLabelResolver::bands()`).
- **A named entry is translatable**: the index draws a "Traduire" action opening
  its edit screen on another language, and what is written there goes to UiBundle's `site_translation`
  table under the owner type `site_config` (`Service\ConfigTranslator`, ECOSYSTEM.md §26). The typed
  value never moves, playing the msgid the way `Page::$title` does, and a setting nobody translated
  keeps it. Which entries those are is listed one by one in `ConfigTranslator::TRANSLATABLE`
  (`site-age-warning` today) and **not** deduced from the kind: a url, a postal address, a name or a
  technical key holds words and is still said the same way in every language, and anything PHP reads
  straight from `ConfigService::get()` never sees the layer. A `sensitive` entry is left out whatever
  its slug — a secret holds no sentence, and the
  language screen leaves the stored value untouched, which there is its encrypted envelope. Nothing of
  this exists on a site declaring a single language. Do **not** store a second entry per language.
- **`sensitive: true`** for any secret: encrypted at rest, masked in the list. One holding a value the
  site can no longer decrypt is raised as a **danger alert** of its own (`ConfigAlertProvider`, off
  `ConfigRepository::findSensitiveWithValue()`): everything reading it gets an empty value while the
  entry still shows as filled, so nothing else says the site is running without it.
- **`restricted: true`** on top, for a secret shared by the whole install: the entry disappears
  entirely — index, edit form and every export — for anyone without `ROLE_SUPER_ADMIN`.
- **`severity`** — `danger`, `warning`, `info`: as long as `value` is empty, the entry is listed as a
  dashboard alert linking straight to it, and the alert disappears on its own once filled.
- **`label` and `description` are both translation keys**, resolved in the `site_config` domain. The
  label is looked up as `label.<slug with underscores>`; a lookup finding nothing displays the text
  as written, so an app declaring its own configs can write both in clear.

An **application** can drop its own `config/configs.json` at its root and get its settings in the
dashboard with no command of its own.

## Loading and pruning

```bash
php bin/console c975l:config:load-all           # every c975L bundle's configs*.json, plus the app's own
php bin/console c975l:config:prune              # lists entries no declaration covers any more
php bin/console c975l:config:prune --force      # deletes them, after confirmation
php bin/console c975l:config:encrypt-sensitive  # idempotent
```

`load-all` inserts new slugs and **re-syncs only the metadata** of the ones that exist — `label`,
`kind`, `choices`, `group`, `severity`, `description`, `restricted`, `sensitive`. **`value` carries
production state and is never overwritten**, so fixing a label and re-running is risk-free.

One exception: **a row holding nothing takes the value its declaration carries.** An entry whose
*emptiness* means something must therefore name that meaning with a value of its own (`0`, `none`)
rather than by being empty.

## Reading a value

```php
use c975L\ConfigBundle\Service\ConfigServiceInterface;

public function __construct(private readonly ConfigServiceInterface $configService) {}

$siteName = $this->configService->get('site-name');
$env = $this->configService->getContainerParameter('kernel.environment');
```

```twig
{{ config('site-name') }}
{{ configParam('kernel.environment') }}

{# The language a page is written in, when it is not the visitor's - a book sheet renders in the book's own #}
{{ config('site-age-warning', book.language) }}
```

**Inject the interface, never the concrete class.** There is already a one-hour cache, invalidated
automatically on every save: **do not add a cache layer on top.**

From the command line:

```bash
php bin/console c975l:config:get site-name
php bin/console c975l:config:set user-creation-notification false
```

## The languages a site offers

**A site declares them in `config/packages/translation.yaml`, next to the language it is written in** —
the one application setting that is not a `configs.json` entry:

```yaml
framework:
    default_locale: en
    enabled_locales: ['en', 'fr', 'es']
```

Symfony is itself a consumer of this list: it restricts a route's `_locale` to it and compiles only the
catalogues it names, so a value it cannot see would leave both beside the point.

`Service\SiteLocales` is the one place anything asks what a site offers. It always holds the default
locale whether or not the list names it, and drops any code the Intl catalogue does not know — a typo
would otherwise take down every back-office page through EasyAdmin's `Locale::new()`.

- **`all()`** — every language offered, the default one first.
- **`isMultilingual()`** — whether more than one is offered. **A site declaring nothing offers its
  default language alone and behaves exactly as it always did**, which is every site until it says
  otherwise: guard anything language-related on this.

`Listener\LocaleListener` (priority 20) sets the language of a request from the `_locale` query
parameter, then the session, then what the browser asks for — a route carrying its own `_locale`
attribute wins over all three. The back-office selector is EasyAdmin's own
(`Dashboard::setLocales()`), which only appends `?_locale=xx` and reads it back nowhere, so this
listener is what keeps the choice — for the front office too.

`enabled_locales` **restricts**: on a site already serving several languages, list every one of them,
not just the new ones. It is unrelated to the interface translations (`messages.fr.xlf`), which keep
working through Symfony's translator whether or not the list exists. Translating *content* is
UiBundle's — see `c975l-blocks`.

## Maintenance mode

`site-maintenance` set to `true` answers every public request with a **503 and a `Retry-After`** —
not a 200, which would get the maintenance page indexed in place of the real ones. `/management` and
`/login` stay reachable, as does anyone holding `site-role-admin` or the `site-maintenance-hash`
token (`?t=…`, opening a six-hour session). `robots.txt`, `humans.txt`, `llms.txt` and the sitemaps
are static files under `public/` and keep answering 200 — a `robots.txt` answering 503 stops the
crawl of the whole site.

Do not leave it on for more than a day or two: past that, search engines stop reading the 503 as
temporary.

## Do not

- **Do not add a `.env` variable, a container parameter or a Configuration/TreeBuilder class** for
  an application setting. The only `.env` value that belongs here is `C975L_VAULT_KEY`, and the only
  setting declared in a Symfony config file is `framework.enabled_locales`.
- **Do not add a config entry for the languages a site offers**, and do not read them from anywhere
  but `SiteLocales` — not from `framework.enabled_locales` directly, which skips the default locale
  and the Intl check.
- **Do not inject `ConfigService`** — inject `ConfigServiceInterface`.
- **Do not cache a config value yourself.** The service already does, and invalidates on save.
- **Do not invent a `group` to avoid sharing one.** `book-legal` or `payment-email` splits what an editor fills in one sitting; file under the shared drawer instead. A drawer of your own is for what only your bundle answers, and it ships its `label.group_*` with it.
- **Do not declare a slug in one bundle and read it from another.** The entry belongs to the bundle
  that reads it; if two read it, it moves to their common ancestor.
- **Do not use emptiness as a meaningful state** on an entry carrying a seeded default.
- **Do not serve `robots.txt` or a sitemap from a controller** — they must survive maintenance mode.
- **Do not render a maintenance page with a 200 or a 404.**
