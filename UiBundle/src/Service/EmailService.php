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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

// Generalizes what used to be c975L\ContactFormBundle\Service\EmailService (from/to/replyTo resolution with a ConfigService fallback, "receive a copy" support, debug preview for ROLE_SUPER_ADMIN) so any bundle can send an email from an EmailSendRequest - see SendEmailFormAction for the FormActionInterface provider built on top of this
class EmailService
{
    /** @var string[] */
    private array $debugPreviews = [];
    private ?string $lastError = null;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MailerInterface $mailer,
        private readonly \Twig\Environment $twig,
        private readonly EmailLayoutRegistry $emailLayoutRegistry,
        private readonly Security $security,
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

        // A request carrying two bodies would silently go out as whichever this chain tests first, so it fails as plainly as one carrying none
        if (count(array_filter([$request->template, $request->html, $request->text], static fn (?string $body): bool => null !== $body)) > 1) {
            throw new \Exception('EmailSendRequest needs exactly one of "template", "html" or "text"');
        }

        if (null !== $request->html) {
            $email->html($request->html);
        } elseif (null !== $request->text) {
            // Sent as plain text, deliberately: an operational digest reads the same in every client and survives one that renders no HTML at all
            $email->text($request->text);
        } elseif (null !== $request->template && $request->wrapLayout) {
            // Rendered here rather than left to the mailer, so the registry's layout can be wrapped around it - a bundle then ships the body of its email alone, and the branding stays wherever EmailLayoutProviderInterface is implemented (SiteBundle's when installed, none otherwise)
            $body = $this->twig->render($request->template, $request->context);
            $email->html($this->emailLayoutRegistry->wrap($body) ?? $body);
        } elseif (null !== $request->template) {
            $email->htmlTemplate($request->template);
        } else {
            throw new \Exception('EmailSendRequest needs exactly one of "template", "html" or "text"');
        }
        $email->context($request->context);

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

        try {
            foreach ($this->buildEmails($request) as $email) {
                if ($this->security->isGranted('ROLE_SUPER_ADMIN') && $this->configService->getBool($this->configService->get('email-debug'))) {
                    // A request built with "html" (see EmailSendRequest) has no template to re-render - its body is already the rendered result. A "text" one has no markup at all, so the preview shows it as it will be received, monospaced
                    $renderedEmail = match (true) {
                        null !== $email->getHtmlTemplate() => $this->twig->render($email->getHtmlTemplate(), $email->getContext()),
                        null !== $email->getHtmlBody() => (string) $email->getHtmlBody(),
                        default => sprintf('<pre style="white-space:pre-wrap;">%s</pre>', htmlspecialchars((string) $email->getTextBody())),
                    };
                    $this->debugPreviews[] = $this->wrapDebugEmail($email, $renderedEmail);
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

    // Returns and clears the stashed debug previews, one per email that was rendered instead of sent
    public function consumeDebugPreview(): ?string
    {
        if ([] === $this->debugPreviews) {
            return null;
        }

        $preview = implode('<hr style="margin:24px 0;border:none;border-top:2px dashed #999;">', $this->debugPreviews);
        $this->debugPreviews = [];

        return $preview;
    }

    // Inserts a debug banner with the subject and addresses right after <body>, keeping a single valid HTML document
    private function wrapDebugEmail(TemplatedEmail $email, string $renderedEmail): string
    {
        $banner = sprintf(
            '<div style="margin:0;padding:8px 16px;background:#e53e3e;color:#fff;font-family:sans-serif;font-weight:bold;">EMAIL DEBUG (not sent) — %s<br>%s</div>',
            htmlspecialchars($email->getSubject() ?? ''),
            $this->formatDebugAddresses($email)
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
