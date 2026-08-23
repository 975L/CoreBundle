<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Service\OAuthLoginClient;

// Every provider installed, and among them the ones this site actually configured - the second being what the login page displays and what /connect/{provider} accepts.
//
// A site that filled no credentials enables nothing, which is the state all of them start in: the login page then looks exactly as it did before this existed.
class OAuthLoginProviderRegistry
{
    /**
     * @param iterable<OAuthLoginProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly OAuthLoginClient $oauthLoginClient,
    ) {
    }

    /**
     * The providers this site holds credentials for, in the order they were registered.
     *
     * @return array<OAuthLoginProviderInterface>
     */
    public function enabled(): array
    {
        $enabled = [];
        foreach ($this->providers as $provider) {
            if ($this->oauthLoginClient->isConfigured($provider)) {
                $enabled[] = $provider;
            }
        }

        return $enabled;
    }

    // Resolves what /connect/{provider} was asked for. An unknown key and a key whose credentials are missing answer the same nothing: an url guessed from a provider this site never enabled says no more than a typo does
    public function get(string $key): ?OAuthLoginProviderInterface
    {
        foreach ($this->enabled() as $provider) {
            if ($provider->getKey() === $key) {
                return $provider;
            }
        }

        return null;
    }
}
