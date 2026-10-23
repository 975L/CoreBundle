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

// The authorization code flow, written once for every provider: a login needs the provider's tokens for a few seconds, so nothing asks for offline access, stores a refresh token or caches anything
class OAuthLoginClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Whether this site holds the credentials this provider needs - which is also what decides that its button is displayed at all (see OAuthLoginProviderRegistry::enabled())
    public function isConfigured(OAuthLoginProviderInterface $provider): bool
    {
        return null !== $provider->getClientId() && null !== $provider->getClientSecret();
    }

    // Where the visitor is sent to consent. No "access_type=offline" and no forced prompt: a login wants the account, not a durable authorization to act for it later
    public function getAuthorizationUrl(OAuthLoginProviderInterface $provider, string $redirectUri, string $state): string
    {
        return $provider->getAuthorizationEndpoint() . '?' . http_build_query([
            'client_id' => $provider->getClientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $provider->getScope(),
            'state' => $state,
        ] + $provider->getAuthorizationParameters());
    }

    // Trades the code for the provider's tokens, handed whole to the provider. The redirect uri travels again, unchanged: providers check it matches the one the code was issued for
    /** @return array<string, mixed>|null */
    public function exchangeCodeForToken(OAuthLoginProviderInterface $provider, string $code, string $redirectUri): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $provider->getTokenEndpoint(), [
                'body' => [
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'client_id' => (string) $provider->getClientId(),
                    'client_secret' => (string) $provider->getClientSecret(),
                ],
                'timeout' => 15,
            ]);

            // Read before the status is judged: providers state the reason ("invalid_grant" on a replayed code) in the body, which an exception on the status alone would throw away
            $token = json_decode($response->getContent(false), true);
        } catch (\Throwable) {
            return null;
        }

        // A refusal comes back as an "error" body, with a 400 or even a 200: neither carries a token
        if (!is_array($token) || isset($token['error']) || (!isset($token['access_token']) && !isset($token['id_token']))) {
            return null;
        }

        return $token;
    }
}
