# UPGRADE

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
|---|---|
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
|---|---|
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
|---|---|
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
|---|---|
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
|---|---|
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
