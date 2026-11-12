<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\UiBundle\Service\MapProviderResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

class MapExtension
{
    // What a marker and its bubble are built from, and all a page has any reason to publish. "icon" is the url of the image a single marker is drawn with, for a listing whose places are of several sorts and whose map is read by telling them apart - a monument from a stele, a shop from a workshop; a point naming none keeps the bundle's own pin, which is the usual case
    private const array DRAWN_KEYS = ['label', 'latitude', 'longitude', 'text', 'url', 'icon'];

    public function __construct(
        private readonly MapProviderResolver $mapProviderResolver,
        private readonly ?Security $security = null,
    ) {
    }

    // The places a map can actually draw, renumbered from zero. Both halves matter: a point saved without coordinates has nowhere to be drawn and would reach the payload as a marker at 0,0 - off the coast of Africa - and Twig's own "filter" keeps the original keys, which turns the encoded payload into a JSON object as soon as one point is dropped. The controller reads a JSON array, and silently draws nothing when handed anything else
    #[AsTwigFunction('ui_map_points')]
    public function points(array $points): array
    {
        $drawable = array_filter(
            $points,
            static fn ($point): bool => is_array($point)
                && is_numeric($point['latitude'] ?? null)
                && is_numeric($point['longitude'] ?? null),
        );

        // Only what the page actually draws with: the payload is written into an attribute anyone reads, and "mode", "address" and "geocodedAddress" are how the editor placed the point, not what a visitor is shown
        return array_values(array_map(
            static fn (array $point): array => array_intersect_key($point, array_flip(self::DRAWN_KEYS)),
            $drawable,
        ));
    }

    // Everything the map component has to write into its element, read off the provider in use (see MapProviderInterface) rather than restated in Twig - so adding a provider stays a class plus a driver in assets/js/map.js. Nothing in it depends on the visitor: the block is cached under MapCacheTagProvider::CACHE_TAG
    #[AsTwigFunction('ui_map_settings')]
    public function settings(): array
    {
        $provider = $this->mapProviderResolver->getProvider();

        return [
            'provider' => $provider?->getSlug() ?? '',
            'settings' => $provider?->getSettings() ?? [],
            'needsConsent' => $provider?->needsConsent() ?? false,
            // A provider missing its key draws nothing at all, so the component keeps the list of places on screen instead of a grey box the visitor cannot read
            'usable' => $provider?->isUsable() ?? false,
        ];
    }

    // Whether the page may say why a map is missing, asked by the layout and never by the cached map itself (see components/Map/_diagnostic.html.twig). A visitor is shown the list of places and nothing else - being told about a key or a security policy they cannot change is noise on the site's own content; whoever placed the block sees the reason where the map should be, rather than only in a health check they have no reason to open
    #[AsTwigFunction('ui_map_diagnostic')]
    public function diagnostic(): bool
    {
        return $this->security?->isGranted(BackOfficeAccessVoter::ACCESS) ?? false;
    }

    // The place on the provider's own map, the link that works whether or not the page ever drew a map of its own
    #[AsTwigFunction('ui_map_place_url')]
    public function placeUrl(array $point): string
    {
        return $this->mapProviderResolver->getProvider()?->getPlaceUrl((string) $point['latitude'], (string) $point['longitude']) ?? '';
    }
}
