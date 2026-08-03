<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class VideoExtension extends AbstractExtension
{
    private const YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
    ];

    // youtu.be links only ever carry a bare video id path ("/abc123"), never "/embed/..." - youtube-nocookie.com only serves the "/embed/" path, so this host needs its path rewritten too, not just its host swapped like the youtube.com family above (assumed already "/embed/")
    private const SHORT_HOSTS = [
        'youtu.be',
    ];

    private const NOCOOKIE_HOSTS = [
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('privacy_embed_url', [$this, 'toPrivacyEmbedUrl']),
        ];
    }

    // Rewrites known YouTube hosts to youtube-nocookie.com (no tracking cookie set until playback starts) - every other provider (Vimeo, Dailymotion...) is left untouched
    public function toPrivacyEmbedUrl(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (null === $host) {
            return $url;
        }

        // Already on youtube-nocookie.com - an explicit choice made upstream (whoever built this URL already resolved the "/embed/" path themselves), left untouched rather than second-guessed
        if (in_array($host, self::NOCOOKIE_HOSTS, true)) {
            return $url;
        }

        if (in_array($host, self::SHORT_HOSTS, true)) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';

            return str_replace($host . $path, 'www.youtube-nocookie.com/embed' . $path, $url);
        }

        if (!in_array($host, self::YOUTUBE_HOSTS, true)) {
            return $url;
        }

        // "/watch?v=..." is what an editor actually copies from their browser's address bar while watching a video - youtube-nocookie.com doesn't serve that path, only "/embed/{id}"
        if ('/watch' === (parse_url($url, PHP_URL_PATH) ?? '')) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

            return isset($query['v']) ? 'https://www.youtube-nocookie.com/embed/' . $query['v'] : $url;
        }

        return str_replace($host, 'www.youtube-nocookie.com', $url);
    }
}
