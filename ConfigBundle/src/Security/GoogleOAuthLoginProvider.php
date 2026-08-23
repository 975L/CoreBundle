<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Model\OAuthIdentity;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// "Sign in with Google", the only provider this bundle ships - the first one worth having, most visitors holding a Google account already.
//
// Its credentials are the site's own (see the "login-google-oauth-*" configs and the "connecter-google" procedure): one Google project per site, so the consent screen carries the site's name rather than the agency's.
class GoogleOAuthLoginProvider implements OAuthLoginProviderInterface
{
    public const string AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const string TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public const string USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    // "openid email" and nothing else: both are non-sensitive scopes, which Google publishes without any review - asking for a name or a picture on top would buy a verification dossier for data a login doesn't need
    public const string SCOPE = 'openid email';

    // The logger is optional: an app without Monolog leaves it null and everything else works the same, only the refusals go unrecorded
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getKey(): string
    {
        return 'google';
    }

    public function getName(): string
    {
        return 'Google';
    }

    public function getAuthorizationEndpoint(): string
    {
        return self::AUTHORIZATION_ENDPOINT;
    }

    public function getTokenEndpoint(): string
    {
        return self::TOKEN_ENDPOINT;
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    public function getClientIdSlug(): string
    {
        return 'login-google-oauth-client-id';
    }

    public function getClientSecretSlug(): string
    {
        return 'login-google-oauth-client-secret';
    }

    // Asks the standard OpenID Connect userinfo endpoint rather than reading the id_token that came with the access token: both say the same thing here, and one http call is cheaper to hold than a JWT parser.
    //
    // Nothing is trusted from the answer beyond those two fields - and an address Google itself doesn't mark as verified is reported as such, leaving OAuthUserResolver to refuse it
    public function fetchIdentity(string $accessToken): ?OAuthIdentity
    {
        try {
            $response = $this->httpClient->request('GET', self::USERINFO_ENDPOINT, [
                'auth_bearer' => $accessToken,
                'timeout' => 15,
            ]);

            $identity = json_decode($response->getContent(false), true);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Google userinfo could not be read: ' . $exception->getMessage());

            return null;
        }

        if (!is_array($identity) || !isset($identity['email']) || !is_string($identity['email'])) {
            $this->logger?->warning('Google returned no email for an access token, the login can only be refused.');

            return null;
        }

        // Google answers a real boolean, other OpenID providers a "true" string: read the same way whatever they send, and anything else than a clear yes counts as no
        return new OAuthIdentity(
            $identity['email'],
            true === filter_var($identity['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }
}
