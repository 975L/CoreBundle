<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Controller\Management\UrlMetadataCrudController;

// The "Social" section, and this bundle's own contribution to it. A second provider rather than a second section on MenuProvider: the interface gives one section per provider, and several providers sharing one is how the section is assembled anyway - four of them already share "Gestion".
// The section is declared here and not only by SocialBundle, so it exists on an app that does not have it: what an url says of itself is its summary for the social networks and its share image, and it would have nowhere to be listed on a site running Config+Ui alone. Its label is shipped in this bundle's own "social" catalogue for that very case, SocialBundle's merging with it when installed.
class SocialMenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        // Spelled exactly as SocialBundle spells it: MenuBuilder groups sections on "domain.label", so a single character apart would draw two "Social" headers instead of one
        return [
            'label' => 'label.social',
            'translation_domain' => 'social',
        ];
    }

    public function getMenus(): array
    {
        return [
            'url_metadata' => [
                'controller' => UrlMetadataCrudController::class,
                'label' => 'label.url_metadata',
                'translation_domain' => 'config',
                'icon' => 'fas fa-tags',
                // Same key as url_metadata_crud_index.html.twig's own explanatory text - one text, reused, not a separate onboarding-only string (see MenuProviderInterface::getMenus())
                'description' => 'label.info_url_metadata',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
