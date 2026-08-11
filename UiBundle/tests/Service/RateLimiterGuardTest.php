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
        $this->assertTrue(new RateLimiterGuard()->isAccepted(null, 'some-key'));
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

    public function testIsAcceptedForIpTrueWhenNoLimiterFactoryConfigured(): void
    {
        $this->assertTrue(new RateLimiterGuard()->isAcceptedForIp(null, '203.0.113.7'));
    }

    // An IPv4 address stands for its holder, so it is counted whole - two of them are two callers
    public function testIsAcceptedForIpCountsEachIpv4AddressOnItsOwn(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAcceptedForIp($factory, '203.0.113.7'));
        $this->assertFalse($guard->isAcceptedForIp($factory, '203.0.113.7'));
        $this->assertTrue($guard->isAcceptedForIp($factory, '203.0.113.8'));
    }

    // The whole point: one subscriber's block must not be as many fresh buckets as it holds addresses
    public function testIsAcceptedForIpCountsAnIpv6BlockAsOneCaller(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAcceptedForIp($factory, '2a01:e0a:f28:34b0:e807:73b9:f102:b5d7'));
        $this->assertFalse($guard->isAcceptedForIp($factory, '2a01:e0a:f28:34b0::1'));
        $this->assertFalse($guard->isAcceptedForIp($factory, '2a01:e0a:f28:34b0:ffff:ffff:ffff:ffff'));
    }

    // Two blocks are two callers, or the cut would have gone too far and put strangers together
    public function testIsAcceptedForIpKeepsTwoIpv6BlocksApart(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAcceptedForIp($factory, '2a01:e0a:f28:34b0::1'));
        $this->assertTrue($guard->isAcceptedForIp($factory, '2a01:e0a:f28:34b1::1'));
    }

    // An IPv4-mapped address is the v4 it really is, not a /64 to cut - two of them stay two callers
    public function testIsAcceptedForIpCountsAnIpv4MappedAddressAsIpv4(): void
    {
        $guard = new RateLimiterGuard();
        $factory = $this->limiterFactory(1);

        $this->assertTrue($guard->isAcceptedForIp($factory, '::ffff:203.0.113.7'));
        $this->assertFalse($guard->isAcceptedForIp($factory, '::ffff:203.0.113.7'));
        $this->assertTrue($guard->isAcceptedForIp($factory, '::ffff:203.0.113.8'));
    }
}
