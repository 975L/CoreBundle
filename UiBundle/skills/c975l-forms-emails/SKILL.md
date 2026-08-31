---
name: c975l-forms-emails
description: "Use this skill when building a form or sending an email in a Symfony application built on the c975L ecosystem — the admin-editable Form and FormField entities, the form block, form actions, the shared anti-spam layers and reCAPTCHA, the EmailTemplate builder, EmailService and the email layout registry. Covers why a contact form needs no controller and why a bundle never writes an email layout. Triggers on: EmailAttachment, EmailAttachmentProviderInterface, EmailAttachmentRegistry, attachmentsFor, LegalDocumentAttachmentProvider, attachments, attaching a PDF, durable medium, Form entity, FormField, FormFieldTemplate, FormOutput, calculator, calculateur, ExpressionEvaluator, CalculatorExpressionLanguage, CalculatorController, ui_form_compute, ValidExpressions, FormOutputType, ui-calculator, TYPE_RANGE, TYPE_CHOICE, outputsFirst, outputs_first, formulaVariables, formula-variables, FormExportProvider, FormImportProvider, site_form, exportSelection, FormController, form block, FormActionInterface, FormActionRegistry, SendEmailFormAction, FormSeeder, form_url, FormPageUrlProviderInterface, FormBotProtection, honeypot, CaptchaType, recaptcha3-site-key, site-form-delay, url-privacy-policy, text.gdpr_information, EmailTemplateProviderInterface, EmailTemplateProviderRegistry, EmailTemplateProviderPass, EmailTemplateFactory, EmailTemplateHealthCheckProvider, EmailTemplateRepository, findForRendering, renderNamed, c975l:ui:email-templates:ensure, EmailTemplateEnsureCommand, seededBlocks, locale, TYPE_SLOT, slot, DATA_TYPES, isDataBlock, data block, backfill, FormEditUrl, EmailTemplate, EmailBlock, EmailService, EmailSendRequest, wrapLayout, EmailLayoutProviderInterface, email_template_body, email-debug, EmailDebugShortcutController, consumeDebugPreviews, EmailDebugExtension, ui_email_debug_previews, Email:DebugPreview, absolute_urls, AbsoluteUrlsExtension."
---

# c975L UiBundle — forms and emails

> A form is a database row an editor composes, processed by a key your bundle registers. An email is a template body your bundle renders, wrapped by whichever layout the site has.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi` · **Translation domain:** `ui`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Form.php`, `src/Entity/FormField.php`, `src/Entity/FormFieldTemplate.php`, `src/Entity/FormOutput.php`, `src/Service/ExpressionEvaluator.php`, `src/Service/CalculatorExpressionLanguage.php`, `src/Controller/CalculatorController.php`, `src/Validator/ValidExpressions.php`, `src/Form/FormOutputType.php`, `src/Management/FormExportProvider.php`, `src/Management/FormImportProvider.php`, `assets/js/formula-variables.js`, `src/Entity/EmailTemplate.php`, `src/Controller/FormController.php`, `src/Controller/Management/FormCrudController.php`, `src/Contract/FormActionInterface.php`, `src/Service/FormSeeder.php`, `src/Service/SendEmailFormAction.php`, `src/Service/EmailService.php`, `src/Service/EmailTemplateRenderer.php`, `src/Service/EmailTemplateFactory.php`, `src/Service/FormEditUrl.php`, `src/Contract/EmailTemplateProviderInterface.php`, `src/Registry/EmailTemplateProviderRegistry.php`, `src/Command/EmailTemplateEnsureCommand.php`, `src/Management/EmailTemplateHealthCheckProvider.php`, `src/Repository/EmailTemplateRepository.php`, `src/Entity/EmailBlock.php`, `src/Model/EmailSendRequest.php`, `src/Model/EmailAttachment.php`, `src/Contract/EmailAttachmentProviderInterface.php`, `src/Registry/EmailAttachmentRegistry.php`, `src/Service/LegalDocumentAttachmentProvider.php`, `src/Controller/Management/EmailDebugShortcutController.php`, `src/Service/FormBotProtection.php`, `src/Form/CaptchaType.php`, `src/Twig/EmailDebugExtension.php`, `templates/components/Form/`, `templates/components/Email/`

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

