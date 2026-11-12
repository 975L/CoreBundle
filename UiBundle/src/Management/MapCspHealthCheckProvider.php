<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckErrorRow;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\SecurityHeadersClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\UiBundle\Service\MapProviderResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Checks only the origins the provider in use declares, which is the point: the provider is picked in the back-office, and a policy widened for a provider nobody asked for is a policy widened for nothing.
//
// Whatever the provider fetches from elsewhere - Leaflet's tiles, Google's script, tiles and api calls - comes from a host the site's own Content-Security-Policy has to name, and a policy that does not name it refuses the fetch in silence: the list of places on screen, or a grey grid where the tiles should be, with nothing anywhere to say why. That silence is what this row breaks: it names the missing origins, per directive, so the change to make is the one the browser is actually waiting for.
//
// The styles Google injects are not checked, and deliberately: the API copies the nonce it finds on the page onto them (see assets/js/map-google.js, carryNonce()), so a nonced "style-src" needs no widening at all.
class MapCspHealthCheckProvider implements HealthCheckProviderInterface
{
    public const string KIND = 'map-csp';

    // What has to name each of them, a directive falling back on "default-src" where the policy declares none of its own
    private const array DIRECTIVES = ['script-src', 'img-src', 'connect-src'];

    public function __construct(
        private readonly MapProviderResolver $mapProviderResolver,
        private readonly SecurityHeadersClient $securityHeadersClient,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $provider = $this->mapProviderResolver->getProvider();
        $url = $this->siteUrlResolver->siteRoot();

        if (null === $provider || [] === array_merge(...array_values($provider::getOrigins())) || null === $url) {
            return [];
        }

        try {
            $headers = $this->securityHeadersClient->fetchHeaders($url);
        } catch (\Throwable $e) {
            return [HealthCheckErrorRow::build($this->translator, 'ui', $url, null, 'label.map_csp_call_failed', $e->getMessage())];
        }

        return [$this->check($url, (string) ($headers['content-security-policy'] ?? ''), $provider::getOrigins())];
    }

    /**
     * @param array{img: list<string>, script: list<string>, connect: list<string>} $origins
     *
     * @return array<string, mixed>
     */
    private function check(string $url, string $policy, array $origins): array
    {
        // A site serving no policy at all refuses nothing, so there is nothing to report - the missing header is SecurityHeadersHealthCheckProvider's own row to raise
        if ('' === $policy) {
            return $this->row($url, HealthCheckResult::STATUS_OK, 'label.map_csp_no_policy', [], []);
        }

        $missing = $this->missing($this->parse($policy), $origins);

        return [] === $missing
            ? $this->row($url, HealthCheckResult::STATUS_OK, 'label.map_csp_allowed', [], [])
            : $this->row($url, HealthCheckResult::STATUS_ERROR, 'label.map_csp_refused', ['%directives%' => implode(', ', array_keys($missing))], $missing);
    }

    // The origins the policy has to name and does not, per directive
    /**
     * @param array<string, list<string>>                                           $policy
     * @param array{img: list<string>, script: list<string>, connect: list<string>} $origins
     *
     * @return array<string, list<string>>
     */
    private function missing(array $policy, array $origins): array
    {
        $needed = [
            'script-src' => $origins['script'],
            'img-src' => $origins['img'],
            'connect-src' => $origins['connect'],
        ];

        $missing = [];
        foreach (self::DIRECTIVES as $directive) {
            $sources = $policy[$directive] ?? $policy['default-src'] ?? null;

            // A policy declaring neither the directive nor "default-src" restricts nothing of that kind
            if (null === $sources) {
                continue;
            }

            // "strict-dynamic" hands the whole allowlist over to the nonce, which the loader carries (see map.js): naming a host there would change nothing either way
            if ('script-src' === $directive && \in_array("'strict-dynamic'", $sources, true)) {
                continue;
            }

            $refused = array_values(array_filter(
                $needed[$directive],
                fn (string $origin): bool => !$this->allows($sources, $origin),
            ));

            if ([] !== $refused) {
                $missing[$directive] = $refused;
            }
        }

        return $missing;
    }

    // Whether a list of sources covers an origin, a policy naming a whole zone ("https://*.googleapis.com") covering the host under it, and a scheme source ("https:") every host served over that scheme
    /** @param list<string> $sources */
    private function allows(array $sources, string $origin): bool
    {
        $host = (string) parse_url($origin, \PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($origin, \PHP_URL_SCHEME));

        foreach ($sources as $source) {
            if ($source === $origin || '*' === $source || strtolower($source) === $scheme . ':') {
                return true;
            }

            $named = (string) parse_url(str_contains($source, '://') ? $source : 'https://' . $source, \PHP_URL_HOST);

            if ($named === $host || (str_starts_with($named, '*.') && str_ends_with($host, substr($named, 1)))) {
                return true;
            }
        }

        return false;
    }

    // The policy as directive => sources, the header being one string of both
    /** @return array<string, list<string>> */
    private function parse(string $policy): array
    {
        $parsed = [];
        foreach (explode(';', $policy) as $part) {
            $sources = preg_split('/\s+/', trim($part), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            $directive = array_shift($sources);

            if (null !== $directive) {
                $parsed[strtolower($directive)] = $sources;
            }
        }

        return $parsed;
    }

    /**
     * @param array<string, string>       $parameters
     * @param array<string, list<string>> $missing
     *
     * @return array<string, mixed>
     */
    private function row(string $url, string $status, string $key, array $parameters, array $missing): array
    {
        return [
            'url' => $url,
            'label' => $this->translator->trans('label.ui_map_provider', [], 'site_config'),
            'status' => $status,
            'summary' => $this->translator->trans($key, $parameters, 'ui'),
            'details' => ['missing' => $missing],
        ];
    }
}
