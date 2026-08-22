<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\EventSubscriber\CspNonceCookieSubscriber;
use Nelmio\SecurityBundle\ContentSecurityPolicy\NonceGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// NelmioSecurityBundle's default generator returns a fresh random nonce per request, but Turbo Drive/Frames/Streams re-merge and re-execute <script> tags from fetched HTML into the already-loaded document without a real navigation, so a per-request nonce mismatches the CSP header the browser already enforces and every re-executed script gets blocked - the nonce is kept stable for the whole visit, same fix as turbo-rails documents. Held in a signed cookie rather than in the session, which cost a connection to its store on every request of every anonymous visitor, ~1400 rows a day on a site nobody logs into
class CookieNonceGenerator implements NonceGeneratorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function generate(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        // Nothing to hold a nonce in a console or worker context, and an error page is never part of a Turbo visit so it needs no stable one - it also must set no cookie, a crawler hitting dead urls being exactly what should leave nothing behind
        if (null === $request || $request->attributes->has('exception')) {
            return bin2hex(random_bytes(16));
        }

        // Generated earlier in this same request: the CSP header and the markup have to carry the very same value
        $pending = $request->attributes->get(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE);
        if (\is_string($pending)) {
            return $pending;
        }

        // SignedCookieListener has already replaced this with the verified raw value, or removed the cookie outright when the signature did not match, so what is read here is what this server wrote
        $nonce = $request->cookies->get(CspNonceCookieSubscriber::cookieName($request));
        if (\is_string($nonce) && 1 === preg_match('/^[0-9a-f]{32}$/', $nonce)) {
            return $nonce;
        }

        $nonce = bin2hex(random_bytes(16));
        $request->attributes->set(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE, $nonce);

        return $nonce;
    }
}
