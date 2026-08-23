<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// What EmailService needs to build/send a TemplatedEmail (see EmailService::send()) - from/fromName/to/toName/replyTo/replyToName left null fall back to the site-wide "email-from"/"email-to"/"email-reply-to" (+ "-name") config keys, same convention every c975L bundle already uses. copyToEmail, when set, sends a second copy of the same email there (e.g. "receive a copy" on a contact-style form), with its own Reply-To stripped. Exactly one of "template" (a Twig path, rendered with "context"), "html" (already-rendered markup, e.g. from EmailTemplateRenderer) or "text" must be given - see EmailService::buildEmails()
final class EmailSendRequest
{
    /**
     * @param ?string           $bcc         a blind copy, invisible to the recipient - unlike copyToEmail, which sends a second, separate message. What a shop keeping a record of every order email needs
     * @param bool              $wrapLayout  only meaningful alongside "template": renders it and wraps the result through EmailLayoutRegistry before sending, so a bundle's own email body comes out in the site's branded layout without that template having to {% extends %} a path it would have to know. The body template must then render the body alone, with no layout of its own. Ignored on an "html" request, already-rendered markup being the caller's own call
     * @param ?string           $text        a plain-text body, sent as such - no template, no layout, nothing to render. What an operational digest written by a command needs: it goes to an administrator, not to a visitor, and gains nothing from branding
     * @param EmailAttachment[] $attachments files travelling with the message. Appended last, like $text before it, so the positional signature stays what it was. A copy sent to copyToEmail carries them too - it is the same message, sent to a second address
     */
    public function __construct(
        public readonly string $subject,
        public readonly array $context,
        public readonly ?string $template = null,
        public readonly ?string $html = null,
        public readonly ?string $from = null,
        public readonly ?string $fromName = null,
        public readonly ?string $to = null,
        public readonly ?string $toName = null,
        public readonly ?string $replyTo = null,
        public readonly ?string $replyToName = null,
        public readonly ?string $copyToEmail = null,
        public readonly ?string $bcc = null,
        public readonly bool $wrapLayout = false,
        public readonly ?string $text = null,
        public readonly array $attachments = [],
    ) {
    }
}
