<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

// Extracted from ContactFormBundle's ContactFormService (consumeRateLimiter()/isRateLimitAccepted())
class RateLimiterGuardTest extends TestCase
{
    // A real factory, final and unstubbable, backed by in-memory storage so no cache service is needed
    private function limiterFactory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }

    public function testIsAcceptedTrueWhenNoLimiterFactoryConfigured(): void
    {
        $this->assertTrue((new RateLimiterGuard())->isAccepted(null, 'some-key'));
    }

    public function testIsAcceptedReflectsLimitDecision(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAccepted($factory, 'some-key'));
        $this->assertFalse($guard->isAccepted($factory, 'some-key'));
    }

    // Two different keys must not share the same bucket
    public function testIsAcceptedTracksEachKeySeparately(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAccepted($factory, 'visitor-a'));
        $this->assertTrue($guard->isAccepted($factory, 'visitor-b'));
    }
}
