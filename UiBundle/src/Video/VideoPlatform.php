<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Video;

// The single place the ecosystem knows a video platform from: which urls belong to it, how its embed url is built, how its player is shaped and which origin a Content-Security-Policy has to allow for it
// Adding a platform is adding a case here - nothing else in this bundle, and nothing at all in the bundles reading it (see GalleryBundle's GalleryMedia, which stores a case's value as its media type)
// What is deliberately absent is a "paste any embed code" escape hatch: third-party HTML stored in the database is an XSS and a CSP hole, where an unknown url simply stays unresolved and is left untouched (see VideoExtension)
enum VideoPlatform: string
{
    case Youtube = 'youtube';
    case Tiktok = 'tiktok';
    case Vimeo = 'vimeo';
    case Dailymotion = 'dailymotion';

    // The url a player is actually loaded from, built from the id alone - so a platform changing its url scheme is one line here rather than a column to migrate everywhere it was stored
    // Privacy-first where the platform offers it: YouTube plays on youtube-nocookie.com, Vimeo carries "dnt=1" (do not track). Neither is a substitute for consent, both players being third-party frames all the same (see the video-iframe Stimulus controller)
    // #lizard forgives - Lizard parses no PHP enum: it reads every method below as part of this one, and reports the whole enum's length and complexity here
    public function embedUrl(string $id, array $carriedParams = []): string
    {
        $url = sprintf(match ($this) {
            self::Youtube => 'https://www.youtube-nocookie.com/embed/%s',
            self::Tiktok => 'https://www.tiktok.com/embed/v2/%s',
            self::Vimeo => 'https://player.vimeo.com/video/%s?dnt=1',
            self::Dailymotion => 'https://www.dailymotion.com/embed/video/%s',
        }, rawurlencode($id));

        if ([] === $carriedParams) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($carriedParams);
    }

    // The address the video is watched at on the platform itself, for whoever offers a way out of the page - a reader wanting the channel, the comments, or the full screen. Not the embed url: a player address pasted in a browser is a bare frame, and on YouTube it is the one page the platform refuses to open on its own
    // The carried params are read here as well as by embedUrl(), and for the same reason: a playlist and an unlisted video are addressed by them rather than by their id, so dropping them would build a watch url the platform answers with an error page
    // TikTok is the one platform whose own page needs the author handle, which the id alone does not carry - its legacy mobile permalink is the form that gets there without one, the platform redirecting it to the handle it knows
    public function watchUrl(string $id, array $carriedParams = []): string
    {
        $id = rawurlencode($id);

        return match ($this) {
            self::Youtube => match (true) {
                !isset($carriedParams['list']) => sprintf('https://www.youtube.com/watch?v=%s', $id),
                // A playlist is stored with the literal "videoseries" for an id, which names no video: the list is the whole address
                'videoseries' === $id => sprintf('https://www.youtube.com/playlist?list=%s', rawurlencode($carriedParams['list'])),
                default => sprintf('https://www.youtube.com/watch?v=%s&list=%s', $id, rawurlencode($carriedParams['list'])),
            },
            self::Tiktok => sprintf('https://m.tiktok.com/v/%s.html', $id),
            // An unlisted video is only reachable with its access token, the page naming it after the id
            self::Vimeo => isset($carriedParams['h'])
                ? sprintf('https://vimeo.com/%s/%s', $id, rawurlencode($carriedParams['h']))
                : sprintf('https://vimeo.com/%s', $id),
            self::Dailymotion => sprintf('https://www.dailymotion.com/video/%s', $id),
        };
    }

    // The stills this platform serves for a video id, best first - whoever imports one takes the first that answers (see VideoPosterImporter)
    // Only YouTube has an address a poster can be built from at all: TikTok, Vimeo and Dailymotion hand theirs out through an API call, which no url scheme can stand in for, so they resolve to no candidate rather than to a guessed one - same rule as urlPatterns(), where an undeclared platform is left alone instead of being pattern-matched
    // i.ytimg.com rather than img.youtube.com: the same bytes without the redirect, and neither host sets a cookie - which is what makes importing one a plain file copy rather than a privacy decision
    // "maxresdefault" (1280x720) is missing on plenty of videos, "hqdefault" (480x360) is always there but letterboxed for 16:9 content. No crop here: the bars fall outside an "object-fit: cover" tile, and cropping a still nobody has looked at yet would be guessing which bars are padding and which are the shot
    public function posterUrls(string $id): array
    {
        return match ($this) {
            self::Youtube => [
                sprintf('https://i.ytimg.com/vi/%s/maxresdefault.jpg', rawurlencode($id)),
                sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', rawurlencode($id)),
            ],
            default => [],
        };
    }

