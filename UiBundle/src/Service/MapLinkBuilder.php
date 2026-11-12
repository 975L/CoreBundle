<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// The address of a place on the map service the site draws with (see MapProviderResolver), built from what a "contact_details" block already holds - so an editor ticks a box instead of searching for their own business and pasting the url back. A plain link anyone opens: no key, no billing account and no script loaded into the page
class MapLinkBuilder
{
    // The postal fields that name a place, in the order an address is written
    private const array ADDRESS_PARTS = ['addressStreetAddress', 'addressPostalCode', 'addressLocality', 'addressCountryName'];

    public function __construct(
        private readonly MapProviderResolver $mapProviderResolver,
    ) {
    }

    public function build(array $data): ?string
    {
        $provider = $this->mapProviderResolver->getProvider();
        if (null === $provider) {
            return null;
        }

        // Coordinates first: they name one point, where an address is a search whose first answer the service picks
        $latitude = trim((string) ($data['latitude'] ?? ''));
        $longitude = trim((string) ($data['longitude'] ?? ''));

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return $provider->getPlaceUrl($latitude, $longitude);
        }

        $address = $this->address($data);

        return '' === $address ? null : $provider->getSearchUrl($address);
    }

    // The complement is left out on purpose: a building or a floor names nothing on a map, and only makes the search miss the street it belongs to
    private function address(array $data): string
    {
        $parts = [];

        foreach (self::ADDRESS_PARTS as $part) {
            $value = trim((string) ($data[$part] ?? ''));

            if ('' !== $value) {
                $parts[] = $value;
            }
        }

        // A country alone, or a postal code alone, points at no place worth a button - the street or the town is what makes the search one
        $hasPlace = '' !== trim((string) ($data['addressStreetAddress'] ?? '')) || '' !== trim((string) ($data['addressLocality'] ?? ''));

        return $hasPlace ? implode(', ', $parts) : '';
    }
}
