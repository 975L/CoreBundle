<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// The connector pattern's one shared step, for a service handed tagged providers and reading through $this->configService the key that names the one in use (AgeVerifier, CaptchaVerifier, AnalyticsTracker, MapProviderResolver): what each then asks of the provider stays its own
trait ConfigChosenProviderTrait
{
    /** @var array<string, object> */
    private array $providers = [];

    // Indexed by the slug each provider answers, a later one replacing an earlier one of the same slug
    /** @param iterable<object> $providers */
    private function indexProviders(iterable $providers): void
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getSlug()] = $provider;
        }
    }

    // The provider the key names, null for an unseeded key, an empty one or a slug nobody registered
    private function namedProvider(string $configKey): ?object
    {
        return $this->providers[(string) $this->configService->get($configKey)] ?? null;
    }
}
