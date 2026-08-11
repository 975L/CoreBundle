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

// Fetches one url exactly as an anonymous visitor's browser would, and hands back the three things a misconfiguration shows up in: the status, the response headers, and the first bytes of the body. Separate from SecurityHeadersClient, which reads headers alone and follows redirects: here a redirect is an answer in itself (a site sending /_profiler back to its home page is not exposing it), so redirects are never followed. Used by SecurityMisconfigurationHealthCheckProvider, only ever from the c975l:health-check:run command
class SecurityProbeClient
{
    // Enough to recognise what a file or a directory listing is, never enough to hold a page in memory ten times over
    private const int BODY_BYTES = 2048;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // ['status' => int, 'headers' => lowercased name => list of values, 'body' => string]. Throws only on a real network/transport failure: every http status is a result here, 404 included - it is the answer proving a path is not served
    public function probe(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 15, 'max_redirects' => 0]);

        $headers = [];
        foreach ($response->getHeaders(false) as $name => $values) {
            $headers[strtolower($name)] = $values;
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => $headers,
            'body' => substr($response->getContent(false), 0, self::BODY_BYTES),
        ];
    }
}
