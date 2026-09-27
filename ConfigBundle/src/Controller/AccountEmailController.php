<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\EmailChanger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

// Where the link sent to a new address lands (see EmailChanger). Logged in, the link being bound to the account that asked for it: opened in another browser, the login form brings the member back here
#[IsGranted('ROLE_USER')]
class AccountEmailController extends AbstractController
{
    public function __construct(
        private readonly EmailChanger $emailChanger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Replaces the address, then back to the account page with what happened
    #[Route('/account/email/confirm', name: EmailChanger::CONFIRM_ROUTE, methods: ['GET'])]
    public function confirm(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw $this->createNotFoundException();
        }

        try {
            $changed = $this->emailChanger->confirm($request, $user);
            $this->addFlash($changed ? 'success' : 'danger', $this->translator->trans($changed ? 'flash.email_changed' : 'flash.email_taken', [], 'config'));
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('danger', $this->translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
        }

        return $this->redirectToRoute('config_account');
    }
}
