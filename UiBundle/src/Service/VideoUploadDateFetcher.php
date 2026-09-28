<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Video\ResolvedVideo;
use c975L\UiBundle\Video\VideoPlatform;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// The day a video went online on its platform, which a VideoObject cannot be published without and which no embed url carries. Asked once, stored in the block (see VideoUploadDatesCommand), never on a page render
class VideoUploadDateFetcher
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    // The date as Y-m-d, null when the url belongs to no platform, the platform gives none (TikTok, a playlist) or did not answer
    public function fetch(?string $url): ?string
    {
        $video = VideoPlatform::resolve($url);
        if (null === $video || 'videoseries' === $video->id) {
            return null;
        }

        try {
            $date = match ($video->platform) {
                VideoPlatform::Youtube => $this->youtube($video),
                VideoPlatform::Vimeo => $this->json('https://vimeo.com/api/oembed.json?url=' . rawurlencode($video->watchUrl()))['upload_date'] ?? null,
                VideoPlatform::Dailymotion => $this->dailymotion($video->id),
                default => null,
            };
        } catch (ExceptionInterface) {
            return null;
        }

        // The day as the platform writes it, not shifted into this server's timezone; Dailymotion alone answers with a timestamp
        return match (true) {
            \is_string($date) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}/', $date) => substr($date, 0, 10),
            \is_int($date) => gmdate('Y-m-d', $date),
            default => null,
        };
    }

    // YouTube's oEmbed gives no date and its API wants a key: the watch page states it in its own structured data. The consent cookie keeps a European server from being served the consent wall instead of the page
    private function youtube(ResolvedVideo $video): ?string
    {
        $html = $this->httpClient->request('GET', sprintf('https://www.youtube.com/watch?v=%s', $video->id), [
            'headers' => ['Cookie' => 'CONSENT=YES+1; SOCS=CAI', 'Accept-Language' => 'en'],
            'timeout' => 10,
        ])->getContent();

        return 1 === preg_match('/itemprop="(?:uploadDate|datePublished)" content="([^"]+)"|"(?:uploadDate|publishDate)":"([^"]+)"/', $html, $matches)
            ? ('' !== $matches[1] ? $matches[1] : $matches[2])
            : null;
    }

    private function dailymotion(string $id): ?int
    {
        $createdTime = $this->json(sprintf('https://api.dailymotion.com/video/%s?fields=created_time', rawurlencode($id)))['created_time'] ?? null;

        return is_numeric($createdTime) ? (int) $createdTime : null;
    }

    private function json(string $url): array
    {
        return $this->httpClient->request('GET', $url, ['timeout' => 10])->toArray();
    }
}
