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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class CspNonceCookieSubscriberTest extends TestCase
{
    // Runs ahead of NelmioSecurityBundle's SignedCookieListener, which signs on this same event at priority -10000
    public function testSubscribesAheadOfTheSigningListener(): void
    {
        $this->assertSame(['onKernelResponse', 10], CspNonceCookieSubscriber::getSubscribedEvents()[\Symfony\Component\HttpKernel\KernelEvents::RESPONSE]);
    }

    // The nonce the generator had to create is sent, so the rest of the visit reuses it
    public function testSendsTheNonceLeftOnTheRequest(): void
    {
        $request = new Request();
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event($request, $response));

        $cookie = $this->nonceCookie($response);
        $this->assertNotNull($cookie);
        $this->assertSame(str_repeat('a', 32), $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(0, $cookie->getExpiresTime());
    }

    // A visit already carrying the cookie left nothing on the request, so nothing is sent again
    public function testSendsNothingWhenTheRequestCarriesNoNonce(): void
    {
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event(new Request(), $response));

        $this->assertNull($this->nonceCookie($response));
    }

    // A sub-request renders a fragment of a page whose own response carries the cookie already
    public function testSendsNothingOnSubRequest(): void
    {
        $request = new Request();
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event($request, $response, HttpKernelInterface::SUB_REQUEST));

        $this->assertNull($this->nonceCookie($response));
    }

    // Over https the "__Host-" prefix applies, which a browser refuses to accept from a subdomain adding a Domain: the signature says the server issued the value, never that this visitor was handed it. The prefix also demands Secure and a "/" path, both of which the cookie carries
    public function testSendsAHostPrefixedCookieOverHttps(): void
    {
        $request = Request::create('https://example.com/');
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event($request, $response));

        $cookie = $this->nonceCookie($response);
        $this->assertNotNull($cookie);
        $this->assertSame(CspNonceCookieSubscriber::COOKIE_NAME_SECURE, $cookie->getName());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    // The prefix requires Secure, which a plain http request cannot carry - a local "symfony serve" over http would otherwise get a cookie the browser drops
    public function testSendsThePlainNameOverHttp(): void
    {
        $request = Request::create('http://example.com/');
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertSame(CspNonceCookieSubscriber::COOKIE_NAME, $this->nonceCookie($response)?->getName());
    }

    // A shared cache storing the response along with its Set-Cookie would serve one visitor's nonce to everyone else, which is a nonce no longer worth having
    public function testMarksTheResponseCarryingTheCookieAsPrivate(): void
    {
        $request = Request::create('https://example.com/');
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        new CspNonceCookieSubscriber()->onKernelResponse($this->event($request, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    private function nonceCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (\in_array($cookie->getName(), [CspNonceCookieSubscriber::COOKIE_NAME, CspNonceCookieSubscriber::COOKIE_NAME_SECURE], true)) {
                return $cookie;
            }
        }

        return null;
    }

    private function event(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(KernelInterface::class), $request, $type, $response);
    }
}
