<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFunction;

// The one place telling whether a site measures its audience, asked by the tracker snippet and by every bundle pushing a measure of its own (an order, an event) so none of them sends to a tracker that never loads
class MatomoExtension
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The switch on, and both values a tracker url is built from filled - a half-filled configuration measures nothing
    #[AsTwigFunction('matomo_enabled')]
    public function isEnabled(): bool
    {
        return $this->configService->getBool($this->configService->get('site-enable-matomo'))
            && '' !== trim((string) $this->configService->get('site-matomo-url'))
            && '' !== trim((string) $this->configService->get('site-matomo-id'));
    }
}
