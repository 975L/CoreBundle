---
name: c975l-operations
description: "Use this skill when running, monitoring or backing up a Symfony application built on the c975L ecosystem — sitemaps and the SEO files, redirects, url metadata, the health-check dashboard, the backup and its offsite copy, the status report, scheduled maintenance tasks and the dev profile. Covers which command writes what, which database it must run against, and what belongs in a static file rather than a route. Triggers on: NotFound, site_not_found, NotFoundSubscriber, NotFoundCrudController, NotFoundAlertProvider, NotFoundRepository, NotFoundCleanupCommand, c975l:config:not-found-cleanup, site-not-found-retention-days, broken link, dead link, referer, config-not-found, c975l:sitemaps:create, c975l:seo:files:create, c975l:url-metadata:sync, c975l:health-check:run, HealthCheckResult, acknowledgedAt, setAcknowledgedAt, health_check_acknowledge, STATUS_SKIPPED, c975l:config:backup, c975l:config:backup:offsite, c975l:config:backup:digest, c975l:status:dump, StatusReportBuilder, dependencies, AccessibilityHealthCheckProvider, AccessibilityClient, HtmlDocument, accessibility, RGAA, RGAA_VERSION, MAX_URLS_PER_SOURCE, c975l:dev-profile:run, c975l:config:sessions-cleanup, Redirect entity, STATIC_PATH_PATTERN, UrlMetadata, robots.txt, humans.txt, llms.txt, site-status-key, BackupPathProviderInterface, MaintenanceTaskProviderInterface, ExternalLinkCheckSchedule, externalLinksCheckedAt, FILTERED_STATUSES, LINK_FILTERED, HealthCheckReportBuilder, health_check_report, findLatestPerUrlAndKindIn, OffsiteState, FileCounter, MAX_DELETE_PERCENT, --max-delete, trailing slash, alternates, hreflang, xhtml:link, SitemapWriter."
---

# c975L ConfigBundle — operating a site

