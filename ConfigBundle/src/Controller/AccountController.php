<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Account\AccountSectionBuilder;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Form\Type\AccountPasswordType;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\PasswordResetter;
use c975L\ConfigBundle\Service\SiteLocales;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

// The member's own page: the profile and its password drawn here, then every section the bundles and the site contribute (see AccountSectionProviderInterface). No link anywhere, the site placing it in its navbar from the back office (see LinkableRouteProvider)
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountSectionBuilder $sectionBuilder,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly PasswordResetter $passwordResetter,
        private readonly SiteLocales $siteLocales,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Every language the site declares, as PaymentBundle's order history: what is read here is the member's own data and the bundles' catalogues. Only a GET is moved to another language, a redirect would drop what a POST carries
    #[Route('/{_locale}/account', name: 'config_account_localized', requirements: ['_locale' => '%c975l_config.locales_pattern%'], methods: ['GET', 'POST'])]
    #[Route('/account', name: 'config_account', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $askedLanguage = $request->isMethod('GET') ? $this->negotiator->redirectToAskedLanguage($request, $this->siteLocales->all(), 'config_account') : null;
        if (null !== $askedLanguage) {
            return $this->negotiator->vary($request, $askedLanguage);
        }

        $user = $this->getUser();
        $form = $user instanceof PasswordAuthenticatedUserInterface ? $this->passwordForm($request) : null;

        if (null !== $form) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // A remembered session reads the page, it does not rewrite the password
                $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

                $this->passwordResetter->resetPassword($user, (string) $form->get('plainPassword')->getData());
                $this->addFlash('success', $this->translator->trans('flash.password_changed', [], 'config'));

                return $this->redirect($request->getRequestUri());
            }
        }

        return $this->negotiator->vary($request, $this->render('@c975LConfig/account/index.html.twig', [
            // What AccountDeleteController would refuse is not offered: a User it cannot anonymize, and the site's owner
            'deletable' => $user instanceof InactivityAwareInterface && !$this->isGranted('ROLE_SUPER_ADMIN'),
            'passwordForm' => $form,
            'sections' => $user instanceof UserInterface ? $this->sectionBuilder->getSections($user) : [],
        ]));
    }

    // Null for a session opened through a provider: its account holds a password nobody knows (see OAuthUserResolver), which its owner replaces through "forgot password"
    private function passwordForm(Request $request): ?FormInterface
    {
        return true === $request->getSession()->get(OAuthLoginController::SESSION_OAUTH_LOGIN) ? null : $this->createForm(AccountPasswordType::class);
    }
}