### A form that computes instead of being sent

A `Form` owning at least one `Entity\FormOutput` (`site_form_output`) is a **calculator**:
`Form::isCalculator()` is what every path branches on, and it has **no action** — it computes and
displays, it never submits. The `form` block embeds it like any other form.

- An output is a **formula an admin types** (`km_an / 100 * conso * prix_e85`) plus its format, unit
  and decimals. The unit is trimmed and set off from the figure by a **non-breaking space**, as the
  percent sign is — an admin types `t`, never ` t`, and never the space itself. Order matters: an
  expression only sees the outputs **above** it. An invisible output
  is an intermediate step the others read — which is why there is no table of constants: a
  coefficient is a literal, an editable price is a field with a default, a sub-total is a hidden output.
- **Variables** are the `number`/`range`/`choice` fields plus the outputs above, named by the row's
  own slug with dashes turned into underscores. `FormFieldNamer` names fields and outputs in **one**
  namespace; `FormCrudController` hands the current ones to `assets/js/formula-variables.js`, which
  makes them buttons inserting a name at the cursor, and still spells them out in the outputs help.
- `Service\ExpressionEvaluator` is the only place a formula becomes a number.
  `Service\CalculatorExpressionLanguage` overrides `registerFunctions()` **without calling the
  parent** — that is what drops `constant()`/`enum()` — and keeps `abs ceil floor max min round`.
  **Never evaluate an expression in JavaScript**: one formula, one implementation.
- `Validator\ValidExpressions` checks every formula on **every** save, because relabelling a field
  renames the variable the formulas read. `ExpressionEvaluator::lint()` answers in the site's own
  language: which way the brackets fail to match, the mistyped name with the closest declared one
  beside it, and otherwise its own wording with the parser's message kept behind it.
- `Form::$outputsFirst` (`site_form.outputs_first`) is the admin's call on which column is read first
  — the results or the controls that move them. The template carries it as a class, the stylesheet
  reorders the same DOM, so nothing focusable is reached out of the order it is read in.
- `Controller\CalculatorController` (`ui_form_compute`) answers a recomputation as JSON: a read, so
  no CSRF, no session, and **outside** `limiter.ui_form` — a dragged slider would exhaust it.
- The results are rendered **server-side** before any script runs; `assets/js/calculator.js` only
  keeps them in step, debounced.
- A calculator gets **none** of the protections below: nothing is submitted.
- A calculator **travels**: `Management\FormExportProvider`/`FormImportProvider` (kind `site_form`)
  plug Forms into the **Export sync (everything)** shortcut and the **Import content** screen, and the
  Form index carries an "Export selection" batch action. A `Form`, its fields and its outputs are all
  matched by `name`; a row the payload drops is removed, except a **restricted** field, and
  `restricted` is only ever set on a row the import creates.

### Protections, already there

Shared by every public form, with **nothing to wire**: a rotating honeypot and a minimum submit delay
(`site-form-delay`), a site-wide GDPR information line (`url-privacy-policy`), a live MX/A DNS check on every
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
  `button`, `image`, `divider`, `spacer`, `fields_table`, `slot`), and **there is no loop**.
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

**A root-relative path does not survive the send.** A mailbox has no page to resolve `/medias/…`
against, so an `src` or an `href` starting with a single slash is a broken picture and a dead link the
moment the message leaves. `|absolute_urls(baseUrl)` (`Twig\AbsoluteUrlsExtension`) rewrites them
against the site's own address, applied to the whole rendered layout rather than template by template:
the paths come from `asset()`, from components building their own `src` and from addresses typed
relative in the back office, and the layout is the one place they all pass through. `//host/…` and
`data-src` are left alone, and an empty address leaves the html untouched.

The six address settings (`email-from`, `email-from-name`, `email-to`, `email-to-name`,
`email-reply-to`, `email-reply-to-name`) live in ConfigBundle and resolve on their own; an explicit
`to:` wins. `bcc` is a real blind copy, not to be confused with `copyToEmail`, which sends a **second**
message.

