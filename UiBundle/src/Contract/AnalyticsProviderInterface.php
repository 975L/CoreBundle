<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// One audience measurement service (Matomo ships with this bundle) - auto-tagged, so a site or a bundle adding one only implements it and names its slug in "analytics-provider". Its browser driver counts the page views and the orders AnalyticsTracker lets through; each provider reads its own configs, the slugs staying its business
#[AutoconfigureTag('c975l.analytics_provider')]
interface AnalyticsProviderInterface
{
    // What "analytics-provider" names it by ("matomo"), the key of its driver in assets/js/analytics.js and of its paragraph in the cookies policy model
    public function getSlug(): string;

    // The name the cookies policy tells visitors measures them ("Matomo")
    public function getName(): string;

    // Whether the site holds what it needs to send a measure - an unconfigured provider measures nothing
    public function isConfigured(): bool;

    // What its driver is started with (an instance url, a site id...), written into the page
    /** @return array<string, scalar> */
    public function getSettings(): array;

    // The third-party origin its script comes from, preconnected by the layout; null when it has none
    public function getOrigin(): ?string;
}
