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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Map\MapProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

class MapExtension
{
    // What a marker and its bubble are built from, and all a page has any reason to publish
    private const array DRAWN_KEYS = ['label', 'latitude', 'longitude', 'text', 'url'];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
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

    // Everything the map component has to write into its element, read off the site's own settings and off the provider enum rather than restated in Twig - so adding a provider stays a case in MapProvider plus a branch in assets/js/map.js
    // The Google key is deliberately handed to the page: a browser API key is loaded by the visitor's browser and can only ever be public, which is why the setting's own description says the protection is Google's HTTP-referrer restriction and not the "sensitive" flag hiding it in the back-office
    #[AsTwigFunction('ui_map_settings')]
    public function settings(): array
    {
        $provider = MapProvider::fromSetting((string) $this->configService->get('ui-map-provider'));
        $apiKey = (string) $this->configService->get('ui-map-google-api-key');

        return [
            'provider' => $provider->value,
            'apiKey' => $provider->needsApiKey() ? $apiKey : '',
            'tileUrl' => $provider->tileUrl() ?? '',
            'attribution' => $provider->attribution() ?? '',
            'needsConsent' => $provider->needsConsent(),
            // A Google map with no key draws nothing at all, so the component keeps the list of places on screen instead of a grey box the visitor cannot read
            'usable' => !$provider->needsApiKey() || '' !== trim($apiKey),
            // Whether the page may say why a map is missing. A visitor is shown the list of places and nothing else - being told about a key or a security policy they cannot change is noise on the site's own content; whoever placed the block sees the reason where the map should be, rather than only in a health check they have no reason to open
            'diagnostic' => $this->security?->isGranted(BackOfficeAccessVoter::ACCESS) ?? false,
        ];
    }
}
