<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Merges the overlays contributed by every PageOverlayProvider (e.g. UiBundle's Donovan panel), the same way DashboardWidgetBuilder merges the dashboard's cards
class PageOverlayBuilder
{
    public function __construct(
        private readonly iterable $pageOverlayProviders,
    ) {
    }

    public function getOverlays(): array
    {
        return ProviderMerger::merge($this->pageOverlayProviders, fn (PageOverlayProviderInterface $provider) => $provider->getPageOverlays());
    }
}
