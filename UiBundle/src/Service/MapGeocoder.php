<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Turns the address an editor typed into the coordinates a map is drawn from, once, when the block is saved - so a page carrying a map never geocodes anything while a visitor waits for it
// Nominatim whatever the provider the site draws with: a Google key restricted by HTTP referrer, which is the only thing protecting a key a page necessarily publishes, is refused on a server-to-server call - supporting Google here would mean asking a site for a second, unrestricted key just to resolve a handful of addresses a year
class MapGeocoder implements MapGeocoderInterface
{
    private const string ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    // Nominatim's usage policy refuses a request that does not identify its caller, and answers a bare client with a 403
    private const string USER_AGENT = 'c975L-UiBundle/1.0 (https://github.com/975L/CoreBundle)';

    // A back-office save waits on this call, so it is kept short: an address that did not resolve in that time is reported to the editor, who still has the coordinates field to fall back on
    private const int TIMEOUT = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function geocode(string $address): ?array
    {
        $address = trim($address);

        if ('' === $address) {
            return null;
        }

        try {
            $results = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                ],
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => self::TIMEOUT,
            ])->toArray();
        } catch (ExceptionInterface | \JsonException $exception) {
            // Logged and not rethrown: the caller reads a null as "this address did not resolve" and says so on the field, which is what an editor can act on - where an exception would take down the whole block form
            $this->logger->warning('Geocoding "{address}" failed: {message}', [
                'address' => $address,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $first = $results[0] ?? null;

        if (!is_array($first) || !isset($first['lat'], $first['lon'])) {
            return null;
        }

        return [
            'latitude' => (string) $first['lat'],
            'longitude' => (string) $first['lon'],
        ];
    }
}
