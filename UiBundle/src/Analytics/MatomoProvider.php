<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Analytics;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\AnalyticsProviderInterface;

// Matomo, self-hosted, meant for the CNIL-exempt setup its configuration entry describes (anonymised IP, no cross-site matching, 13 months at most, Do Not Track honoured), which is why it runs outside the cookie banner
class MatomoProvider implements AnalyticsProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getSlug(): string
    {
        return 'matomo';
    }

    public function getName(): string
    {
        return 'Matomo';
    }

    // Both values a tracker url is built from filled is the switch - a half-filled configuration measures nothing
    public function isConfigured(): bool
    {
        return '' !== $this->getUrl() && '' !== $this->getSiteId();
    }

    // The instance url without its trailing slashes, "//matomo.js" defeating proxy cache de-duplication
    public function getSettings(): array
    {
        return ['url' => $this->getUrl(), 'id' => $this->getSiteId()];
    }

    // Origin only, a preconnect carrying no path
    public function getOrigin(): ?string
    {
        $parts = parse_url($this->getUrl());

        return isset($parts['scheme'], $parts['host']) ? $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') : null;
    }

    private function getUrl(): string
    {
        return rtrim(trim((string) $this->configService->get('analytics-matomo-url')), '/');
    }

    private function getSiteId(): string
    {
        return trim((string) $this->configService->get('analytics-matomo-id'));
    }
}
