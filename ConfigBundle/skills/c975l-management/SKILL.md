---
name: c975l-management
description: "Use this skill when a bundle or an application has to add anything to the /management dashboard of a c975L site — a menu entry, an alert, a shortcut, a widget, a guided project, a what's new note, an importmap entry, an admin procedure, an export or an import, a linkable route. Lists every contribution interface, the one wiring rule that makes them work, and the test that proves their targets still exist. Triggers on: MenuProviderInterface, AlertProviderInterface, ShortcutProviderInterface, DashboardWidgetProviderInterface, GuidedProjectProviderInterface, WhatsNewProviderInterface, ImportmapProviderInterface, ProcedureProviderInterface, ExportProviderInterface, ImportProviderInterface, LinkableRouteProviderInterface, EssentialActionProviderInterface, BackOfficeAccessVoter, TaggedInterfacePass, TableExporter, ManagementTargetsTestCase, EasyAdmin dashboard, whatsnew.json."
---

# c975L ConfigBundle — contributing to /management

> Every bundle adds to the shared EasyAdmin dashboard through a contribution interface. One class in a `Management/` namespace, no service tag to write, no dependency on the bundle you are adding to.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\ConfigBundle\` · **Twig namespace:** `@c975LConfig`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Management/`, `src/Scheduler/MaintenanceTaskProviderInterface.php`, `src/Test/ManagementTargetsTestCase.php`, `src/Service/Export/TableExporter.php`, `src/Service/Export/ContentExporter.php`, `src/DependencyInjection/`, `config/whatsnew.json`

**Related skills:** `c975l-config`, `c975l-users`, `c975l-operations` in this same bundle.

## The mechanism, once

A class implementing one of the interfaces below, placed in your bundle's `Management/` namespace, is
picked up by a compiler pass (`TaggedInterfacePass`). **There is no service tag to write.**

The single thing to check: your bundle's `services.yaml` must cover `Management/` in its `src/`
resource. A class outside the scanned resource is never registered, and nothing reports it.

```php
namespace c975L\MyBundle\Management;

use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;

class MyUrlMetadataProvider implements UrlMetadataProviderInterface
{
    public function getUrlMetadataPaths(): array
    {
        return ['/animaux', '/caste/guerrier'];
    }
}
```

## The interfaces

| Interface | Methods | Adds |
| --- | --- | --- |
| `MenuProviderInterface` | `getMenuSection()`, `getMenus()`, `getLinks()` | sidebar sections, entries and plain-route links |
| `AlertProviderInterface` | `getAlerts()` | `danger` / `warning` / `info` alerts on the dashboard |
| `ShortcutProviderInterface` | `getShortcuts()` | quick-action tiles, one titled row per `category` — POST and CSRF token required; `'active' => true` says the thing is on and the tile turns it off, `'warning' => true` paints the tile (optional, filled from `active` when left out, set it yourself where the state to signal is the *off* one), and such a tile belongs in `CATEGORY_TOGGLE` |
| `DashboardWidgetProviderInterface` | `getDashboardWidgets()` | dashboard widgets |
| `EssentialActionProviderInterface` | `getEssentialActions()` | entries of the "essential actions" checklist |
| `GuidedProjectProviderInterface` | `getGuidedProjects()` | replayable guided tours of your screens |
| `WhatsNewProviderInterface` | `getEntries()` | user-facing release notes, read from `config/whatsnew.json` |
| `ProcedureProviderInterface` | `getProcedures()` | admin workflows for the dashboard AI assistant |
| `ImportmapProviderInterface` | `getImportmapEntries()`, `getAdminImportmapEntries()` | AssetMapper importmap entries, written on `composer update` |
| `LinkableRouteProviderInterface` | `getLinkableRoutes()` | routes offered as SiteBundle menu targets |
| `UrlMetadataProviderInterface` | `getUrlMetadataPaths()` | urls that have no entity to describe themselves |
| `SitemapProviderInterface` | `getSitemapName()`, `getUrls()` | a sitemap of your own |
| `HealthCheckProviderInterface` | `getKind()`, `runChecks()` | health checks |
| `HealthCheckAdviceProviderInterface` | `buildAdvice()` | advice lines under a check's results |
| `MaintenanceTaskProviderInterface` | `getMaintenanceTasks()` | scheduled maintenance tasks |
| `StatusProviderInterface` | `getStatusKey()`, `getStatusData()` | an `extra` section of the status report |
| `BackupPathProviderInterface` | `getBackupPaths()` | directories the backup must carry |
| `DevProfilePathProviderInterface` | `getPaths()` | paths the dev profile walks |
| `ExportProviderInterface` | `getKind()`, `exportAll()` | your content in the **Export sync (everything)** shortcut |
| `ImportProviderInterface` | `supportsImport()`, `import()` | your content in the **Import content** screen |

