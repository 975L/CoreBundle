<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

// Generalizes what used to be c975L\ContactFormBundle\Service\EmailService (from/to/replyTo resolution with a ConfigService fallback, "receive a copy" support, debug preview for ROLE_SUPER_ADMIN) so any bundle can send an email from an EmailSendRequest - see SendEmailFormAction for the FormActionInterface provider built on top of this
class EmailService
{
    // Where the previews wait for the page that shows them - a session attribute and not a flash, the layout rendering every flash it finds as an alert and an email being a whole html document
    public const string DEBUG_PREVIEWS_KEY = 'ui_email_debug_previews';

    private ?string $lastError = null;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MailerInterface $mailer,
        private readonly \Twig\Environment $twig,
        private readonly EmailLayoutRegistry $emailLayoutRegistry,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    // Resolves an email+name pair: explicit value from the request if given, else the "$configKey"/"$configKey-name" ConfigService parameters, name itself falling back to the email if no "-name" parameter is seeded either
    private function resolveAddress(?string $email, ?string $name, string $configKey): ?Address
    {
        $email ??= $this->configFallback($configKey);
        if (null === $email) {
            return null;
        }

        $name ??= $this->configFallback($configKey . '-name') ?? $email;

        return new Address($email, $name);
    }

    private function configFallback(string $parameter): ?string
    {
        return $this->configService->hasParameter($parameter)
            ? ($this->configService->get($parameter) ?: null)
            : null;
    }

    // Builds the TemplatedEmail(s) for a request - the main one, plus a copy to $request->copyToEmail if set (its own Reply-To stripped, to avoid exposing the main recipient's address to the copy holder, and its Bcc too, the archive needing a single exemplary)
    // The one body the request carries, whichever of the three it names
    // A request carrying two would silently go out as whichever a chain tests first, so it fails as plainly as one carrying none
    private function applyBody(TemplatedEmail $email, EmailSendRequest $request): void
    {
        if (1 !== count(array_filter([$request->template, $request->html, $request->text], static fn (?string $body): bool => null !== $body))) {
            throw new \Exception('EmailSendRequest needs exactly one of "template", "html" or "text"');
        }

        if (null !== $request->html) {
            $email->html($request->html);

            return;
        }

        if (null !== $request->text) {
            // Sent as plain text, deliberately: an operational digest reads the same in every client and survives one that renders no HTML at all
            $email->text($request->text);

            return;
        }

        if (!$request->wrapLayout) {
            $email->htmlTemplate($request->template);

            return;
        }

        // Rendered here rather than left to the mailer, so the registry's layout can be wrapped around it - a bundle then ships the body of its email alone, and the branding stays wherever EmailLayoutProviderInterface is implemented (SiteBundle's when installed, none otherwise)
        $body = $this->twig->render($request->template, $request->context);
        $email->html($this->emailLayoutRegistry->wrap($body) ?? $body);
    }

    private function buildEmails(EmailSendRequest $request): array
    {
        $from = $this->resolveAddress($request->from, $request->fromName, 'email-from');
        $to = $this->resolveAddress($request->to, $request->toName, 'email-to');

        if (null === $from || null === $to) {
            throw new \Exception('Missing email parameter(s)');
        }

        $email = new TemplatedEmail();
        $email->subject($request->subject);
        $email->from($from);
        $email->to($to);

        $replyTo = $this->resolveAddress($request->replyTo, $request->replyToName, 'email-reply-to');
        if (null !== $replyTo) {
            $email->replyTo($replyTo);
        }

        $this->applyBody($email, $request);
        $email->context($request->context);

        // Attached after the body, and to the message rather than to the request: a copy sent elsewhere is a clone of this one, so it carries the same files - it is the same message, sent to a second address
        foreach ($request->attachments as $attachment) {
            $email->attach($attachment->content, $attachment->filename, $attachment->contentType);
        }

        if (null !== $request->bcc) {
            $email->bcc(new Address($request->bcc));
        }

        $emails = [$email];

        if (null !== $request->copyToEmail) {
            $copy = clone $email;
            $copy->to(new Address($request->copyToEmail));
            $copy->getHeaders()->remove('Reply-To');
            // The blind copy archives the message that was actually sent, not each of its copies - the clone would otherwise carry it too, and the archive would get two of everything
            $copy->getHeaders()->remove('Bcc');
            $emails[] = $copy;
        }

        return $emails;
    }

