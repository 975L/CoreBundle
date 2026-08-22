---
name: c975l-forms-emails
description: "Use this skill when building a form or sending an email in a Symfony application built on the c975L ecosystem — the admin-editable Form and FormField entities, the form block, form actions, the shared anti-spam layers and reCAPTCHA, the EmailTemplate builder, EmailService and the email layout registry. Covers why a contact form needs no controller and why a bundle never writes an email layout. Triggers on: Form entity, FormField, FormFieldTemplate, FormController, form block, FormActionInterface, FormActionRegistry, SendEmailFormAction, FormSeeder, form_url, FormPageUrlProviderInterface, FormBotProtection, honeypot, CaptchaType, recaptcha3-site-key, site-form-delay, site-form-gdpr, EmailTemplate, EmailBlock, EmailService, EmailSendRequest, wrapLayout, EmailLayoutProviderInterface, email_template_body, email-debug, EmailDebugShortcutController, DebugPreviewCapableInterface, consumeDebugPreview."
---

# c975L UiBundle — forms and emails

> A form is a database row an editor composes, processed by a key your bundle registers. An email is a template body your bundle renders, wrapped by whichever layout the site has.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi` · **Translation domain:** `ui`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Form.php`, `src/Entity/FormField.php`, `src/Entity/FormFieldTemplate.php`, `src/Entity/EmailTemplate.php`, `src/Controller/FormController.php`, `src/Controller/Management/FormCrudController.php`, `src/Contract/FormActionInterface.php`, `src/Service/FormSeeder.php`, `src/Service/SendEmailFormAction.php`, `src/Service/EmailService.php`, `src/Model/EmailSendRequest.php`, `src/Controller/Management/EmailDebugShortcutController.php`, `src/Service/FormBotProtection.php`, `src/Form/CaptchaType.php`, `templates/components/Form/`

**Related skills:** `c975l-blocks`, `c975l-media`, `c975l-ui-assets` in this same bundle, and `c975l-users`, `c975l-config` in ConfigBundle beside it.

## Forms are rows, not controllers

`Entity\Form` / `Entity\FormField` (`site_form`, `site_form_field`) hold a named form and its fields.
An editor composes one entirely from the back office; a bundle seeds its own and locks what it must.
**Registration, password reset and contact are all just rows** — there is no `RegistrationController`,
no contact controller, and there should be none of yours either.

- Rendering and submission are `Controller\FormController`'s, through the **`form` block kind**
  (embeds any form by name, anywhere a block can go) or the bare route.
- `Form::$enabled` pauses a form without unpublishing its page.
- `Form::$restricted` / `FormField::$restricted` lock what a bundle seeded — the field's type and its
  deletion, or the form's own name — while leaving it reorderable and relabellable.
- `Entity\FormFieldTemplate` is a catalogue of ready-made fields picked from a select rather than
  composed by hand.
- `Form::$links` are the way out of a dead end ("already have an account, sign in"), following the
  form wherever it is rendered.

### Processing a submission

Implement `Contract\FormActionInterface`, one `getKey()` per implementation; `Form::$action` stores
which key handles a form and `Form::$actionConfig` is free-shape JSON only that action reads. **This
bundle never knows what your action does.**

`Service\SendEmailFormAction` (key `send_email`) is the built-in one, so a form built purely through
the admin still notifies someone, configured through `actionConfig` (`to`, `from`, `replyTo`,
`subject`, `template` or `emailTemplate`, `senderEmailField`, `offerReceiveCopy`), everything unset
falling back on the site-wide `email-*` settings.

`Service\FormSeeder::ensureForm()` / `ensureEmailTemplate()` are how a bundle gets its rows in place
out of the box — idempotent, seeding `restricted` rows, backfilling an older seed in place, **never
overwriting an admin's edit**, and neither flushes, so a batch stays one transaction.

