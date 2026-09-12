<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Controller\Management\DashboardController;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\HealthCheck;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// Priority 200: below ValidateRequestListener (256), which is what reports a malformed forwarded header cleanly, and above SessionListener (128) so a refused request is answered before a session exists. That ordering is the whole point - a session costs a second database connection, on top of Doctrine's, and the burst this guards against is precisely the one that runs the connection budget out
#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest', priority: 200)]
class RateLimitListener
{
    // Paths that never count against a visitor: the way back in, the console, what a monitoring console reads, and Symfony's own dev tools
    private const array EXEMPT_PREFIXES = [
        '/login',
        '/status/report',
        '/_wdt',
        '/_profiler',
        '/_error',
        '/_fragment',
        '/assets/',
        '/bundles/',
        '/media/',
        '/images/',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly RateLimiterFactoryInterface $frontRequestLimiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isLimited($request)) {
            return;
        }

        $limit = $this->frontRequestLimiter->create($this->clientKey($request))->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $event->setResponse($this->refusedResponse($limit->getRetryAfter()->getTimestamp() - time()));
    }

    // Plain text and no template: this answer is built before the session exists, and rendering Twig here would reach into components that expect one
    private function refusedResponse(int $retryAfter): Response
    {
        $response = new Response('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->set('Retry-After', (string) max(1, $retryAfter));

        // Nothing about a refusal belongs in a shared cache: the next visitor through the same proxy has their own budget
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    // Whether this request counts at all
    private function isLimited(Request $request): bool
    {
        if (true !== $this->configService->get('site-rate-limit')) {
            return false;
        }

        // Our own probes are let through by name: the console fires them in bursts far above any ceiling a visitor would meet, and refusing them would turn every run into a page of false alarms
        if (HealthCheck::isProbe($request->headers->get('User-Agent'))) {
            return false;
        }

        $path = $request->getPathInfo();
        if (DashboardController::isManagementPath($path)) {
            return false;
        }

        return array_all(self::EXEMPT_PREFIXES, static fn (string $prefix): bool => !str_starts_with($path, $prefix));
    }

    // One budget per address, IPv6 counted on its /64. A single machine is routinely handed that whole block, so counting the full address lets one scraper walk through the ceiling as many times as it cares to renumber itself
    private function clientKey(Request $request): string
    {
        $ip = $request->getClientIp();
        if (null === $ip) {
            return 'unknown';
        }

        $packed = @inet_pton($ip);
        if (false === $packed || 16 !== \strlen($packed)) {
            return $ip;
        }

        return bin2hex(substr($packed, 0, 8)) . '::/64';
    }
}
