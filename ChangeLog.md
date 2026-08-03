# ChangeLog

## Unreleased

ConfigBundle and UiBundle ship as a single package

- `c975l/config-bundle` and `c975l/ui-bundle` merged into `c975l/core-bundle`, two bundles in one package (03/08/2026) [BC-Break]
- Both bundles keep their namespace, services, templates, translations and `bundles.php` entry (03/08/2026)
- `replace` declared for both old package names, at the versions this package supersedes (03/08/2026)
- No PHP change at all: `getPath()` already anchors on each bundle class' own file, not on the package root (03/08/2026)
- One CI run, one PHPUnit run and one PHPStan run over the two bundles, catching the cross-breaks the two separate pipelines could not see (03/08/2026)
- `COMPOSER_ROOT_VERSION` dropped from the CI, the cross-requirement it worked around being gone (03/08/2026)

Each bundle's own history is kept in its directory: [ConfigBundle/ChangeLog.md](ConfigBundle/ChangeLog.md) and [UiBundle/ChangeLog.md](UiBundle/ChangeLog.md).
