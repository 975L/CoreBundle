<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// The one identity every health-check request carries, whichever bundle issues it. Three different strings used to travel under this name - one per client class, plus the HttpClient default on those that set none - which made a run impossible to read in an access log and impossible to exempt from anything
final class HealthCheck
{
    // Sent as User-Agent by every client that probes a site on the console's behalf. Identifies the checker honestly (a WAF operator can look it up and allow it) while keeping the "Mozilla/5.0 (compatible; ...)" shape crawlers have used since Googlebot, which far fewer filters reject outright than a bare library default
    public const string USER_AGENT = 'Mozilla/5.0 (compatible; c975LHealthCheck/1.0; +https://github.com/975L/ConfigBundle)';

    // Whether this request is one of our own probes. Read from the raw header rather than any resolved identity: it runs before the firewall, where none exists yet. The string is published in a public repository, so it names a caller rather than proving one: anyone sending it walks past RateLimitListener, which is the one way around the limiter. Deliberate - the limiter answers bulk traffic, not an adversary who reads the source, and a shared secret to hold on every site would cost more than that caller is worth
    public static function isProbe(?string $userAgent): bool
    {
        return null !== $userAgent && str_contains($userAgent, 'c975LHealthCheck');
    }
}
