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

// Whether the layout declares the site's manifest and registers its service worker (see PwaController)
class PwaExtension
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    #[AsTwigFunction('pwa_enabled')]
    public function isEnabled(): bool
    {
        return $this->configService->getBool($this->configService->get('ui-pwa-enabled'));
    }
}