> Everything a deployed c975L site writes, checks, backs up and reports: sitemaps and SEO files, redirects, health checks, backups, the status report and the scheduler.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\ConfigBundle\`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Command/`, `src/Entity/Redirect.php`, `src/Entity/NotFound.php`, `src/Entity/UrlMetadata.php`, `src/Repository/NotFoundRepository.php`, `src/Management/NotFoundAlertProvider.php`, `src/EventSubscriber/`, `src/Management/`, `src/Scheduler/`, `src/Service/`, `templates/management/`

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
php bin/console c975l:config:sessions-cleanup # expired rows of the PdoSessionHandler table
php bin/console c975l:dev-profile:run        # dev only: what the toolbar would flag, on every page
```

**Sitemaps and SEO files are generated static files under `public/`, not routes** — served by the web
server, they keep answering 200 during a maintenance, where a controller-rendered `robots.txt` would
503 and stop the crawl of the whole site. `robots.txt` only declares the sitemap index once that file
really exists, so the order of those two commands matters.

A `SitemapProviderInterface` url is `loc` plus the optional `lastmod`, `changefreq` and `priority`
(declared on the admin's 0-10 scale, converted to the protocol's 0.0-1.0 by `SitemapWriter`). A site
offering several languages may add **`alternates`**, a `hreflang => url` map written out as
`xhtml:link rel="alternate"`. **Include the url's own language in that map**: a group is read whole,
and a page naming only its neighbours declares a group no engine keeps. A single-language provider
declares no such key and nothing is written.

The eight `seo-*` settings driving them are `restricted`. `seo-robots-block-ai` is **on by default**;
`seo-robots-private` is off.

## Redirects

`Entity\Redirect` (`site_redirect`) carries `fromPath`, `toUrl`, `permanent` and `gone`, resolved on
`kernel.request` **before the router**. They live here, not in whichever bundle serves the content: a
url that changed needs a redirect whether it was a page's or a product's.

- **`gone`** answers 410 rather than redirecting — search engines drop a 410 far faster than a 404.
- **`fromPath` accepts a trailing `*`**: `/apidoc/*` covers everything below. An exact row wins over a
  prefix; among prefixes the longest wins.
- **A trailing slash is not another url**: nothing matching `/contact/`, the row written `/contact`
  answers it. Tried only once an exact and a prefix lookup both came up empty, so a row stating its
  own trailing slash still answers for itself.
- **`toUrl` accepts one too, and that pairing renames a tree**: `/character/*` → `/personnages/*`
  carries the tail over. A destination without the `*` folds the whole tree onto one url — both are
  wanted, and the `*` is what tells them apart.
- The site root is left alone by design.
- **A path the web server answers itself is refused**: `Redirect::STATIC_PATH_PATTERN` rejects anything
  under `/assets` or `/bundles` carrying a file extension, and `RedirectSubscriber` returns on those
  without querying — a missing asset would otherwise turn a 404 into a database connection. Uploads
  under `/medias` stay redirectable.

**Never deploy a redirecting route per old url.** A renamed tree is a handful of rows.

## Broken links

`Entity\NotFound` (`site_not_found`) is one row per dead path — `path` (unique), the `referer` that led
there, whether that referer is `internal` (this very host), a `hits` count and `firstSeen`/`lastSeen`.
It answers the question the redirects above cannot: **which** url needs one.

- **Only 404s carrying a `Referer` are recorded**, on `GET`, and never on a `410`. That header is what
  separates a link that broke from the noise a 404 otherwise attracts: a browser following a link always
  sends one, and the scanners walking `/wp-admin` do not. Monolog is deliberately not this place - its
  prod handler excludes 404 precisely because the mail would be 99% scanners, and the same noise would
  drown a table just as well.
- **Paths the web server answers itself are skipped**, the same `Redirect::STATIC_PATH_PATTERN` the
  redirects decline: a missing asset is a deployment matter, not a published url anyone can be sent to.
- **The row is written in plain SQL on the connection**, not through the entity manager: a flush failing
  inside `kernel.exception` would close it and take the error page down with it. Every failure is
  swallowed - a site whose migration has not run yet simply takes no note, and a 404 never becomes a 500.
- **`internal` is taken on trust and only ever believed loosely.** A referer is written by whoever sent
  the request, scheme included, so anyone can file their own 404s as broken links of yours.
  `NotFoundAlertProvider` alerts on those alone: an external dead link is a redirect to make when
  convenient, not something to interrupt anyone with.
- **`NotFoundCrudController` is read-only but for deleting**, and carries a *Create the redirect* action
  opening a new `Redirect` on that very path (`RedirectCrudController::createEntity()` prefills
  `fromPath` from the query). The `config-not-found` guided project walks that whole path.
- **`c975l:config:not-found-cleanup`** and its weekly `MaintenanceTask` delete what nothing has followed
  for `site-not-found-retention-days` - 90 by default, `0` keeping everything.

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
`intrusion`, `accessibility`, and `urls-<bundle>`.

**`DeclaredUrlsHealthCheckProvider` is the one to know from a satellite bundle**: it runs the
content-quality analysis over the urls a bundle already declares for its sitemap, one kind per bundle.
**Nothing to implement bundle-side** — declaring the sitemap is enough. Do not write a per-bundle
content check, and do not remount in an `extra` status section what it already reports.

**`accessibility` reads the same sitemap list, for the RGAA**: `AccessibilityHealthCheckProvider`
answers the eight RGAA 4.1 criteria a page's rendered markup can settle (2.1, 5.6, 6.2, 8.3, 8.4,
9.1, 11.1, 12.6), one row per url, monthly, capped at `MAX_URLS_PER_SOURCE` (50) urls per sitemap.
Nothing to implement bundle-side either. Each row's `details` carries the whole verdict table,
**conforming criteria included** — that half is what an accessibility statement is written from.
Contrast, focus, tab order and any judgement of relevance are **not attempted**, a browser engine
being what measures them. Criteria 1.1, 8.5 and the `<h1>` count stay with `content-quality`, which
traces the offending image back to its block: do not restate them here. `Service\AccessibilityClient`
reports what the markup holds, the provider decides which finding is a non-conformity — and both open
their documents through `Service\HtmlDocument`, the shared libxml precautions, never a bare
`DOMDocument`.

**Internal and external links are not checked on the same cadence.** The links inside the site are
checked every run, in batches of ten. The links leaving it are called **once a month**
(`ExternalLinkCheckSchedule::INTERVAL_DAYS`), the runs in between reporting back what that pass found,
so a dead external link stays on the dashboard without its host being called every week — the date of
the last real pass travels in each row's `details`, under `externalLinksCheckedAt`. When they are
called they are spread over their hosts, at most one url per host in flight at a time: a site linking
mostly to two or three merchants would otherwise fire ten requests at one of them at once, from a
single server address, which is what gets that address rate limited and then blocked. A host answering
`403`, `429` or `999` is not retried in `GET` (`ContentQualityClient::FILTERED_STATUSES`) — that
answer describes a filtered client, where the `405`/`501` a retry does resolve describes a method a
server refuses. **Everything else the `HEAD` pass did not settle is retried in `GET`, a `404`
included, and the `GET`'s verdict is the one kept**: a url routed client-side — a share intent
(`bsky.app/intent/compose`), a single-page app's route — answers `404` to a `HEAD` it serves in `200`,
which had a working share button reported as a dead link on every page carrying it.

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

**Reading the dashboard**: the table opens on the warning and error rows nobody has declared dealt
with, not on every row recorded — a passing site records hundreds of `ok` ones. `skipped` is labelled
*not verifiable* and is hidden with them: it covers a target that is up and turns automated probes
down (a store answering `403` to a `HEAD`) as much as a page never reached, and neither is an editor's
to fix. Return `STATUS_SKIPPED` for those rather than an error nobody can act on.

**A fixed row is acknowledged, never re-run**: the ✓ button stamps `HealthCheckResult::setAcknowledgedAt()`
and the row leaves the default view and the dashboard alert on the spot. The stamp is borne by the
row, not by the (url, kind) pair — rows are appended, so the next run records a fresh unacknowledged
one and a problem that was not actually fixed comes back on its own. Do not delete a result row to
clear the dashboard: the export is an audit artefact, and the next run would recreate it anyway.

**Two exports, one run.** *Export (CSV)* is the dated audit trace — one line per (url, kind), opened
in a spreadsheet, kept. *Diagnostic report (JSON)* is the diagnosis: every row needing action, the
acknowledged ones included, carrying the checkers' own `details` payload, under the site's identity,
environment and bundle versions (`HealthCheckReportBuilder`, which is `StatusReportBuilder`'s report
plus those details). It is the file to attach to a ticket or hand to an assistant, where a screenshot
of the table says only *what* is wrong. `ok` and `skipped` rows are left out, `checks.counts` still
saying how many there were, and nothing is capped — unlike `/status/report`'s own issue list, which
travels over the network.

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

**The mirror does not carry a deletion over blindly.** `c975l:config:backup:offsite` sizes `--max-delete`
on what each folder currently holds — a quarter of its files, never fewer than 30 (`FileCounter` counts
them, locally on purpose: a local side that lost its files counts near zero, so the guard tightens
exactly when it matters). A fixed count fits no two folders: 100 deletions is a wipe for a gallery of 80
photos and an ordinary morning for 1500 derived images regenerated under new names.

**A failed mirror reaches the backup row**, `c975l:config:backup` reading `OffsiteState` back and
raising it as a warning and a line of its report. The mirror runs on its own night, and the archives
push refreshes the timestamp the offsite status is computed from every six hours — so a mirror red for
a month sat under a row saying "offsite ok". Only the mirror is read back: the archives push is that
command's own and is reported the moment it fails.

## Status report

`/status/report` serves the site's PHP and Symfony versions, its installed bundles and its last health
check as JSON, to whoever presents `site-status-key` in an `X-Status-Key` header. **The site answers
nobody until a key is set** — an empty key, or one under 32 characters, gets every caller a 404, the
same answer a wrong key gets.

Two lists travel, not one: `packages` holds the installed **bundles**, `dependencies` holds
**everything** installed with its version, platform entries aside, so a receiver can look the site up
against a vulnerability database — the lookup is the receiver's, never the site's. `VERSION` is `2`
since that second list appeared: a receiver reads it to tell a site that omits the section from one
that has nothing in it.

A bundle adds to its `extra` section with `StatusProviderInterface`. **The criterion is strict: a
figure calling for no action is not reported.** The report is read across a dozen sites at once; a
"number of blocks" decides nothing and buries what matters.

ConfigBundle fills three `extra` keys itself: `capabilities` (SAPI, `exec()`, the ini limits),
`registration` (whether a stranger can still create an account) and `messenger` — one count per
countable transport plus the name of the failure transport. **A stopped worker shows zero failures**:
what has failed for good sits in the failure transport, what was never tried piles up in the ordinary
ones, so a console reading only the failure count reads a dead worker as healthy. Transports that
cannot count themselves (the scheduler's) are left out rather than reported as zero.

## Scheduler and dev profile

`c975l:config:sessions-cleanup` deletes the expired rows of `PdoSessionHandler`'s `sessions` table —
the same `DELETE` its own garbage collection runs, on a cadence instead of on a dice roll: that
collection is probabilistic and on a managed host can simply never fire. Declared by
`ConfigMaintenanceTaskProvider`, so it runs nightly with nothing to add to a schedule; a site storing
its sessions in files has no such table and the command says so rather than failing.

A bundle contributes scheduled work with `MaintenanceTaskProviderInterface` rather than asking each
site to add a cron line. `DevProfilePathProviderInterface` declares the paths `c975l:dev-profile:run`
walks — it hands each path to the **local** kernel, no HTTP and no host involved, unlike the health
check and the smoke test which fetch the live site at `site-url`.

## Do not

- **Do not log broken links through Monolog** — its prod handler excludes 404 on purpose. The
  `Referer` guard is what keeps the table about links and not about scanners.

- **Do not serve `robots.txt`, `humans.txt`, `llms.txt` or a sitemap from a controller.**
- **Do not point Search Console at a sub-sitemap** — only at the index.
- **Do not write a redirecting route** for a url that changed. Add a `Redirect` row.
- **Do not add a `Redirect` row on a path under `/assets` or `/bundles`** — the entity refuses it, and
  the subscriber never queries for one.
- **Do not store what an url says in code**, nor declare a url's sentences from a provider.
- **Do not run a health check from a controller.**
- **Do not call external links on every run**, and do not retry a `403`/`429`/`999` in `GET`.
- **Do not claim a page conforms to the RGAA from the `accessibility` rows alone** — they answer
  eight criteria of 106, and never the ones a browser engine measures.
- **Do not open a bare `DOMDocument`** to read a page's markup. Go through `HtmlDocument`.
- **Do not run the checks, the sitemap or the backup against a staging database** while `site-url`
  points at production.
- **Do not back up code, templates or asset sources.**
- **Do not tar a mirror path.**
- **Do not add a `Redirect` row for the trailing-slash variant of a path** — the subscriber falls back
  to the path without it.
- **Do not report a figure in the status `extra` section that calls for no action.**
