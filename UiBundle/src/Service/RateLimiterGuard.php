<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// Consumes an optional rate limiter: a null factory means no limiter configured. c975LUiBundle prepends "ui_form" itself, so a null only reaches here when a site deliberately took that limiter away
class RateLimiterGuard
{
    public function isAccepted(?RateLimiterFactoryInterface $limiterFactory, string $key): bool
    {
        if (null === $limiterFactory) {
            return true;
        }

        return $limiterFactory->create($key)->consume(1)->isAccepted();
    }

    // The same decision, counted per caller rather than per address. The two are the same thing in IPv4, where an address is scarce enough to stand for whoever holds it, and not at all in IPv6, where the smallest block handed to a subscriber holds more addresses than a limit could ever count. Keyed on the address itself, a ceiling is then walked straight through: one more address out of one's own block opens a fresh bucket, at no cost and for as long as it takes. So an IPv6 address is counted by its /64 and an IPv4 one whole - which is what anonymize()'s two byte counts say here, none masked for v4 and the lower eight for v6. It also settles the awkward cases (an IPv4-mapped address stays the v4 it really is) rather than leaving them to a hand-rolled cut.
    public function isAcceptedForIp(?RateLimiterFactoryInterface $limiterFactory, string $ip): bool
    {
        return $this->isAccepted($limiterFactory, IpUtils::anonymize($ip, 0, 8));
    }
}
