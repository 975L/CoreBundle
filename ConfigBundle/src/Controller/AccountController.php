<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use App\Entity\User;
use c975L\ConfigBundle\Account\AccountSectionBuilder;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Form\Type\AccountDeleteType;
use c975L\ConfigBundle\Form\Type\AccountEmailType;
use c975L\ConfigBundle\Form\Type\AccountPasswordType;
use c975L\ConfigBundle\Form\Type\AccountProfileType;
use c975L\ConfigBundle\Form\Type\AccountSessionsType;
use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use c975L\ConfigBundle\Service\EmailChanger;
use c975L\ConfigBundle\Service\LocalizedRouteNegotiator;
use c975L\ConfigBundle\Service\PasswordResetter;
use c975L\ConfigBundle\Service\SiteLocales;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

// The member's own page: the profile, the address and the password drawn here, then every section the bundles and the site contribute (see AccountSectionProviderInterface). No link anywhere, the site placing it in its navbar from the back office (see LinkableRouteProvider)
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountSectionBuilder $sectionBuilder,
        private readonly EmailChanger $emailChanger,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocalizedRouteNegotiator $negotiator,
        private readonly PasswordResetter $passwordResetter,
        private readonly OAuthLoginProviderRegistry $providerRegistry,
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
        $loginProvider = $this->loginProvider($request);
        $forms = $this->forms($user, null !== $loginProvider);

        $sent = $this->changePassword($forms['passwordForm'], $request, $user) ?? $this->changeEmail($forms['emailForm'], $request, $user) ?? $this->saveProfile($forms['profileForm'], $request, $user) ?? $this->closeOtherSessions($forms['sessionsForm'], $request, $user);
        if (null !== $sent) {
            return $sent;
        }

        return $this->negotiator->vary($request, $this->render('@c975LConfig/account/index.html.twig', $forms + [
            'deleteForm' => $this->deleteForm($user),
            'loginProvider' => $loginProvider,
            // The providers this site signs in through, offered to a member who came with their password - the account is found by its address, so nothing has to be linked first
            'otherProviders' => null !== $loginProvider ? [] : array_map(static fn (OAuthLoginProviderInterface $provider): string => $provider->getName(), $this->providerRegistry->enabled()),
            'sections' => $user instanceof UserInterface ? $this->sectionBuilder->getSections($user) : [],
        ]));
    }

    // The page's forms, null where they do not apply. A session opened through a provider has no password, address or device form: its account holds a password nobody knows (see OAuthUserResolver), and a new address would lose the provider's way back to it, which matches by address
    /** @return array{passwordForm: ?FormInterface, emailForm: ?FormInterface, sessionsForm: ?FormInterface, profileForm: ?FormInterface} */
    private function forms(mixed $user, bool $oauth): array
    {
        $passwordForm = !$oauth && $user instanceof PasswordAuthenticatedUserInterface ? $this->createForm(AccountPasswordType::class) : null;

        return [
            'passwordForm' => $passwordForm,
            'emailForm' => null !== $passwordForm && method_exists($user, 'setEmail') ? $this->createForm(AccountEmailType::class) : null,
            'sessionsForm' => null !== $passwordForm ? $this->createForm(AccountSessionsType::class) : null,
            'profileForm' => $this->profileForm($user),
        ];
    }

    // The new password hashed and saved, null while the form is not sent valid
    private function changePassword(?FormInterface $form, Request $request, mixed $user): ?Response
    {
        if (null === $form || !$user instanceof PasswordAuthenticatedUserInterface || !$this->submitted($form, $request)) {
            return null;
        }

        $this->passwordResetter->resetPassword($user, (string) $form->get('plainPassword')->getData());

        return $this->done($request, 'flash.password_changed');
    }

    // The link sent to the new address, with the same answer whether or not it is free, as the registration form gives
    private function changeEmail(?FormInterface $form, Request $request, mixed $user): ?Response
    {
        if (null === $form || !$user instanceof UserInterface || !$this->submitted($form, $request)) {
            return null;
        }

        $this->emailChanger->request($user, (string) $form->get('newEmail')->getData());

        return $this->done($request, 'flash.email_change_sent');
    }

    // The site's own fields saved. A refused submission has already written onto the logged-in User: reloaded, so no flush later in the request saves it
    private function saveProfile(?FormInterface $form, Request $request, mixed $user): ?Response
    {
        if (null === $form || !$user instanceof User) {
            return null;
        }

        if (!$this->submitted($form, $request)) {
            if ($form->isSubmitted()) {
                $this->entityManager->refresh($user);
            }

            return null;
        }

        $user->setModification(new \DateTime());
        $this->entityManager->flush();

        return $this->done($request, 'flash.profile_saved');
    }

    // Every other device signed out: the same password hashed again, which Symfony reads as a changed account on each session and remember-me cookie made before. This session keeps going, its token holding the very User just updated
    private function closeOtherSessions(?FormInterface $form, Request $request, mixed $user): ?Response
    {
        if (null === $form || !$user instanceof PasswordAuthenticatedUserInterface || !$this->submitted($form, $request)) {
            return null;
        }

        $this->passwordResetter->resetPassword($user, (string) $form->get('currentPassword')->getData(), notify: false);

        return $this->done($request, 'flash.sessions_closed');
    }

    // Back to the page with that flash, the change made
    private function done(Request $request, string $flash): Response
    {
        $this->addFlash('success', $this->translator->trans($flash, [], 'config'));

        return $this->redirect($request->getRequestUri());
    }

    // The provider that opened this session, null for a password login. A session flagged before the name was kept holds true, read as an unnamed provider
    private function loginProvider(Request $request): ?string
    {
        $flag = $request->getSession()->get(OAuthLoginController::SESSION_OAUTH_LOGIN);

        return match (true) {
            \is_string($flag) => $flag,
            true === $flag => 'OAuth',
            default => null,
        };
    }

    // Whether that form was sent and is valid. A remembered session reads the page, it does not rewrite the account
    private function submitted(FormInterface $form, Request $request): bool
    {
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return false;
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return true;
    }

    // The confirmation AccountDeleteController reads, drawn in the page's dialog. Null where it would refuse: a User it cannot anonymize, and the site's owner
    private function deleteForm(mixed $user): ?FormInterface
    {
        if (!$user instanceof InactivityAwareInterface || $this->isGranted('ROLE_SUPER_ADMIN')) {
            return null;
        }

        return $this->createForm(AccountDeleteType::class, null, ['expected_email' => (string) $user->getEmail()]);
    }

    // Null when the site's User holds no column of its own (see AccountProfileType), or is not the one the type is bound to
    private function profileForm(mixed $user): ?FormInterface
    {
        if (!$user instanceof User) {
            return null;
        }

        $form = $this->createForm(AccountProfileType::class, $user);

        return count($form) > 0 ? $form : null;
    }
}
