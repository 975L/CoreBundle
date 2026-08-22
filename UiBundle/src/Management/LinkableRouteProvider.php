<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;

// Exposes the wishlist page as a SiteBundle Menu target (navbar/footer): it is the one public page of this bundle a visitor navigates to on purpose, and it is backed by no Page, so without this entry nothing in the back office could ever point at it
// The vote and toggle routes are left out: they answer a click and are never navigated to
class LinkableRouteProvider implements LinkableRouteProviderInterface
{
    public function getLinkableRoutes(): array
    {
        return [
            'ui_favorite_page' => [
                'label' => 'label.favorites',
                'translation_domain' => 'ui',
            ],
        ];
    }
}
