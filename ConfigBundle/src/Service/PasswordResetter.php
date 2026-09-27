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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Hashes the new password and persists it - the "what happens once the change-password form is valid" step, extracted out of the app-copied scaffold's ResetPasswordController (see UPGRADE.md). Token generation/validation (SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface) stays called directly from the controller: it's a thin pass-through already, wrapping it here would add indirection without extracting any real logic.
class PasswordResetter
{
    // The admin-editable EmailTemplate telling the account its password changed, seeded by UserFormSeeder
    public const string NOTICE_TEMPLATE = 'account_password_changed';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // $notify false for the same password hashed again (see AccountController's "other devices"), which changes nothing the member has to be told about
    public function resetPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword, bool $notify = true): void
    {
        // Symfony's interface only declares getPassword(), the setter living on the app's own entity (see the scaffold's User): checked rather than assumed, so a divergent entity says so instead of fataling
        if (!method_exists($user, 'setPassword')) {
            throw new \LogicException(sprintf('"%s" must declare setPassword() for its password to be reset.', $user::class));
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        if (method_exists($user, 'setModification')) {
            $user->setModification(new \DateTime());
        }

        $this->entityManager->flush();

        if ($notify) {
            $this->notify($user);
        }
    }

    // The account told its password changed, from its own page or through "forgot password", so a hijacked one does not go unnoticed. Nothing sent without an address, or when the template was deleted from the back office
    private function notify(PasswordAuthenticatedUserInterface $user): void
    {
        $to = method_exists($user, 'getEmail') ? (string) $user->getEmail() : '';
        $html = str_contains($to, '@') ? $this->emailTemplateRenderer->renderNamed(self::NOTICE_TEMPLATE) : null;

        if (null !== $html) {
            $this->emailService->send(new EmailSendRequest(
                subject: $this->translator->trans('label.account_password_changed_heading', [], 'config'),
                context: [],
                html: $html,
                to: $to,
            ));
        }
    }
}