**Nothing is sent while the debug mode is on.** `email-debug` (a restricted config) plus
`ROLE_SUPER_ADMIN` makes `EmailService` render the message and stash its preview in the session
instead of sending it — anyone else keeps getting real sends. Whichever page follows shows it, the
layout rendering `<twig:c975LUi:Email:DebugPreview/>` above the flashes, so an email dispatched from a
message handler or a command is as visible as one a form sent. Nothing to implement anywhere: every
email of the site goes out through `EmailService::send()`, which is the only place the mode is read.
A send with no session to stash a preview in goes out for real rather than being destroyed silently.
It is switched from the dashboard's **"Enable / Disable"** row
(`Controller\Management\EmailDebugShortcutController`), whose tile stays warning-colored for as long
as the mode is on.

## A declared email, one row per language

`FormSeeder::ensureEmailTemplate()` seeds a template on the spot; **declaring** it instead is what makes it
reachable to everything else. Implement `Contract\EmailTemplateProviderInterface::getEmailTemplates()`
(auto-discovered by interface, no tag - see `EmailTemplateProviderPass`) and return `name => locale => blocks`:

```php
public function getEmailTemplates(): array
{
    return ['shop_order_confirmation' => ['fr' => [...blocks...], 'en' => [...]]];
}
```

One declaration, four readers - the seeder, `c975l:ui:email-templates:ensure`, `renderNamed()` and
`Management\EmailTemplateHealthCheckProvider`. A bundle that seeds its templates from inside its own installer
is invisible to the other three, which is how a site ends up quietly unable to send a password reset.

- **`EmailTemplate::$name` is unique *with* its `locale`**, not on its own: one e-mail is one name, written once
  per language the site answers in. `renderNamed(name, variables, locale)` takes the **recipient's** language -
  neither the request's nor the site's, a reminder going out from a nightly command - and
  `EmailTemplateRepository::findForRendering()` tries it, then the site's own, then whichever version exists.
- **`c975l:ui:email-templates:ensure` belongs in `deploy.yml`.** It is safe on every deployment and is the only
  thing that reaches a site built before your bundle gained that e-mail. It also **adopts** the rows a site had
  before e-mails carried a language, instead of seeding a duplicate beside each one - a generated migration
  brings the column and never the rows.
- **`renderNamed()` renders the declaration itself when the site has no row**, through `EmailTemplateFactory` -
  the one place a declaration becomes an `EmailTemplate`, shared with the seeder, which persists it. A template
  deleted in the back office is an uneditable e-mail rather than a missing one, so a missing row is a warning
  and not an error.
- **Put the default wording in your translation catalogue** and have the provider read it, rather than writing
  the sentences into the PHP: the same string then serves the seeded row, the fallback render and any language
  a translator adds later. Declare the locales your catalogues actually cover.

### Data blocks: what an email is *for*

`slot` and `fields_table` are the two kinds the **code** fills in (`EmailBlock::DATA_TYPES`, `isDataBlock()`).
An order confirmation without the order's lines confirms nothing.

- **A `slot` is a fragment the sending bundle rendered** - an order's lines, its delivery address - named by the
  block's `label` and handed over in the `slots` key of `$variables`. It is written out **raw**, deliberately
  kept away from `substitute()` and from the escaping every other block goes through, which is safe **only**
  because that markup comes from a bundle's own Twig and never from anything typed in the back office. A slot
  holding nothing renders nothing, so an order carrying no gift card shows no empty row.
- **On a restricted template, a data block moves but is never deleted.** `EmailTemplateCrudController` puts back
  any that a submission dropped, and `EmailBlockType` locks a saved one's kind and slot name (retyping a `slot`
  into a `text` is a deletion by another road). `email-data-block.js` takes the delete button out of the page,
  but the page is only the courtesy - the guarantee is server-side.
- **A declaration that grows after the sites were built** is backfilled: `ensure` appends the data blocks a
  template never had, once each, `EmailTemplate::$seededBlocks` recording what it has already been offered.
  That is the whole difference between a template that never received a block and one whose admin took it out.
  **Wording is never backfilled** - a sentence is the admin's to write and has no identity to match on.

## Attaching a file to an email

`Model\EmailAttachment` + `EmailSendRequest::$attachments`:

```php
new EmailSendRequest(
    subject: $subject, context: [], template: $template, to: $email,
    attachments: [new EmailAttachment('conditions-generales-de-vente.pdf', $pdf)],
);
```

