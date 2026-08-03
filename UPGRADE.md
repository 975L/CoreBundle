# UPGRADE

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

### If you forget

`c975l/core-bundle` declares `replace` for both old package names, so a satellite bundle still requiring `c975l/config-bundle` or `c975l/ui-bundle` resolves onto this package instead of installing a second copy of the same namespaces.

The replacement is declared at the exact versions this package supersedes (`config-bundle 6.0.0`, `ui-bundle 1.18.0`), not at `*`. An older constraint such as `c975l/config-bundle: ^5` therefore **fails to resolve** rather than silently receiving newer, incompatible code — read the two bundles' own `UPGRADE.md` and update the constraint.

### The old packages

`c975l/config-bundle` and `c975l/ui-bundle` are abandoned in favour of `c975l/core-bundle`. Their last published versions (`v5.17.1` and `v1.17.0`) remain installable forever; they simply receive no further releases.

---

Both bundles' own upgrade notes follow in their respective files:

- [ConfigBundle/UPGRADE.md](ConfigBundle/UPGRADE.md)
- [UiBundle/UPGRADE.md](UiBundle/UPGRADE.md)
