---
name: c975l-users
description: "Use this skill when working on accounts, roles or access control in a Symfony application built on the c975L ecosystem — the User contract, the site-role-* settings, ROLE_SUPER_ADMIN and restricted configs, registration and its anti-spam layers, password reset, login throttling and back-office access. Triggers on: UserInterface contract, UserCrudController, site-role-admin, site-role-editor, ROLE_SUPER_ADMIN, user-roles-available, UserManagementVoter, EmailVerifier, UserRegistrar, PasswordResetter, isEnabled, isVerified, UserChecker, login_throttling, access_control, register form, reset_password_request, honeypot, DnsEmail, user-creation-notification."
---

# c975L ConfigBundle — users, roles and access

> Accounts, the roles that gate the back office, and the layers that keep a public registration form from becoming a way to farm confirmation emails.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\ConfigBundle\`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Contract/UserInterface.php`, `src/Controller/Management/UserCrudController.php`, `src/Security/`, `src/Service/UserRegistrar.php`, `src/Service/EmailVerifier.php`, `src/Service/PasswordResetter.php`, `src/Service/UserFormSeeder.php`, `src/EventSubscriber/LoginRequestSubscriber.php`, `src/Command/UserCreateCommand.php`, `scaffold/src/`

**Related skills:** `c975l-config`, `c975l-management` in this same bundle, and `c975l-forms-emails` in UiBundle beside it.

## The contract

The application owns its `App\Entity\User`; this bundle owns the contract every c975L entity relates
to. **`App\Entity\User` must implement `c975L\ConfigBundle\Contract\UserInterface`** — the scaffolded
one already does, an older one adds the `implements` with no migration and no configuration change.

A satellite bundle typing a `$user` property does so against that interface, never against `App\Entity\User`.

## Roles

There is **no `role_hierarchy`**: each role is granted explicitly, so `ROLE_ADMIN` never implies
`ROLE_EDITOR`, and an account holding only the former fails every `site-role-editor` action.

| Setting | Gates |
| --- | --- |
| `site-role-admin` | the back office as a whole, and the User screen |
| `site-role-editor` | content screens — pages, menus, galleries, the front-office edit buttons |
| `user-roles-available` | the roles (`json`) the User form offers |

Both `site-role-admin` and `user-roles-available` are `restricted`: they gate the whole admin and
decide which roles exist, so a plain `ROLE_ADMIN` must never reach them.

`site-role-admin` is the one entry read before it can exist — `ConfigService::loadAll()` falls back on
its declared default, `ROLE_ADMIN`, while the row is absent, or a fresh install would lock everyone
out including whoever would fix it.

### ROLE_SUPER_ADMIN

It is decided by `UserCrudController`, **not read from the config**: stripped from whatever
`user-roles-available` holds, and put back — first in the list — only for an acting user who already
holds it. Server-side, not merely visually: out of the choices means out of the submitted form's
allowed values, so a crafted submission is rejected. Without that, any `ROLE_ADMIN` could grant it to
themselves and bypass every restricted config in one step.

The reverse is blocked too: `UserManagementVoter`, handed to EasyAdmin as the entity permission and
therefore evaluated per row, keeps a plain `ROLE_ADMIN` off a super admin's account entirely — email,
password reset and deletion included. On such a record the `roles` field is rendered **disabled**,
because Symfony's `ChoiceType` silently drops a value missing from the choices when displaying, which
would have demoted them on save without either of them seeing it.

Any config flagged `restricted: true` is invisible below `ROLE_SUPER_ADMIN` — index, edit form and
every export.

## Registration and password reset

**They are not controllers.** Both are plain `c975L\UiBundle\Entity\Form` rows (`register`,
`reset_password_request`), processed by UiBundle's generic `FormController`, their fields editable from
the Forms screen. The work itself is done by a `FormActionInterface` implementation in the app's
scaffold (`RegisterFormAction`, `ResetPasswordRequestFormAction`), calling this bundle's
`UserRegistrar`, `EmailVerifier` and `PasswordResetter`.

**To disable registration, uncheck the `register` form's `enabled` field** — no deployment. The
dashboard shortcut and the status report find that row by its **action**, not by its name, the name
being editable.

Protections, all shared with every other public form:

- `Assert\Email` plus a live MX/A DNS lookup (`DnsEmail`) on every email field, `User::$email`
  included;
- a rotating honeypot and a minimum submit delay (`site-form-delay`, default 3 seconds) — either
  failing redirects back with the very same flash a real submission gets, giving a bot no signal;
- a GDPR checkbox (`site-form-gdpr`) and, for registration, a terms-of-use one;
- a **duplicate email succeeds silently** — same flash, no account, no email — the same non-revealing
  stance a reset request has for an unknown address;
- rate limiting by caller through the shared `limiter.ui_form` (sliding window, 5 per 10 minutes),
  prepended by UiBundle, an IPv6 caller counted by its /64.

## Login and access

```yaml
security:
    firewalls:
        main:
            user_checker: App\Security\UserChecker
            login_throttling: { max_attempts: 5 }
    access_control:
        - { path: ^/management, roles: IS_AUTHENTICATED_FULLY }
```

`c975l:site:create` writes all three. `IS_AUTHENTICATED_FULLY` rather than an admin role on purpose:
**which role grants the back office is `site-role-admin`, editable from the dashboard**, so the
controllers check it themselves. On a `lazy: true` firewall it also makes the token resolve up front,
without which the dashboard runs before the firewall has restored it.

`LoginRequestSubscriber` sends a POST to `app_login` carrying no usable `_username` straight back to
the form: scanners otherwise trigger a `BadRequestHttpException` the kernel logs at `ERROR`, burying a
production log. Nothing that could ever authenticate is turned away.

`isEnabled` gates login independently from `isVerified`. `EmailVerifier::handleEmailConfirmation()`
sets both on confirmation; `isVerified` is readonly in the back office — only that confirmation may
set it — while `isEnabled` stays editable, which is how an account is locked out without being
deleted.

Every account created through `UserRegistrar` also notifies the site's own `email-to` address, in
`kernel.default_locale`. Uncheck `user-creation-notification` to stop it. It never gets in the way of
the registration itself.

## Do not

- **Do not type a property against `App\Entity\User`** from a bundle. Use the contract.
- **Do not add a `role_hierarchy`** expecting `ROLE_ADMIN` to imply `ROLE_EDITOR`.
- **Do not list `ROLE_SUPER_ADMIN` in `user-roles-available`.**
- **Do not check `ROLE_ADMIN` in a controller** — read `site-role-admin` or `site-role-editor`.
- **Do not put an admin role in the firewall's access control for `^/management`.**
- **Do not write a `RegistrationController` or a `ResetPasswordController`.** They are `Form` rows and
  a `FormActionInterface`.
- **Do not reveal that an email is already registered**, or that an address has no account.
- **Do not make `isVerified` editable** in a back-office form.
