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

// Implement to tell where a block of some kind is shown on the front end, so a bundle links to its own block without knowing which page the site put it on - SiteBundle answers with the published Page carrying it, anchor included. Consumed by the "block_page_url" Twig function
#[AutoconfigureTag('c975l.block_page_url_provider')]
interface BlockPageUrlProviderInterface
{
    // Null when this provider knows no page showing that kind, so the next one gets asked
    public function getBlockPageUrl(string $kind): ?string;
}
