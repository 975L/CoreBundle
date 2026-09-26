<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\SiteLocales;

// Exposes the backoffice dashboard's own route, so it can be picked as a SiteBundle Menu item (navbar/footer) - e.g. a small "Admin" link, and the member's own page. Access to either stays gated by its controller, the menu link is just a shortcut to it
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    public function __construct(private readonly SiteLocales $siteLocales)
    {
    }

    public function getLinkableRoutes(): array
    {
        return [
            'management' => [
                'label' => 'label.dashboard',
                'translation_domain' => 'config',
            ],
            // In every language the site declares, the member's own data not being content written in one of them (see AccountController)
            'config_account' => [
                'label' => 'label.my_account',
                'translation_domain' => 'config',
                'locales' => $this->siteLocales->all(),
            ],
        ];
    }
}
