<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Form\Type\AccountDeleteType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

// The account's owner deleting it (GDPR right to erasure), anonymized exactly as c975l:config:users-cleanup does: the payments, invoices and credits referring to it stay for the accounting retention, and UserAnonymizedEvent lets the site detach what the account owns. No link anywhere, each site placing its own
class AccountDeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Explains what happens, then anonymizes the account once its address is typed again. Fully authenticated: a remembered session is not enough to erase an account
    #[Route('/account/delete', name: 'config_account_delete', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Request $request): Response
    {
        // A site whose User cannot be anonymized has nothing to offer here
        $user = $this->getUser();
        if (!$user instanceof InactivityAwareInterface) {
            throw $this->createNotFoundException();
        }

        // The site's owner is not a visitor erasing their data: anonymized, nobody left could hand ROLE_SUPER_ADMIN back from the back office
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AccountDeleteType::class, null, ['expected_email' => (string) $user->getEmail()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('@c975LConfig/account/delete.html.twig', ['form' => $form]);
        }

        // Same sequence as the cleanup command, so a listener detaching what the account owns runs before the flush
        $user->anonymize();
        $this->eventDispatcher->dispatch(new UserAnonymizedEvent($user));
        $this->entityManager->flush();

        // Logged out before the flash is added: the logout empties the session, and a flash added earlier would go with it. Its response is kept, carrying the cookies the firewall clears
        $response = $this->security->logout(false) ?? $this->redirect($request->getBasePath() . '/');
        $this->addFlash('success', $this->translator->trans('flash.account_deleted', [], 'config'));

        return $response;
    }
}
