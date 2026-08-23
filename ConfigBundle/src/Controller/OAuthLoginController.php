<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Model\OAuthResolution;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use c975L\ConfigBundle\Service\OAuthLoginClient;
use c975L\ConfigBundle\Service\OAuthUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// "Sign in with Google" and where it comes back to, for every provider this site enabled - one pair of routes whatever their number, {provider} being resolved against what OAuthLoginProviderRegistry holds.
//
// No custom authenticator and nothing to declare in an application's security.yaml: the account is logged in programmatically once the provider answered, which is what lets this ship without touching a single file in the sites that install it. Security::login() runs the firewall's own user checker on the way, so an account disabled in the back office is refused here exactly as it is on the login form - said in its own words before that, see OAuthUserResolver.
class OAuthLoginController extends AbstractController
{
    private const string SESSION_STATE_PREFIX = 'config_oauth_state_';

    private const string SESSION_REDIRECT = 'config_oauth_redirect';

    public function __construct(
        private readonly OAuthLoginProviderRegistry $providerRegistry,
        private readonly OAuthLoginClient $oauthLoginClient,
        private readonly OAuthUserResolver $oauthUserResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Sends the visitor to the provider's consent screen
    #[Route('/connect/{provider}', name: 'config_oauth_connect', methods: ['GET'])]
    public function connect(Request $request, string $provider): Response
    {
        $oauthProvider = $this->providerRegistry->get($provider);

        // Unknown provider and provider this site never configured answer the same 404: an url guessed for one that isn't enabled says no more than a typo does
        if (null === $oauthProvider) {
            throw $this->createNotFoundException();
        }

        if ($this->getUser()) {
            return $this->redirect($this->afterLoginUrl());
        }

        // Where the visitor was when they clicked, so they come back to it rather than to the home page - the order they just paid for, typically (see PaymentBundle's account invitation). Held in the session and never carried through the provider, which would take a tampered value straight to the callback
        $redirect = $this->relativePath($request->query->get('redirect'));
        if (null === $redirect) {
            $request->getSession()->remove(self::SESSION_REDIRECT);
        } else {
            $request->getSession()->set(self::SESSION_REDIRECT, $redirect);
        }

        // Held in the session and checked on the way back: without it the callback would accept a code obtained by anyone able to make the visitor's browser follow a link
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set(self::SESSION_STATE_PREFIX . $oauthProvider->getKey(), $state);

        return $this->redirect($this->oauthLoginClient->getAuthorizationUrl(
            $oauthProvider,
            $this->redirectUri($oauthProvider->getKey()),
            $state,
        ));
    }

    // Where the provider sends the visitor back, code in hand
    #[Route('/connect/{provider}/check', name: 'config_oauth_check', methods: ['GET'])]
    public function check(Request $request, string $provider, Security $security): Response
    {
        $oauthProvider = $this->providerRegistry->get($provider);

        if (null === $oauthProvider) {
            throw $this->createNotFoundException();
        }

        $session = $request->getSession();
        $expected = $session->remove(self::SESSION_STATE_PREFIX . $oauthProvider->getKey());
        $code = $request->query->get('code');

        // Consumed whatever happens, a state being good for exactly one round trip
        if (!is_string($expected) || $expected !== $request->query->get('state') || !is_string($code)) {
            return $this->refuse('flash.oauth_login_failed');
        }

        $accessToken = $this->oauthLoginClient->exchangeCodeForAccessToken(
            $oauthProvider,
            $code,
            $this->redirectUri($oauthProvider->getKey()),
        );

        $identity = null === $accessToken ? null : $oauthProvider->fetchIdentity($accessToken);

        if (null === $identity) {
            return $this->refuse('flash.oauth_login_failed');
        }

        $resolution = $this->oauthUserResolver->resolve($identity);

        // Nothing went wrong technically in any of these, so each is said plainly: an address the provider wouldn't vouch for, a site that isn't taking new accounts, or an account this site has disabled
        if (null === $resolution->user) {
            return $this->refuse(match ($resolution->reason) {
                OAuthResolution::REASON_REGISTRATION_CLOSED => 'flash.oauth_registration_closed',
                OAuthResolution::REASON_ACCOUNT_DISABLED => 'flash.oauth_account_disabled',
                default => 'flash.oauth_email_not_verified',
            });
        }

        if ($resolution->passwordReset) {
            $this->addFlash('warning', $this->translator->trans('flash.oauth_password_reset', [], 'config'));
        }

        $redirect = $session->remove(self::SESSION_REDIRECT);
        $response = $security->login($resolution->user, 'form_login');

        // Where they clicked from wins over the firewall's own target path, which is where a form login would have gone. Read back through the same check as on the way in: a session can be written to by anything else running in the same app
        if (is_string($redirect) && null !== $this->relativePath($redirect)) {
            return $this->redirect($redirect);
        }

        // Whatever the success handler decided, the home page standing in when it has nothing to say
        return $response ?? $this->redirect($this->afterLoginUrl());
    }

    // The url the provider was told to come back to, which travels twice: providers check the code is traded for the very uri it was issued for
    private function redirectUri(string $provider): string
    {
        return $this->generateUrl('config_oauth_check', ['provider' => $provider], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * A path on this site and nothing else, or null.
     *
     * Anything else is dropped rather than corrected: "//example.com" and "https://example.com" are both an
     * absolute url a browser follows off-site, which is the whole of an open redirect - a login page handing
     * a visitor to whoever put the address in the link they clicked. A backslash goes with them, some browsers
     * having read it as a slash.
     */
    private function relativePath(mixed $path): ?string
    {
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return str_contains($path, '\\') ? null : $path;
    }

    private function refuse(string $message): Response
    {
        $this->addFlash('danger', $this->translator->trans($message, [], 'config'));

        return $this->redirect($this->loginUrl());
    }

    // app_login belongs to the application (the scaffold's SecurityController), so it's asked for rather than assumed: a site that renamed it lands on its home page instead of on an exception
    private function loginUrl(): string
    {
        try {
            return $this->generateUrl('app_login');
        } catch (RouteNotFoundException) {
            return $this->afterLoginUrl();
        }
    }

    private function afterLoginUrl(): string
    {
        return '/';
    }
}
