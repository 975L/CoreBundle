<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Map;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\MapProviderInterface;

// Google's Maps JavaScript API, from "ui-map-google-api-key": it writes cookies of its own and is billed per load, hence the consent. The key is deliberately handed to the page - a browser key can only ever be public, protected by Google's HTTP-referrer restriction and not by the "sensitive" flag hiding it in the back-office
class GoogleMapsProvider implements MapProviderInterface
{
    // Google's Maps URLs, the free half of Google Maps: a link anyone opens, no key, no billing account and no script loaded
    private const string SEARCH_URL = 'https://www.google.com/maps/search/?api=1&query=';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getSlug(): string
    {
        return 'google';
    }

    public function needsConsent(): bool
    {
        return true;
    }

    // A Google map with no key draws nothing at all
    public function isUsable(): bool
    {
        return '' !== $this->getApiKey();
    }

    public function getSettings(): array
    {
        return ['apiKey' => $this->getApiKey()];
    }

    public function getPlaceUrl(string $latitude, string $longitude): string
    {
        return self::SEARCH_URL . rawurlencode($latitude . ',' . $longitude);
    }

    public function getSearchUrl(string $address): string
    {
        return self::SEARCH_URL . rawurlencode($address);
    }

    // The hosts Google serves its script, its data, its tiles, its sprites and its logo from
    public static function getOrigins(): array
    {
        return [
            'img' => ['https://maps.googleapis.com', 'https://maps.gstatic.com'],
            'script' => ['https://maps.googleapis.com'],
            'connect' => ['https://maps.googleapis.com'],
        ];
    }

    private function getApiKey(): string
    {
        return trim((string) $this->configService->get('ui-map-google-api-key'));
    }
}