`form_url(name)` answers where a form is actually reachable: a bundle displaying it on something
richer than the bare route contributes its url through `FormPageUrlProviderInterface`, and a template
linking to a form never has to know which bundles are installed.

### Protections, already there

Shared by every public form, with **nothing to wire**: a rotating honeypot and a minimum submit delay
(`site-form-delay`), a site-wide GDPR checkbox (`site-form-gdpr`), a live MX/A DNS check on every
email-typed field, `IsTrue` on every required checkbox, a rate limiter counting a caller rather than
an address (an IPv6 one by its /64), and reCAPTCHA v3 — a no-op unless `recaptcha3-site-key` and
`recaptcha3-secret-key` are both filled in.

Google's script is fetched only once the visitor interacts, and the token requested only on submit —
a token grabbed on page load expires after two minutes anyway. If Google is unreachable the form is
submitted with an empty token and the server decides: a visitor is never stuck on a page that
silently refuses to submit.

## Emails

Two natures of content, two paths — **do not confuse them**:

- **Editorial content** (account confirmation, forgotten password, a contact notification): an
  `EmailTemplate` seeded by `FormSeeder::ensureEmailTemplate()`, editable in the back office.
  Substitution of `{{ variables }}` is **literal**, the block vocabulary is closed (`heading`, `text`,
  `button`, `image`, `divider`, `spacer`, `fields_table`), and **there is no loop**.
- **Structured content** (an order recap, a list of tickets, download links): a Twig template of
  **body only**, living in your bundle, rendering its own components. It cannot be expressed in blocks
  — and there is no point: a client does not edit a table of order lines.

```php
$this->emailService->send(new EmailSendRequest(
    subject: $subject,
    context: ['basket' => $basket],
    template: '@c975LPayment/emails/confirm_order.html.twig',  // the BODY alone, no extends
    wrapLayout: true,
    to: $basket->getEmail(),
    bcc: $this->configService->get('shop-email-bcc'),
));
```

**`wrapLayout: true` is what dresses it**: `EmailLayoutProviderInterface` answers with SiteBundle's
branded layout when that bundle is installed, and with this bundle's bare one otherwise. A request
carries **exactly one** body — `template`, `html` or `text` — anything else is refused rather than
sent as whichever the chain tested first.

The six address settings (`email-from`, `email-from-name`, `email-to`, `email-to-name`,
`email-reply-to`, `email-reply-to-name`) live in ConfigBundle and resolve on their own; an explicit
`to:` wins. `bcc` is a real blind copy, not to be confused with `copyToEmail`, which sends a **second**
message.

**Nothing is sent while the debug mode is on.** `email-debug` (a restricted config) plus
`ROLE_SUPER_ADMIN` makes `EmailService` render the message and stash it (`consumeDebugPreview()`)
instead of sending it — anyone else keeps getting real sends. It is switched from the dashboard's
**"Enable / Disable"** row (`Controller\Management\EmailDebugShortcutController`), whose tile stays
warning-colored for as long as the mode is on. Implement `Contract\DebugPreviewCapableInterface` on
your own form action to get the same behavior.

## Do not

- **Do not write a controller for a form.** It is a `Form` row and a `FormActionInterface`.
- **Do not create a fields table for your bundle's form.** Use `Form` / `FormField`.
- **Do not add your own honeypot, delay, captcha or rate limiter** — they are already on every form.
- **Do not overwrite an admin's edit when seeding.** `FormSeeder` only ever fills what is unset.
- **Do not write an email layout in a satellite bundle**, and do not `extends` a layout from an email
  template: send the body with `wrapLayout: true`.
- **Do not turn structured content into an `EmailTemplate`** — the blocks have no loop, and the
  structure would be lost without becoming editable.
- **Do not pass more than one body** to an `EmailSendRequest`.
- **Do not declare your own `email-*` address settings** when the six shared ones answer.
- **Do not leave the email debug mode on.** While it is, a `ROLE_SUPER_ADMIN` only ever gets a
  preview and no message leaves the site.
