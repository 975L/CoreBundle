<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Contract\AnalyticsProviderInterface;
use c975L\UiBundle\Service\AnalyticsTracker;
use Twig\Attribute\AsTwigFunction;

// What the tracker snippet, the layout's preconnect, the cookies policy and the bundles pushing a measure ask (see AnalyticsTracker)
class AnalyticsExtension
{
    public function __construct(
        private readonly AnalyticsTracker $analyticsTracker,
    ) {
    }

    #[AsTwigFunction('analytics_enabled')]
    public function isEnabled(): bool
    {
        return $this->analyticsTracker->isEnabled();
    }

    // The provider in use, for its slug picking its driver, its settings, its origin and its name told to visitors
    #[AsTwigFunction('analytics_provider')]
    public function getProvider(): ?AnalyticsProviderInterface
    {
        return $this->analyticsTracker->getProvider();
    }

    // Whether Matomo is the provider in use, kept for c975l/payment-bundle up to v6.25, which pushes its orders into Matomo's own queue - anything new asks analytics_enabled() and renders <twig:c975LUi:Analytics:Order>
    #[AsTwigFunction('matomo_enabled')]
    public function isMatomoEnabled(): bool
    {
        return 'matomo' === $this->analyticsTracker->getProvider()?->getSlug();
    }
}
