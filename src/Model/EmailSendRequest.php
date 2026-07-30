<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// What EmailService needs to build/send a TemplatedEmail (see EmailService::send()) - from/fromName/to/toName/replyTo/replyToName left null fall back to the site-wide "email-from"/"email-to"/"email-reply-to" (+ "-name") config keys, same convention every c975L bundle already uses. copyToEmail, when set, sends a second copy of the same email there (e.g. "receive a copy" on a contact-style form), with its own Reply-To stripped. Exactly one of "template" (a Twig path, rendered with "context") or "html" (already-rendered markup, e.g. from EmailTemplateRenderer) must be given - see EmailService::buildEmails()
final class EmailSendRequest
{
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
    ) {
    }
}
