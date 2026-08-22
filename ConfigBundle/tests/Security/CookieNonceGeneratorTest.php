<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\EventSubscriber\CspNonceCookieSubscriber;
use c975L\ConfigBundle\Security\CookieNonceGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CookieNonceGeneratorTest extends TestCase
{
    private const string NONCE_FORMAT = '/^[0-9a-f]{32}$/';

    // No current request at all (console, worker) has nowhere to keep a nonce, so a fresh random one comes back
    public function testGenerateReturnsRandomNonceWhenNoCurrentRequest(): void
    {
        $nonce = new CookieNonceGenerator(new RequestStack())->generate();

        $this->assertMatchesRegularExpression(self::NONCE_FORMAT, $nonce);
    }

    // A first visit carries no cookie, so a nonce is generated and left on the request for CspNonceCookieSubscriber to send
    public function testGenerateStoresNewNonceOnRequestWhenNoCookie(): void
    {
        $request = new Request();

        $nonce = new CookieNonceGenerator(new RequestStack([$request]))->generate();

        $this->assertMatchesRegularExpression(self::NONCE_FORMAT, $nonce);
        $this->assertSame($nonce, $request->attributes->get(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE));
    }

    // The nonce of a later page of the same visit comes from the cookie, and nothing is sent again
    public function testGenerateReusesNonceFromCookie(): void
    {
        $request = new Request(cookies: [CspNonceCookieSubscriber::COOKIE_NAME => str_repeat('a', 32)]);

        $nonce = new CookieNonceGenerator(new RequestStack([$request]))->generate();

        $this->assertSame(str_repeat('a', 32), $nonce);
        $this->assertFalse($request->attributes->has(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE));
    }

    // A cookie that is not a nonce is replaced rather than echoed into the CSP header, which SignedCookieListener already makes unreachable by removing any cookie whose signature does not match
    public function testGenerateReplacesMalformedCookie(): void
    {
        $request = new Request(cookies: [CspNonceCookieSubscriber::COOKIE_NAME => 'nonce-bidon']);

        $nonce = new CookieNonceGenerator(new RequestStack([$request]))->generate();

        $this->assertMatchesRegularExpression(self::NONCE_FORMAT, $nonce);
        $this->assertNotSame('nonce-bidon', $nonce);
    }

    // Both the CSP header and the markup of one response have to carry the very same nonce
    public function testGenerateReturnsSameNonceTwiceWithinOneRequest(): void
    {
        $generator = new CookieNonceGenerator(new RequestStack([new Request()]));

        $this->assertSame($generator->generate(), $generator->generate());
    }

    // An error page is never part of a Turbo visit, and a crawler hitting dead urls should leave nothing behind, so no nonce is remembered and no cookie is sent
    public function testGenerateSetsNoNonceOnErrorPage(): void
    {
        $request = new Request();
        $request->attributes->set('exception', new NotFoundHttpException());

        $nonce = new CookieNonceGenerator(new RequestStack([$request]))->generate();

        $this->assertMatchesRegularExpression(self::NONCE_FORMAT, $nonce);
        $this->assertFalse($request->attributes->has(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE));
    }

    // Over https the cookie carries the "__Host-" prefix, so that is the name read back - the plain one left over from an earlier visit is simply not a nonce this request holds
    public function testGenerateReusesTheHostPrefixedNonceOverHttps(): void
    {
        $request = Request::create('https://example.com/', cookies: [CspNonceCookieSubscriber::COOKIE_NAME_SECURE => str_repeat('b', 32)]);

        $nonce = new CookieNonceGenerator(new RequestStack([$request]))->generate();

        $this->assertSame(str_repeat('b', 32), $nonce);
        $this->assertFalse($request->attributes->has(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE));
    }
}
