<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Hands a batch of urls to IndexNow (https://www.indexnow.org), which shares it with every engine taking part (Bing, Yandex, Seznam, Naver...) - its own class like AiCrawlerListClient, so what talks to a third party is one stubable seam rather than an http client threaded through IndexNowNotifier
class IndexNowClient
{
    // The shared endpoint rather than one engine's own: a submission there reaches all of them
    private const string ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Submits up to 10 000 urls of the same host. Throws on anything but 200 (accepted) or 202 (accepted, key still being checked), so the caller keeps the urls for the next run rather than taking them for submitted
    public function submit(string $host, string $key, string $keyLocation, array $urls): void
    {
        try {
            $statusCode = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => json_encode(['host' => $host, 'key' => $key, 'keyLocation' => $keyLocation, 'urlList' => array_values($urls)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
            ])->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException(sprintf('IndexNow could not be reached: %s', $e->getMessage()), 0, $e);
        }

        if (!in_array($statusCode, [200, 202], true)) {
            throw new \RuntimeException(sprintf('IndexNow answered %d.', $statusCode));
        }
    }
}
