<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Contract\BlockPageUrlProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Attribute\AsTwigFunction;

// Where the site shows a block of a given kind
class BlockPageUrlExtension
{
    /** @param iterable<BlockPageUrlProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator('c975l.block_page_url_provider')]
        private readonly iterable $providers,
    ) {
    }

    // The first answer a provider gives, null when no page shows that kind - the caller then links nowhere rather than to a guess
    #[AsTwigFunction('block_page_url')]
    public function getBlockPageUrl(string $kind): ?string
    {
        foreach ($this->providers as $provider) {
            $url = $provider->getBlockPageUrl($kind);
            if (null !== $url) {
                return $url;
            }
        }

        return null;
    }
}
