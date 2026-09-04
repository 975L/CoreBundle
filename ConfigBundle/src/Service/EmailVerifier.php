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
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

// Moved from the app-copied scaffold (previously App\Security\EmailVerifier) so it's shared, tested bundle code instead of duplicated per app - see UPGRADE.md. Only relies on UserInterface::getUserIdentifier() (guaranteed) plus method_exists() duck-typing for getId()/setIsVerified()/setIsEnabled(), which aren't part of any Security interface - App\Entity\User (app-space, this bundle can't reference it directly) is expected to expose them.
class EmailVerifier
{
    // The admin-editable EmailTemplate this email is composed of, seeded by UserFormSeeder
    public const EMAIL_TEMPLATE = 'account_validation';

    // How long an address is left alone after one confirmation email, whoever asked for it - the same hour SymfonyCasts' ResetPasswordHelper holds a password-reset request for
    public const int COOLDOWN = 3600;

    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly EmailService $emailService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    // Rendered here rather than from a Twig file: the layout wrapping it comes from whichever bundle registers an EmailLayoutProviderInterface (SiteBundle's branded one when installed, UiBundle's plain fallback otherwise), so this bundle sends the same email whether or not a site foundation sits on top of it. Through EmailService, so registration gets the same "email-debug" preview as every other email. False when the template was renamed or deleted from the back-office, an empty email being worse than none - and false too where the address was written to less than an hour ago
    public function sendEmailConfirmation(string $verifyEmailRouteName, UserInterface $user, string $subject, string $to): bool
    {
        // The registration form answers the same thing whether or not the address is already taken, so anyone may post a stranger's address and have this email sent to them. The form's rate limiter counts the caller and nothing else, and one address after another out of one's own IPv6 block walks straight through it: the ceiling that matters here is the one on the mailbox being written to
        if ($this->withinCooldown($to)) {
            return false;
        }

        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $this->getUserId($user),
            $user->getUserIdentifier(),
            ['id' => $this->getUserId($user)]
        );

        $html = $this->emailTemplateRenderer->renderNamed(self::EMAIL_TEMPLATE, [
            'signed_url' => $signatureComponents->getSignedUrl(),
            'expires_at' => $this->translator->trans('text.link_expires_in', [], 'config')
                . ' ' . $this->translator->trans(
                    $signatureComponents->getExpirationMessageKey(),
                    $signatureComponents->getExpirationMessageData(),
                    'VerifyEmailBundle'
                ),
        ]);

        if (null === $html) {
            return false;
        }

        $this->markSent($to);

        return $this->emailService->send(new EmailSendRequest(
            subject: $subject,
            context: [],
            html: $html,
            to: $to,
        ));
    }

    // Whether this address was written to less than an hour ago, and is therefore left alone. Read before anything is composed, so a flood costs a cache lookup rather than an email
    private function withinCooldown(string $to): bool
    {
        return $this->cache->getItem($this->cooldownKey($to))->isHit();
    }

    // Marked only once the email is about to leave: a template an administrator renamed must not lock the address out for an hour on top of sending nothing
    private function markSent(string $to): void
    {
        $item = $this->cache->getItem($this->cooldownKey($to));
        $item->set(true);
        $item->expiresAfter(self::COOLDOWN);

        $this->cache->save($item);
    }

    // Hashed, an address holding characters PSR-6 reserves ("@" among them) and being no business of whoever reads a cache directory. Cased and spaced alike, or the very same mailbox would open a bucket per spelling
    private function cooldownKey(string $to): string
    {
        return 'c975l_email_confirmation_' . hash('xxh128', mb_strtolower(trim($to)));
    }

    public function handleEmailConfirmation(Request $request, UserInterface $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $this->getUserId($user), $user->getUserIdentifier());

        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }
        if (method_exists($user, 'setIsEnabled')) {
            $user->setIsEnabled(true);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    // App\Entity\User (app-space, this bundle can't reference it directly) is expected to expose getId() - duck-typed since it's not part of any Security interface
    private function getUserId(UserInterface $user): ?int
    {
        return method_exists($user, 'getId') ? $user->getId() : null;
    }
}
