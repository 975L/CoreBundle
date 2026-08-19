---
name: c975l-operations
description: "Use this skill when running, monitoring or backing up a Symfony application built on the c975L ecosystem — sitemaps and the SEO files, redirects, url metadata, the health-check dashboard, the backup and its offsite copy, the status report, scheduled maintenance tasks and the dev profile. Covers which command writes what, which database it must run against, and what belongs in a static file rather than a route. Triggers on: c975l:sitemaps:create, c975l:seo:files:create, c975l:url-metadata:sync, c975l:health-check:run, c975l:config:backup, c975l:config:backup:offsite, c975l:config:backup:digest, c975l:status:dump, c975l:dev-profile:run, Redirect entity, UrlMetadata, robots.txt, humans.txt, llms.txt, site-status-key, BackupPathProviderInterface, MaintenanceTaskProviderInterface."
---

# c975L ConfigBundle — operating a site

> Everything a deployed c975L site writes, checks, backs up and reports: sitemaps and SEO files, redirects, health checks, backups, the status report and the scheduler.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\ConfigBundle\`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Command/`, `src/Entity/Redirect.php`, `src/Entity/UrlMetadata.php`, `src/EventSubscriber/`, `src/Management/`, `src/Scheduler/`, `src/Service/`, `templates/management/`

**Related skills:** `c975l-config`, `c975l-management`, `c975l-users` in this same bundle.

## The commands

```bash
php bin/console c975l:sitemaps:create        # one sitemap per provider, plus sitemap-index.xml
php bin/console c975l:seo:files:create       # robots.txt, humans.txt, llms.txt - run right after
php bin/console c975l:url-metadata:sync      # empty rows for the declared urls, waiting to be described
php bin/console c975l:health-check:run       # every registered provider, persisted for the dashboard
php bin/console c975l:config:backup          # dump + archive + offsite, silent unless it fails
php bin/console c975l:config:backup:offsite  # mirror the declared upload folders
php bin/console c975l:config:backup:digest   # emails a digest of the last 7 days
php bin/console c975l:status:dump            # the status report, locally
php bin/console c975l:dev-profile:run        # dev only: what the toolbar would flag, on every page
```

**Sitemaps and SEO files are generated static files under `public/`, not routes** — served by the web
server, they keep answering 200 during a maintenance, where a controller-rendered `robots.txt` would
503 and stop the crawl of the whole site. `robots.txt` only declares the sitemap index once that file
really exists, so the order of those two commands matters.

The eight `seo-*` settings driving them are `restricted`. `seo-robots-block-ai` is **on by default**;
`seo-robots-private` is off.

## Redirects

`Entity\Redirect` (`site_redirect`) carries `fromPath`, `toUrl`, `permanent` and `gone`, resolved on
`kernel.request` **before the router**. They live here, not in whichever bundle serves the content: a
url that changed needs a redirect whether it was a page's or a product's.

- **`gone`** answers 410 rather than redirecting — search engines drop a 410 far faster than a 404.
- **`fromPath` accepts a trailing `*`**: `/apidoc/*` covers everything below. An exact row wins over a
  prefix; among prefixes the longest wins.
- **`toUrl` accepts one too, and that pairing renames a tree**: `/character/*` → `/personnages/*`
  carries the tail over. A destination without the `*` folds the whole tree onto one url — both are
  wanted, and the `*` is what tells them apart.
- The site root is left alone by design.

**Never deploy a redirecting route per old url.** A renamed tree is a handful of rows.

## Url metadata

`Entity\UrlMetadata` (`site_url_metadata`) holds `title`, `summarySocialNetwork` and `ogImage` for the
urls **no entity carries** — a listing, a filtered listing, a tool page. Keyed by **path, not by route
name**: one route serving twelve listings is twelve rows. A row only ever fills a silence, both
layouts reading it last for whatever the template left unset.

A bundle declares **which urls exist, never what they say** — the paths are structure and live in the
code, the sentences are content and live in the database — through `UrlMetadataProviderInterface`,
then `c975l:url-metadata:sync` creates the empty rows. It only ever creates: a row whose url no longer
appears is reported, never deleted.

