<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\UiBundle\Service;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// Consumes an optional rate limiter: a null factory means no limiter configured, symfony/rate-limiter being a soft dependency
class RateLimiterGuard
{
    public function isAccepted(?RateLimiterFactoryInterface $limiterFactory, string $key): bool
    {
        if (null === $limiterFactory) {
            return true;
        }

        return $limiterFactory->create($key)->consume(1)->isAccepted();
    }
}
