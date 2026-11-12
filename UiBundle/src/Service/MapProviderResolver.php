<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\MapProviderInterface;
use c975L\UiBundle\Map\GoogleMapsProvider;
use c975L\UiBundle\Map\LeafletProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// The map provider "ui-map-provider" names, whatever it is (see MapProviderInterface): what the map component, the contact links, the privacy policy and the CSP health check ask
class MapProviderResolver
{
    use ConfigChosenProviderTrait;

    /**
     * @param iterable<MapProviderInterface> $providers
     */
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        #[AutowireIterator('c975l.map_provider')]
        iterable $providers = [],
    ) {
        $this->indexProviders($providers);
    }

    // Falls back to Leaflet rather than to nothing on an unknown value: a map is a page's content, and a site whose setting names a provider gone keeps a drawn map
    public function getProvider(): ?MapProviderInterface
    {
        $provider = $this->namedProvider('ui-map-provider') ?? $this->providers['leaflet'] ?? null;

        return $provider instanceof MapProviderInterface ? $provider : null;
    }

    // Every origin the shipped providers need, per directive, for the container parameters a site builds its policy from - known at compile time, a provider added later naming its own in its README
    /** @return array{img: list<string>, script: list<string>, connect: list<string>} */
    public static function shippedOrigins(): array
    {
        $origins = ['img' => [], 'script' => [], 'connect' => []];
        foreach ([LeafletProvider::class, GoogleMapsProvider::class] as $provider) {
            foreach ($provider::getOrigins() as $directive => $list) {
                $origins[$directive] = array_values(array_unique([...$origins[$directive], ...$list]));
            }
        }

        return $origins;
    }
}
