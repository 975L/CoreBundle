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

// One map service the "map" block can draw with (Leaflet over OpenStreetMap and Google Maps ship with this bundle) - auto-tagged, so a site or a bundle adding one only implements it, adds its slug to the choices of "ui-map-provider" and its driver to assets/js/map.js. Deliberately not a symfony/ux-map renderer: that package builds one renderer out of a compile-time DSN, which a provider picked in the back-office and a key held in the database cannot reach
#[AutoconfigureTag('c975l.map_provider')]
interface MapProviderInterface
{
    // What "ui-map-provider" names it by ("leaflet"), the key of its driver in assets/js/map.js and of its paragraph in the privacy policy model
    public function getSlug(): string;

    // Whether the map may only be drawn once the visitor has accepted the "content" category of a consent banner, for a service writing cookies of its own
    public function needsConsent(): bool;

    // Whether it can draw anything at all - a service missing its key renders as the list of places under it and nothing more
    public function isUsable(): bool;

    // What its driver draws with (a tile server, a key, its library's urls), written into the page: a browser key is public by nature
    /** @return array<string, scalar> */
    public function getSettings(): array;

    // The place on this service's own map, a link anyone opens with no key nor script
    public function getPlaceUrl(string $latitude, string $longitude): string;

    // An address searched on this service's own map, for a place known by its address alone
    public function getSearchUrl(string $address): string;

    // What a Content-Security-Policy has to allow for it, per directive - static, so the container parameters a site builds its policy from are known at compile time
    /** @return array{img: list<string>, script: list<string>, connect: list<string>} */
    public static function getOrigins(): array;
}
