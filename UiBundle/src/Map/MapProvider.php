<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Map;

// The single place the bundle knows a map fournisseur from: which origins a Content-Security-Policy has to allow for it, which tile server it draws from, and whether framing it costs the visitor a cookie
// Adding a provider is adding a case here plus a branch in assets/js/map.js - nothing else, and nothing at all in a consuming site beyond the "ui-map-provider" setting
// Deliberately not a renderer abstraction: symfony/ux-map builds exactly one renderer, named "default", out of a compile-time DSN, so a provider picked in the back-office and an API key held in the database cannot reach it without decorating a service its own package marks @internal
enum MapProvider: string
{
    case Leaflet = 'leaflet';
    case Google = 'google';

    // Whether the map may only be drawn once the visitor has accepted the "content" category of a consent banner. Google's JavaScript API writes cookies of its own and is billed per load; OpenStreetMap's tile servers write none, the visitor's IP being all that reaches them - which is why one is gated and the other is not (same contract as the video_iframe block, see assets/js/map.js)
    public function needsConsent(): bool
    {
        return self::Google === $this;
    }

    // Whether an API key has to be set for the provider to draw anything at all - a map declared with none renders as the list of places under it and nothing more
    public function needsApiKey(): bool
    {
        return self::Google === $this;
    }

    // The tile server a raster map is drawn from, null for a provider serving its own through its API
    public function tileUrl(): ?string
    {
        return match ($this) {
            self::Leaflet => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            self::Google => null,
        };
    }

    // The credit the tile server's licence requires on the map itself, null where the API draws its own
    public function attribution(): ?string
    {
        return match ($this) {
            self::Leaflet => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
            self::Google => null,
        };
    }

    // What an "img-src" has to allow: the tile server a raster map is drawn from, and the hosts Google serves its own tiles, its sprites and its logo from
    public function imgOrigins(): array
    {
        return match ($this) {
            self::Leaflet => ['https://tile.openstreetmap.org'],
            self::Google => ['https://maps.googleapis.com', 'https://maps.gstatic.com'],
        };
    }

    // What a "script-src" has to allow. Empty for Leaflet, and deliberately so: this bundle serves that library itself (public/js/leaflet.js, see config/vendor-assets.json), so an OpenStreetMap site opens its policy to nobody
    public function scriptOrigins(): array
    {
        return match ($this) {
            self::Leaflet => [],
            self::Google => ['https://maps.googleapis.com'],
        };
    }

    // What a "connect-src" has to allow, a map fetching its own data as it is panned. Nothing for Leaflet, whose tiles are plain images the "img-src" above covers
    public function connectOrigins(): array
    {
        return match ($this) {
            self::Leaflet => [],
            self::Google => ['https://maps.googleapis.com'],
        };
    }

    // Falls back to OpenStreetMap rather than throwing on an unknown value: the setting is typed in a back-office, and a map is a page's content - a site whose setting was mistyped keeps a drawn map instead of a 500 on every page carrying one
    public static function fromSetting(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Leaflet;
    }

    // Every origin every declared provider needs, per directive, for a site building its policy from the enum rather than from a list copied out of a README (see the "c975l_ui.map.*_origins" container parameters)
    // Three lists and not one: a single blob named in a "script-src" would authorise scripts from a tile server, which is the opposite of what naming origins is for
    public static function allImgOrigins(): array
    {
        return self::gather(static fn (self $provider): array => $provider->imgOrigins());
    }

    public static function allScriptOrigins(): array
    {
        return self::gather(static fn (self $provider): array => $provider->scriptOrigins());
    }

    public static function allConnectOrigins(): array
    {
        return self::gather(static fn (self $provider): array => $provider->connectOrigins());
    }

    private static function gather(callable $origins): array
    {
        $gathered = [];

        foreach (self::cases() as $provider) {
            $gathered = array_merge($gathered, $origins($provider));
        }

        return array_values(array_unique($gathered));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
