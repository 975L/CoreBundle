<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\EventSubscriber\CspNonceCookieSubscriber;
use c975L\ConfigBundle\EventSubscriber\ProfilerNonceSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class ProfilerNonceSubscriberTest extends TestCase
{
    // Behind ProfilerListener (-1024), which sets the X-Debug-Token this reads, and ahead of WebDebugToolbarListener (-2048), which reads what this writes
    public function testSubscribesBetweenTheProfilerAndItsToolbar(): void
    {
        $this->assertSame(['onKernelResponse', -1500], ProfilerNonceSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE]);
    }

    // The nonce the visit already carries, so the toolbar's inline scripts pass the CSP of the document Turbo keeps
    public function testHandsTheVisitNonceToTheToolbar(): void
    {
        $request = new Request();
        $request->cookies->set(CspNonceCookieSubscriber::COOKIE_NAME, str_repeat('a', 32));
        $response = $this->response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertSame(str_repeat('a', 32), $response->headers->get('X-SymfonyProfiler-Script-Nonce'));
        $this->assertSame(str_repeat('a', 32), $response->headers->get('X-SymfonyProfiler-Style-Nonce'));
    }

    // The first request of a visit has no cookie yet, the nonce being left on the request by CookieNonceGenerator
    public function testReadsTheNonceLeftOnTheRequest(): void
    {
        $request = new Request();
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('b', 32));
        $response = $this->response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertSame(str_repeat('b', 32), $response->headers->get('X-SymfonyProfiler-Script-Nonce'));
    }

    // Nobody would read these headers back off a response the profiler is not on, and they would be served to the browser rather than removed
    public function testWritesNothingWithoutTheProfiler(): void
    {
        $request = new Request();
        $request->cookies->set(CspNonceCookieSubscriber::COOKIE_NAME, str_repeat('a', 32));
        $response = new Response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Script-Nonce'));
    }

    // A visit carrying no nonce at all gets none minted here: it would come too late for CspNonceCookieSubscriber to send its cookie, so the next request would find nothing to reuse and the toolbar would be blocked again
    public function testMintsNoNonceOfItsOwn(): void
    {
        $response = $this->response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event(new Request(), $response));

        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Script-Nonce'));
    }

    // A cookie whose value is not one this server wrote is left alone, the same shape CookieNonceGenerator demands
    public function testIgnoresAMalformedNonce(): void
    {
        $request = new Request();
        $request->cookies->set(CspNonceCookieSubscriber::COOKIE_NAME, 'not-a-nonce');
        $response = $this->response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Script-Nonce'));
    }

    // A sub-request renders a fragment of a page whose own response carries the toolbar
    public function testWritesNothingOnSubRequest(): void
    {
        $request = new Request();
        $request->cookies->set(CspNonceCookieSubscriber::COOKIE_NAME, str_repeat('a', 32));
        $response = $this->response();

        new ProfilerNonceSubscriber()->onKernelResponse($this->event($request, $response, HttpKernelInterface::SUB_REQUEST));

        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Script-Nonce'));
    }

    private function response(): Response
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'abc123');

        return $response;
    }

    private function event(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(KernelInterface::class), $request, $type, $response);
    }
}
