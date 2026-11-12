<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Map;

use c975L\UiBundle\Contract\MapProviderInterface;
use Symfony\Component\Asset\Packages;

// Leaflet over OpenStreetMap's tiles: no key, no cookie - the visitor's IP is all that reaches the tile server, which is why it needs no consent. The library is served by this bundle (public/js/leaflet.js, see config/vendor-assets.json), so a site opens its policy to the tile server alone
class LeafletProvider implements MapProviderInterface
{
    public function __construct(
        private readonly Packages $packages,
    ) {
    }

    public function getSlug(): string
    {
        return 'leaflet';
    }

    public function needsConsent(): bool
    {
        return false;
    }

    public function isUsable(): bool
    {
        return true;
    }

    // The library's urls asset()-resolved for the cache busting, and the credit the tile server's licence requires on the map itself
    public function getSettings(): array
    {
        return [
            'tileUrl' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
            'script' => $this->packages->getUrl('bundles/c975lui/js/leaflet.js'),
            'stylesheet' => $this->packages->getUrl('bundles/c975lui/css/leaflet.css'),
        ];
    }

    public function getPlaceUrl(string $latitude, string $longitude): string
    {
        return \sprintf('https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=16/%1$s/%2$s', rawurlencode($latitude), rawurlencode($longitude));
    }

    public function getSearchUrl(string $address): string
    {
        return 'https://www.openstreetmap.org/search?query=' . rawurlencode($address);
    }

    public static function getOrigins(): array
    {
        return ['img' => ['https://tile.openstreetmap.org'], 'script' => [], 'connect' => []];
    }
}