`LinkableRouteProviderInterface` lives here, not in SiteBundle, **precisely so a bundle can offer a
menu target without depending on SiteBundle**. The same reasoning applies to all of them: a satellite
depends on the core and on nothing else.

Two nuances that get lost:

- Menu sections sharing the same `label` **and** `translation_domain` merge under one heading, and the
  entries are sorted alphabetically on the **translated** label.
- A menu entry, an alert and a guided project each take an optional `role`. On a menu it defaults to
  `site-role-admin` and must be set to the bar the entry's own screen states, `setPermission()` being
  unreadable from here: too high and the entry goes missing from a sidebar its screen would have
  answered, too low and it leads to a 403 the guided tour walks the user to. The dashboard itself
  opens on `BackOfficeAccessVoter::ACCESS`, not on a role, so an editor stands in it and every block
  filters itself (see `c975l-users`).
- `whatsnew.json` is a marketing thread for non-developer users — no `version`, no `bundle` field,
  describe a benefit. The developer changelog is `ChangeLog.md`.

## Exports

**Do not write an export by hand.** `Service\Export\TableExporter` takes a table name and an array of
associative rows and returns a `Response` in SQL, CSV or JSON:

```php
return $this->tableExporter->export(ExportFormat::Sql, 'my_table', $rows);
```

Its optional 4th argument is a context read by the SQL encoder alone: `primary_key` (enables
`ON DUPLICATE KEY UPDATE`), `exclude_from_update`, `insert_ignore_when`.

For content that does not fit a flat dump — nested structures, real files — `ContentExporter` produces
a zip (`manifest.json` plus the files). On the way back in, `ImportProviderInterface::import()`
receives the raw items and the directory the zip was extracted into. **Match on a natural key (slug,
name), never on a raw id**: dev and prod ids never need to match.

## Proving the targets still exist

Every provider names a target nothing verifies: a menu entry names a CRUD class, a link or a guided
step names a route. Renaming it compiles, and **a dead sidebar link takes the whole back office
down**, not just its own entry.

`ManagementTargetsTestCase` checks all of it with **no kernel and no database** — route names are read
off the `#[AdminRoute]` / `#[Route]` attributes the controllers already carry. About twenty lines per
bundle:

```php
class ManagementTargetsTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [new MenuProvider($this->createStub(ConfigServiceInterface::class))];
    }

    protected function controllerDirectories(): array
    {
        return [...parent::controllerDirectories(), __DIR__ . '/../../src/Controller'];
    }
}
```

It lives in `src/` because a bundle cannot autoload another bundle's test files. **If your
`services.yaml` scans `src/`, exclude any class extending `TestCase`** — PHPUnit is a dev dependency,
and the container compilation of every production site breaks otherwise.

## Do not

- **Do not add a Composer dependency on another satellite bundle** to contribute to its screens.
  Implement the interface instead.
- **Do not write a service tag** for a contribution class — the compiler pass finds it.
- **Do not leave a menu entry on the admin default** when its own screen opens to an editor.
- **Do not leave `Management/` out of the `src/` resource** in `services.yaml`.
- **Do not write your own export.** Reuse `TableExporter` or `ContentExporter`.
- **Do not match on ids when importing.** Use the natural key.
- **Do not put a technical changelog entry in `whatsnew.json`** — it is read by the site's owner.
- **Do not declare a GET admin route under `/config/...`** — `/config/{entityId}` of the Config CRUD
  swallows it. Pages sit at the root; only POST actions may live under `/config/`.
- **Do not leave a `TestCase` subclass inside a scanned `src/`.**
