<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Implement and alias this to geocode against something else than Nominatim - a national address API, a paid service, a table of places the site already holds
interface MapGeocoderInterface
{
    /**
     * Resolves a postal address into the coordinates a map draws a marker at.
     *
     * @return array{latitude: string, longitude: string}|null null when the address resolved to nothing, whatever the reason - unknown place, service down, quota reached
     */
    public function geocode(string $address): ?array;
}
