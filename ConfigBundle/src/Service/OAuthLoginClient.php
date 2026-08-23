<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// The authorization code flow, written once for every provider - same shape as SocialBundle's GoogleOAuthClient, which connects a site to its Google Business Profile.
//
// Simpler than that one on purpose: a login needs the access token for the few seconds it takes to read an email address back, so nothing asks for offline access, nothing stores a refresh token, and nothing is cached. What the provider says is used and dropped.
class OAuthLoginClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Whether this site holds the credentials this provider needs - which is also what decides that its button is displayed at all (see OAuthLoginProviderRegistry::enabled())
    public function isConfigured(OAuthLoginProviderInterface $provider): bool
    {
        return null !== $this->clientId($provider) && null !== $this->clientSecret($provider);
    }

    // Where the visitor is sent to consent. No "access_type=offline" and no forced prompt: a login wants the account, not a durable authorization to act for it later
    public function getAuthorizationUrl(OAuthLoginProviderInterface $provider, string $redirectUri, string $state): string
    {
        return $provider->getAuthorizationEndpoint() . '?' . http_build_query([
            'client_id' => $this->clientId($provider),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $provider->getScope(),
            'state' => $state,
        ]);
    }

    // Trades the code the callback received for the short-lived token that reads the identity back. The redirect uri travels again, unchanged: providers check it matches the one the code was issued for
    public function exchangeCodeForAccessToken(OAuthLoginProviderInterface $provider, string $code, string $redirectUri): ?string
    {
        try {
            $response = $this->httpClient->request('POST', $provider->getTokenEndpoint(), [
                'body' => [
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'client_id' => (string) $this->clientId($provider),
                    'client_secret' => (string) $this->clientSecret($provider),
                ],
                'timeout' => 15,
            ]);

            // Read before the status is judged: providers state the reason ("invalid_grant" on a replayed code) in the body, which an exception on the status alone would throw away
            $token = json_decode($response->getContent(false), true);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($token) || !isset($token['access_token']) || !is_string($token['access_token'])) {
            return null;
        }

        return $token['access_token'];
    }

    private function clientId(OAuthLoginProviderInterface $provider): ?string
    {
        return $this->trimmedConfig($provider->getClientIdSlug());
    }

    private function clientSecret(OAuthLoginProviderInterface $provider): ?string
    {
        return $this->trimmedConfig($provider->getClientSecretSlug());
    }

    // A credential pasted from a console carries whatever whitespace came with it, and an empty string is not a value
    private function trimmedConfig(string $slug): ?string
    {
        $value = $this->configService->get($slug);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
