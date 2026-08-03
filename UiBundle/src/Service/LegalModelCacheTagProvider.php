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

// A legal_model's cached render has the site's own identity baked into it: LegalModelPlaceholders::substitute()
// resolves %site-name%, %site-contact-email%... inside the cached callback, and a Config save fires no
// Block/Media event for BlockCacheInvalidationListener to catch. This extra tag is what
// LegalPlaceholderCacheListener invalidates when one of those configs changes.
class LegalModelCacheTagProvider implements BlockCacheTagProviderInterface
{
    public const CACHE_TAG = 'legal_placeholders';

    public function getCacheTagResolvers(): array
    {
        return [
            'legal_model' => static fn (Block $block): array => [self::CACHE_TAG],
        ];
    }
}
