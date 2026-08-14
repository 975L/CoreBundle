<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Tells the site that an account has just been created on it - the counterpart of the confirmation email the new user gets, for the owner who wants to know their site is being signed up to without watching the back-office. Sent to the site's own "email-to" address (the one every c975L bundle already sends to, see EmailService), no separate address to keep in sync, and only when "user-creation-notification" is on
class UserCreationNotifier
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EmailService $emailService,
        private readonly TranslatorInterface $translator,
        // The site's own language, not the visitor's: this email goes to the owner, who has no reason to read it in whatever locale the person signing up was browsing in
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    // Returns false when the notification is off, or when EmailService couldn't send it (no "email-from"/"email-to" seeded, mailer down) - a registration is never worth failing over a notification the visitor knows nothing about, so the caller is free to ignore the result
    public function notify(UserInterface $user): bool
    {
        if (!$this->configService->getBool($this->configService->get('user-creation-notification'))) {
            return false;
        }

        // Plain text, as every operational email this bundle sends (see BackupCommand): it goes to an administrator, not to a visitor, and gains nothing from a branded layout
        return $this->emailService->send(new EmailSendRequest(
            subject: $this->subject(),
            context: [],
            text: $this->translator->trans(
                'text.user_created',
                ['%email%' => $user->getUserIdentifier()],
                'config',
                $this->defaultLocale
            ),
        ));
    }

    // Prefixed with the site name when one is set, several sites otherwise sending an inbox the very same subject
    private function subject(): string
    {
        $label = $this->translator->trans('label.user_created', [], 'config', $this->defaultLocale);
        $siteName = (string) $this->configService->get('site-name');

        return '' === $siteName ? $label : $siteName . ' - ' . $label;
    }
}