    // The few query parameters a player cannot play without, kept from the pasted url alongside the id: an unlisted Vimeo video is only reachable with its "h" access token, and a YouTube playlist is nothing but its "list" (the id being the literal "videoseries")
    // A whitelist rather than the whole query string, so playback options and tracking parameters ("autoplay", "start", campaign tags) stay out of what gets stored (see UPGRADE.md)
    public function carriedParams(string $url): array
    {
        $whitelist = match ($this) {
            self::Youtube => ['list'],
            self::Vimeo => ['h'],
            default => [],
        };

        $query = [] === $whitelist ? null : parse_url(trim($url), PHP_URL_QUERY);
        if (!is_string($query)) {
            return [];
        }

        parse_str($query, $params);

        return array_filter(
            array_intersect_key($params, array_flip($whitelist)),
            static fn ($value): bool => is_string($value) && '' !== $value,
        );
    }

    // What a `frame-src` (and the `child-src` fallback, and a `Permissions-Policy` naming fullscreen) has to allow for this platform's player to render at all - a missing directive is an empty frame in production and nothing in development
    public function cspOrigins(): array
    {
        return match ($this) {
            self::Youtube => ['https://www.youtube-nocookie.com'],
            self::Tiktok => ['https://www.tiktok.com'],
            self::Vimeo => ['https://player.vimeo.com'],
            // The embed url redirects to the geo host, which is where the player itself ends up being framed
            self::Dailymotion => ['https://www.dailymotion.com', 'https://geo.dailymotion.com'],
        };
    }

    // The shape of the player, for whoever reserves its box before it loads - portrait for the platforms built for phones, landscape for the rest
    public function aspectRatio(): string
    {
        return match ($this) {
            self::Tiktok => '9 / 16',
            default => '16 / 9',
        };
    }

    // What an editor actually copies: the address bar of the page they were watching the video on, an embed url a platform handed them, or a share link. All of them carry the same id, which is the only thing worth storing
    // An unrecognized url resolves to null rather than being guessed at - it is what keeps a platform nobody declared here from being framed on the strength of a pattern that happened to match
    public static function resolve(?string $url): ?ResolvedVideo
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        foreach (self::cases() as $platform) {
            foreach ($platform->urlPatterns() as $pattern) {
                if (1 === preg_match($pattern, trim($url), $matches)) {
                    return new ResolvedVideo($platform, $matches[1], $platform->carriedParams($url));
                }
            }
        }

        return null;
    }

    // Every origin every declared platform needs, for a site building its CSP from the registry rather than from a list in a README (see the "c975l_ui.video.embed_origins" container parameter)
    public static function allCspOrigins(): array
    {
        $origins = [];
        foreach (self::cases() as $platform) {
            $origins = array_merge($origins, $platform->cspOrigins());
        }

        return array_values(array_unique($origins));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Ids are matched loosely on purpose ("[A-Za-z0-9_-]+" rather than YouTube's own 11 characters): a length is a platform's implementation detail, free to change, and an id that turns out to be wrong shows an error in the player rather than silently refusing a valid url at save time
    private function urlPatterns(): array
    {
        return match ($this) {
            self::Youtube => [
                // "/watch?v=" is the address bar's own form, the id being anywhere in the query string ("?list=...&v=...")
                '#^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:[^\#]*&)?v=([A-Za-z0-9_-]+)#i',
                '#^https?://(?:www\.|m\.)?youtube\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]+)#i',
                // Only the "/embed/" path: a bare path on the nocookie host is a deliberate choice made upstream, left untouched rather than second-guessed
                '#^https?://(?:www\.)?youtube-nocookie\.com/embed/([A-Za-z0-9_-]+)#i',
                '#^https?://youtu\.be/([A-Za-z0-9_-]+)#i',
            ],
            // vm.tiktok.com and www.tiktok.com/t/ short links are deliberately absent: they carry no id at all, only a redirect nobody can follow without a request to TikTok from the server
            self::Tiktok => [
                '#^https?://(?:www\.)?tiktok\.com/@[^/]+/video/(\d+)#i',
                '#^https?://(?:www\.)?tiktok\.com/embed/(?:v2/)?(\d+)#i',
                '#^https?://m\.tiktok\.com/v/(\d+)#i',
            ],
            self::Vimeo => [
                '#^https?://player\.vimeo\.com/video/(\d+)#i',
                '#^https?://(?:www\.)?vimeo\.com/(?:channels/[^/]+/|groups/[^/]+/videos/)?(\d+)#i',
            ],
            self::Dailymotion => [
                '#^https?://(?:www\.)?dailymotion\.com/(?:embed/)?video/([A-Za-z0-9]+)#i',
                '#^https?://geo\.dailymotion\.com/player[^?]*\?(?:[^\#]*&)?video=([A-Za-z0-9]+)#i',
                '#^https?://dai\.ly/([A-Za-z0-9]+)#i',
            ],
        };
    }
}
