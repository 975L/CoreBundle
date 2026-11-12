<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AnalyticsProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Audience measurement whatever service performs it (see AnalyticsProviderInterface, "analytics-provider" naming the one in use): the one place telling whether a site measures its audience, asked by the tracker snippet and by every bundle pushing a measure of its own (an order) so none of them sends to a tracker that never loads
class AnalyticsTracker
{
    use ConfigChosenProviderTrait;

    /**
     * @param iterable<AnalyticsProviderInterface> $providers
     */
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        #[AutowireIterator('c975l.analytics_provider')]
        iterable $providers = [],
    ) {
        $this->indexProviders($providers);
    }

    // The provider "analytics-provider" names, when it is registered and configured: null measures nothing
    public function getProvider(): ?AnalyticsProviderInterface
    {
        $provider = $this->namedProvider('analytics-provider');

        return $provider instanceof AnalyticsProviderInterface && $provider->isConfigured() ? $provider : null;
    }

    public function isEnabled(): bool
    {
        return null !== $this->getProvider();
    }
}
