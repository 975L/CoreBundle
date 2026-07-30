<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BundleStylesheetManagementProviderInterface;

class StylesheetManagementRegistry
{
    /** @var BundleStylesheetManagementProviderInterface[] */
    private array $providers = [];

    public function addProvider(BundleStylesheetManagementProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return string[] */
    public function all(): array
    {
        $stylesheets = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getManagementStylesheets() as $url) {
                if (!in_array($url, $stylesheets, true)) {
                    $stylesheets[] = $url;
                }
            }
        }

        return $stylesheets;
    }
}