```twig
{{ url_metadata().title }}
{{ url_metadata('/caste/guerrier').summarySocialNetwork }}
```

## Health checks

`/management/health-check` runs entirely server-side over plain HTTP calls — no Node, no headless
browser. This bundle's own kinds: `ssl-certificate`, `security-headers`, `security-misconfig`,
`seo-files`, `ai-crawlers`, `redirect-chains`, `sitemap-robots`, `deployment`, `database-load`,
`intrusion`, and `urls-<bundle>`.

**`DeclaredUrlsHealthCheckProvider` is the one to know from a satellite bundle**: it runs the
content-quality analysis over the urls a bundle already declares for its sitemap, one kind per bundle.
**Nothing to implement bundle-side** — declaring the sitemap is enough. Do not write a per-bundle
content check, and do not remount in an `extra` status section what it already reports.

**For the files a bundle stores rather than the urls it publishes**, UiBundle ships
`AbstractDeclaredFilesHealthCheckProvider`: extend it, yield the files your rows name, and every one
missing from `public/` is reported as an error (kinds `files-ui`, `files-site`, `files-gallery`). It is
what catches a file that only ever existed on the server — a site graphic, a signature — and that no
deployment carries. Do not write a file-exists check of your own.

A provider whose run lists the whole of its domain — the file checks above, `svg-fonts` — implements
`HealthCheckExhaustiveInterface`, and the runner then drops that kind's rows for the urls the run no
longer returns. Without it a url carrying a generated filename keeps its last error for good, the
retention purge preserving the latest row of each (url, kind). Never put it on a provider checking a
fixed set of urls: an empty run clears the kind.

Checks run from `c975l:health-check:run` only, never from a controller, so a slow or paid API call
never blocks a request. **Run them against production's own database**: the url list comes from
whichever database is connected, while the urls always point at `site-url`.

## Backup

Three kinds of state, three treatments — and the third decides the shape of the rest:

| State | Treatment |
| --- | --- |
| Code, templates, asset sources | **nothing** — git plus `composer install` brings it back |
| Configuration and content | the database dump, `site_config` included |
| Files neither in git nor in the database | declared through `BackupPathProviderInterface` |

A declared path is in one of two modes: **`archive`** for something small wanted with a history
(`.env.local` is the case that matters — git-ignored *and* outside the database, so a server restored
with every photo and no `APP_SECRET` does not start), or **`mirror`** for something large written once
— uploads. A mirror is copied as-is, never tarred, never compressed, never dated: a photo does not
need a version history, it needs a copy.

Everything is configured in the `backup` config group, all of it `restricted`.

## Status report

`/status/report` serves the site's PHP and Symfony versions, its installed bundles and its last health
check as JSON, to whoever presents `site-status-key` in an `X-Status-Key` header. **The site answers
nobody until a key is set** — an empty key, or one under 32 characters, gets every caller a 404, the
same answer a wrong key gets.

A bundle adds to its `extra` section with `StatusProviderInterface`. **The criterion is strict: a
figure calling for no action is not reported.** The report is read across a dozen sites at once; a
"number of blocks" decides nothing and buries what matters.

## Scheduler and dev profile

A bundle contributes scheduled work with `MaintenanceTaskProviderInterface` rather than asking each
site to add a cron line. `DevProfilePathProviderInterface` declares the paths `c975l:dev-profile:run`
walks — it hands each path to the **local** kernel, no HTTP and no host involved, unlike the health
check and the smoke test which fetch the live site at `site-url`.

## Do not

- **Do not serve `robots.txt`, `humans.txt`, `llms.txt` or a sitemap from a controller.**
- **Do not point Search Console at a sub-sitemap** — only at the index.
- **Do not write a redirecting route** for a url that changed. Add a `Redirect` row.
- **Do not store what an url says in code**, nor declare a url's sentences from a provider.
- **Do not run a health check from a controller.**
- **Do not run the checks, the sitemap or the backup against a staging database** while `site-url`
  points at production.
- **Do not back up code, templates or asset sources.**
- **Do not tar a mirror path.**
- **Do not report a figure in the status `extra` section that calls for no action.**