The **bytes** and not a path: what is attached is commonly a document drawn for that very message and written
nowhere (see `PdfGeneratorInterface` in `c975l-media`). A copy sent to `copyToEmail` carries the same files - it
is the same message, sent to a second address. In debug mode the preview **names** the attached files and never
carries them, the preview living in the session.

**Attach rather than link where the law asks for a durable medium.** A distance seller owes its customer the
confirmation of the contract on a support the customer keeps (art. L221-13 du Code de la consommation); a link
points at a page that can be rewritten, which the CJEU said is not one (C-49/11).

### Which documents a named email carries is stored, not coded

The constructor above is the low-level path, for a caller already holding the file. For a **named** e-mail, the
answer lives in the database beside the blocks that make up its body - same rule as every sentence in it.

```php
class InvoiceAttachmentProvider implements EmailAttachmentProviderInterface
{
    public function getAttachmentKinds(): array
    {
        // Namespace the kind: it is stored as it is in the template's row
        return ['shop:invoice' => t('label.invoice', [], 'shop')];
    }

    public function createAttachment(string $kind, array $context): ?EmailAttachment
    {
        $basket = $context['basket'] ?? null;

        // null for the ordinary nothing-to-attach: a kind another provider owns, an order with no invoice yet
        return $basket instanceof Basket ? new EmailAttachment('facture.pdf', $this->draw($basket)) : null;
    }
}
```

- Auto-discovered, no tag (`Registry\EmailAttachmentRegistry`, `Compiler\EmailAttachmentProviderPass`).
- The label is a `TranslatableInterface`, so it carries its own domain - a shop's document is named in the shop's
  catalogue. The builder translates it once, a choice label being an array key.
- The admin ticks kinds per template in **Email templates → Attachments** (`EmailTemplate::$attachments`, a JSON
  column). **Nothing is ticked by default**: whether an order confirmation travels with the terms of sale is a
  shopkeeper's decision about their own shop, not a bundle's.
- The sender asks `EmailTemplateRenderer::attachmentsFor(name, context, locale)` and hands the result to
  `EmailSendRequest::$attachments`. See the basket e-mail factory of `c975l/payment-bundle`, which passes the
  order and the language it was placed in, so every basket e-mail gains the feature at once.
- Read off the site's **own row** only: an e-mail falling back on a declared body (a row deleted in the back-office)
  goes out alone until `c975l:ui:email-templates:ensure` seeds the row again.
- `Service\LegalDocumentAttachmentProvider` ships every `LegalModelCatalog` model as `legal:france/terms-of-sales`
  and friends, drawn through `Service\LegalDocument` - the page and the attached file are the same document, and
  the PDF is redrawn as soon as its text changes. The filename is written in the recipient's language.
- New column: `site_email_template.attachments`. Generate a migration on each site.


## Do not

- **Do not write a controller for a form.** It is a `Form` row and a `FormActionInterface`.
- **Do not create a fields table for your bundle's form.** Use `Form` / `FormField`.
- **Do not add your own honeypot, delay, captcha or rate limiter** — they are already on every form.
- **Do not overwrite an admin's edit when seeding.** `FormSeeder` only ever fills what is unset.
- **Do not write an email layout in a satellite bundle**, and do not `extends` a layout from an email
  template: send the body with `wrapLayout: true`.
- **Do not turn structured content into an `EmailTemplate`** — the blocks have no loop, and the
  structure would be lost without becoming editable.
- **Do not seed a bundle's e-mails from its own installer** — declare them through
  `EmailTemplateProviderInterface`, or the command, the renderer and the health check never see them.
- **Do not ship a Twig body beside a declared e-mail.** It reads like a safety net and is really a second
  copy of the same sentences, which drift. `renderNamed()` falling back on the declaration is the net.
- **Do not put anything typed in the back office into a `slot`** — it is rendered raw, unescaped.
- **Do not pass more than one body** to an `EmailSendRequest`.
- **Do not declare your own `email-*` address settings** when the six shared ones answer.
- **Do not leave the email debug mode on.** While it is, a `ROLE_SUPER_ADMIN` only ever gets a
  preview and no message leaves the site.
