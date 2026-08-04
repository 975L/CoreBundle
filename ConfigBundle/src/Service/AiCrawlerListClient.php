<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

// Reads the community list of AI crawlers AiCrawlerListUpdater compares the site's own to - its own class like SeoFilesClient and DeploymentClient, so what talks to a third party is one stubable seam rather than an http client threaded through a service that has other work to do
class AiCrawlerListClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // The user agents the list declares, in the order it declares them. Throws on anything but a readable json object: an html error page decoding to nothing must be reported, never taken for a list that lost all its entries
    public function fetch(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('The AI crawler list at "%s" answered %d.', $url, $response->getStatusCode()));
        }

        $agents = json_decode($response->getContent(false), true);

        if (!is_array($agents) || [] === $agents) {
            throw new \RuntimeException(sprintf('The AI crawler list at "%s" is not a readable json object.', $url));
        }

        $userAgents = array_values(array_filter(array_map(
            static fn (mixed $agent): string => is_string($agent) ? trim($agent) : '',
            array_keys($agents)
        )));

        // A json list rather than the expected object, or an object whose keys are numbers, decodes fine but leaves nothing to compare - reported like any other unreadable answer, never taken for a site that has every crawler already
        if ([] === $userAgents) {
            throw new \RuntimeException(sprintf('The AI crawler list at "%s" declares no user agent.', $url));
        }

        return $userAgents;
    }
}