    // Sends the email(s), or stashes a rendered preview instead if the current user is ROLE_SUPER_ADMIN and "email-debug" is on - never both. Returns false and stashes the exception message (see getLastError()) on failure
    public function send(EmailSendRequest $request): bool
    {
        $this->lastError = null;
        $debug = $this->isDebugPreviewing();

        try {
            foreach ($this->buildEmails($request) as $email) {
                if ($debug) {
                    // A request built with "html" (see EmailSendRequest) has no template to re-render - its body is already the rendered result. A "text" one has no markup at all, so the preview shows it as it will be received, monospaced
                    $renderedEmail = match (true) {
                        null !== $email->getHtmlTemplate() => $this->twig->render($email->getHtmlTemplate(), $email->getContext()),
                        null !== $email->getHtmlBody() => (string) $email->getHtmlBody(),
                        default => sprintf('<pre style="white-space:pre-wrap;">%s</pre>', htmlspecialchars((string) $email->getTextBody())),
                    };
                    $this->stashDebugPreview($this->wrapDebugEmail($email, $renderedEmail));
                    continue;
                }
                $this->mailer->send($email);
            }

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    // Set only after a send() that returned false - the exception message, for the caller to surface however it likes (flash message, log...)
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // Read here and nowhere else, so whichever page follows the send shows the preview (see the Email:DebugPreview component the layout renders) - and the session being part of the condition, an email with nowhere to leave one is sent for real rather than destroyed silently
    private function isDebugPreviewing(): bool
    {
        return null !== $this->session()
            && $this->security->isGranted('ROLE_SUPER_ADMIN')
            && $this->configService->getBool($this->configService->get('email-debug'));
    }

    // Appended rather than written over: one submission can send an email and its copy, and a redirect can follow another send
    private function stashDebugPreview(string $preview): void
    {
        $session = $this->session();
        if (null === $session) {
            return;
        }

        $previews = $session->get(self::DEBUG_PREVIEWS_KEY, []);
        $previews[] = $preview;
        $session->set(self::DEBUG_PREVIEWS_KEY, $previews);
    }

    /**
     * Returns and clears the stashed debug previews, one per email that was rendered instead of sent.
     *
     * @return string[]
     */
    public function consumeDebugPreviews(): array
    {
        $session = $this->session();
        if (null === $session) {
            return [];
        }

        $previews = $session->get(self::DEBUG_PREVIEWS_KEY, []);
        $session->remove(self::DEBUG_PREVIEWS_KEY);

        return $previews;
    }

    // getSession() raises on a request that has none - a console command, a webhook - so the request is asked first
    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request && $request->hasSession() ? $request->getSession() : null;
    }

    // The names of what travels with the message, never its bytes: the preview is rendered into the session, and a document of a few hundred kilobytes stashed there would be carried by every request until the next page reads it
    private function formatDebugAttachments(TemplatedEmail $email): string
    {
        $names = array_map(static fn ($attachment): string => (string) $attachment->getFilename(), $email->getAttachments());

        return [] === $names ? '' : '<br>' . htmlspecialchars(sprintf('Attachments: %s', implode(', ', $names)));
    }

    // Inserts a debug banner with the subject, the addresses and the attached files right after <body>, keeping a single valid HTML document
    private function wrapDebugEmail(TemplatedEmail $email, string $renderedEmail): string
    {
        $banner = sprintf(
            '<div style="margin:0;padding:8px 16px;background:#e53e3e;color:#fff;font-family:sans-serif;font-weight:bold;">EMAIL DEBUG (not sent) — %s<br>%s%s</div>',
            htmlspecialchars($email->getSubject() ?? ''),
            $this->formatDebugAddresses($email),
            $this->formatDebugAttachments($email)
        );

        if (1 === preg_match('/<body[^>]*>/i', $renderedEmail)) {
            // _callback, not preg_replace: a "$1" in $banner would be read as a backreference
            return preg_replace_callback('/<body[^>]*>/i', static fn (array $matches): string => $matches[0] . $banner, $renderedEmail, 1);
        }

        return $banner . $renderedEmail;
    }

    // Formats From/To/Cc/Bcc addresses for the debug banner
    private function formatDebugAddresses(TemplatedEmail $email): string
    {
        $lines = [];
        foreach (['From' => $email->getFrom(), 'To' => $email->getTo(), 'Cc' => $email->getCc(), 'Bcc' => $email->getBcc()] as $label => $addresses) {
            if ([] === $addresses) {
                continue;
            }

            $lines[] = htmlspecialchars(sprintf('%s: %s', $label, implode(', ', array_map(
                static fn (Address $address) => '' !== $address->getName()
                    ? sprintf('%s <%s>', $address->getName(), $address->getAddress())
                    : $address->getAddress(),
                $addresses
            ))));
        }

        return implode('<br>', $lines);
    }
}
