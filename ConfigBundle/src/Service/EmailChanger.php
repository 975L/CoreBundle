<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use App\Entity\User;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

// A member replacing their address from the account page: the new one confirmed before it replaces the old, so a typo never locks anybody out - the site's sole administrator first. No pending column: the new address travels in the signed link, bound to the current one, which also makes the link dead once used
class EmailChanger
{
    // The admin-editable EmailTemplates, seeded by UserFormSeeder: the link sent to the new address, then the notice sent to the old one
    public const string CONFIRM_TEMPLATE = 'account_email_change';
    public const string NOTICE_TEMPLATE = 'account_email_changed';

    public const string CONFIRM_ROUTE = 'config_account_email_confirm';

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly EmailService $emailService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Sends the confirmation link to the new address, and nothing when an account already holds it (this one included): the caller answers the same either way, as the registration form does
    public function request(UserInterface $user, string $newEmail): void
    {
        if ($this->isTaken($newEmail)) {
            return;
        }

        $this->emailVerifier->sendSignedLink(self::CONFIRM_TEMPLATE, self::CONFIRM_ROUTE, $user, ['email' => $newEmail], $this->translator->trans('label.account_email_change_heading', [], 'config'), $newEmail, $this->currentEmail($user));
    }

    // Replaces the address once the link is checked, then tells the old one. False when another account took the address in the meantime
    /** @throws VerifyEmailExceptionInterface a link altered, expired, or already used */
    public function confirm(Request $request, UserInterface $user): bool
    {
        // Declared by no interface, the setter living on the app's own entity (see the scaffold's User)
        if (!method_exists($user, 'setEmail')) {
            throw new \LogicException(sprintf('"%s" must declare setEmail() for its address to be changed.', $user::class));
        }

        $oldEmail = $this->currentEmail($user);
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $oldEmail);

        $newEmail = (string) $request->query->get('email');
        if ($this->isTaken($newEmail)) {
            return false;
        }

        $user->setEmail($newEmail);
        if (method_exists($user, 'setModification')) {
            $user->setModification(new \DateTime());
        }
        $this->entityManager->flush();

        $this->notify($oldEmail, $newEmail);

        return true;
    }

    // Whether an account already answers to that address
    public function isTaken(string $email): bool
    {
        return null !== $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    // The address itself, which the identifier is not on a site logging in by username
    private function currentEmail(UserInterface $user): string
    {
        return method_exists($user, 'getEmail') ? (string) $user->getEmail() : $user->getUserIdentifier();
    }

    // The old address told of the change, so a hijacked account does not go unnoticed. Nothing sent when the template was deleted from the back office
    private function notify(string $oldEmail, string $newEmail): void
    {
        $html = $this->emailTemplateRenderer->renderNamed(self::NOTICE_TEMPLATE, ['email' => $newEmail]);

        if (null !== $html) {
            $this->emailService->send(new EmailSendRequest(
                subject: $this->translator->trans('label.account_email_changed_heading', [], 'config'),
                context: [],
                html: $html,
                to: $oldEmail,
            ));
        }
    }
}
