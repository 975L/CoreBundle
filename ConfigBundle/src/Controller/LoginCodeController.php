<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Form\Type\LoginCodeType;
use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Security\TrustedDevice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The page an admin types the emailed login code on (see LoginCodeSubscriber), the browser then remembered by TrustedDevice
class LoginCodeController extends AbstractController
{
    public const string ROUTE = 'config_login_code';

    public const string RESEND_ROUTE = 'config_login_code_resend';

    public function __construct(
        private readonly LoginCode $loginCode,
        private readonly TrustedDevice $trustedDevice,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The code typed, the login going on to where it was heading once it is right
    #[Route('/login/code', name: self::ROUTE, methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (null === $user || !$this->loginCode->isPending($request->getSession())) {
            return $this->redirect($request->getBasePath() . '/');
        }

        $form = $this->createForm(LoginCodeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->check($request, $user, $form);
            if (null !== $response) {
                return $response;
            }
        }

        return $this->render('@c975LConfig/security/login_code.html.twig', ['form' => $form]);
    }

    // Another code, once a minute at most
    #[Route('/login/code/resend', name: self::RESEND_ROUTE, methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $user = $this->getUser();
        $resent = null !== $user
            && $this->isCsrfTokenValid('login_code_resend', (string) $request->request->get('_token'))
            && $this->loginCode->resend($request->getSession(), $user);

        $this->addFlash($resent ? 'success' : 'warning', $this->translator->trans($resent ? 'flash.login_code_resent' : 'flash.login_code_not_resent', [], 'config'));

        return $this->redirectToRoute(self::ROUTE);
    }

    // The redirect once the code is right, the logout once too many were wrong, null with the error on the form otherwise
    private function check(Request $request, UserInterface $user, FormInterface $form): ?Response
    {
        $session = $request->getSession();
        $result = $this->loginCode->verify($session, (string) $form->get('code')->getData());

        if (LoginCode::VALID === $result) {
            $response = $this->redirect($this->loginCode->finish($session));
            $response->headers->setCookie($this->trustedDevice->cookie($request, $user));

            return $response;
        }

        if (LoginCode::EXHAUSTED === $result) {
            $this->loginCode->finish($session);
            $response = $this->security->logout(false) ?? $this->redirect($request->getBasePath() . '/');
            $this->addFlash('danger', $this->translator->trans('flash.login_code_exhausted', [], 'config'));

            return $response;
        }

        $form->get('code')->addError(new FormError($this->translator->trans('text.login_code_' . $result, [], 'config')));

        return null;
    }
}
