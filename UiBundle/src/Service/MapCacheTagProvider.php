<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

// A map's cached render carries what ui_map_settings() read from the configuration - the provider, its key, whether it is usable - and a contact block links its address to that provider's map (map_place_url()): no Block/Media event covers either, so MapSettingsCacheListener invalidates this tag when one of the "ui-map-" entries changes
class MapCacheTagProvider implements BlockCacheTagProviderInterface
{
    public const string CACHE_TAG = 'ui_map_settings';

    public function getCacheTagResolvers(): array
    {
        return [
            'map' => static fn (Block $block): array => [self::CACHE_TAG],
            'contact_details' => static fn (Block $block): array => [self::CACHE_TAG],
        ];
    }
}
