<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Listener\RateLimitListener;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\HealthCheck;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimitListenerTest extends TestCase
{
    // Deliberately tiny, so a test spends three requests rather than sixty proving the ceiling exists
    private const int LIMIT = 3;

    private function createListener(bool $enabled = true): RateLimitListener
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => 'site-rate-limit' === $slug ? $enabled : null);

        $factory = new RateLimiterFactory(
            [
                'id' => 'test_front_request',
                'policy' => 'sliding_window',
                'limit' => self::LIMIT,
                'interval' => '10 seconds',
            ],
            new CacheStorage(new ArrayAdapter()),
        );

        return new RateLimitListener($configService, $factory);
    }

    private function createRequestEvent(string $path, string $ip = '203.0.113.7', ?string $userAgent = null, bool $mainRequest = true): RequestEvent
    {
        $server = ['REMOTE_ADDR' => $ip];
        if (null !== $userAgent) {
            $server['HTTP_USER_AGENT'] = $userAgent;
        }

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path, 'GET', [], [], [], $server),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }

    // Consumes the allowance, then returns the event of the request just over it
    private function exhaust(RateLimitListener $listener, string $path = '/', string $ip = '203.0.113.7'): RequestEvent
    {
        for ($i = 0; $i < self::LIMIT; ++$i) {
            $listener->onKernelRequest($this->createRequestEvent($path, $ip));
        }

        $event = $this->createRequestEvent($path, $ip);
        $listener->onKernelRequest($event);

        return $event;
    }

    public function testLetsARequestUnderTheCeilingThrough(): void
    {
        $event = $this->createRequestEvent('/');
        $this->createListener()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testRefusesOnceTheCeilingIsPassed(): void
    {
        $response = $this->exhaust($this->createListener())->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    // The refusal has to say when to come back, and must never be kept by a shared cache
    public function testRefusalCarriesRetryAfterAndIsNotStored(): void
    {
        $response = $this->exhaust($this->createListener())->getResponse();

        $this->assertNotNull($response);
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('Retry-After'));
        // Symfony appends "private" of its own accord; what matters is that no-store is in there
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $this->assertNull($this->exhaust($this->createListener(false))->getResponse());
    }

    public function testDoesNotCountSubRequests(): void
    {
        $listener = $this->createListener();
        for ($i = 0; $i < self::LIMIT * 2; ++$i) {
            $event = $this->createRequestEvent('/', userAgent: null, mainRequest: false);
            $listener->onKernelRequest($event);
            $this->assertNull($event->getResponse());
        }
    }

    // Our own probes burst far above any visitor's ceiling, and counting them would turn every run into false alarms
    public function testDoesNotCountHealthCheckProbes(): void
    {
        $listener = $this->createListener();
        for ($i = 0; $i < self::LIMIT * 2; ++$i) {
            $event = $this->createRequestEvent('/', userAgent: HealthCheck::USER_AGENT);
            $listener->onKernelRequest($event);
            $this->assertNull($event->getResponse());
        }
    }

    public function testExemptPathsAreNeverCounted(): void
    {
        $listener = $this->createListener();
        foreach (['/login', '/status/report', '/_profiler/x', '/assets/app.css', '/bundles/c975lui/x.js'] as $path) {
            $event = $this->exhaust($listener, $path);
            $this->assertNull($event->getResponse(), $path . ' should not be counted');
        }
    }

    // A machine is routinely handed a whole /64, so counting the full address would let one scraper walk through the ceiling as often as it renumbers itself
    public function testIpv6IsCountedOnItsSixtyFourPrefix(): void
    {
        $listener = $this->createListener();
        $this->exhaust($listener, '/', '2001:db8:1:2::1');

        $event = $this->createRequestEvent('/', '2001:db8:1:2::ffff');
        $listener->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
    }

    public function testASeparateAddressKeepsItsOwnAllowance(): void
    {
        $listener = $this->createListener();
        $this->exhaust($listener);

        $event = $this->createRequestEvent('/', '198.51.100.4');
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
