<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Sends the nonce CookieNonceGenerator had to generate, so the rest of the visit reuses it. The constants live here rather than on the generator, which implements a NelmioSecurityBundle interface and is therefore not even autoloadable without that optional bundle
class CspNonceCookieSubscriber implements EventSubscriberInterface
{
    public const string COOKIE_NAME = 'csp_nonce';

    // The "__Host-" prefix is what a browser refuses to accept with a Domain attribute, closing the one way a subdomain has of handing the parent domain a nonce it knows: the signature only says the server issued the value, never to whom. It requires Secure, hence the two names rather than one
    public const string COOKIE_NAME_SECURE = '__Host-csp_nonce';

    // Where the generator leaves a freshly generated nonce for this subscriber to find
    public const string REQUEST_ATTRIBUTE = '_csp_nonce_to_send';

    // The single place deciding which of the two names applies, read by CookieNonceGenerator too
    public static function cookieName(Request $request): string
    {
        return $request->isSecure() ? self::COOKIE_NAME_SECURE : self::COOKIE_NAME;
    }

    public static function getSubscribedEvents(): array
    {
        // Ahead of NelmioSecurityBundle's SignedCookieListener, which signs on this same event at priority -10000: an unsigned nonce cookie is one its holder could rewrite, and a nonce the visitor chooses is a nonce an injected script can carry
        return [KernelEvents::RESPONSE => ['onKernelResponse', 10]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $nonce = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!\is_string($nonce)) {
            return;
        }

        // A cookie without expiry, so it lasts the browser session and no longer: the nonce only has to outlive the visit, exactly like the session entry it replaces. No domain and a "/" path, which "__Host-" also demands, and SameSite left to Cookie::create's own default of Lax
        $event->getResponse()->headers->setCookie(
            Cookie::create(self::cookieName($request), $nonce, 0, '/', null, $request->isSecure(), true, false)
        );

        // A shared cache storing this Set-Cookie would hand one visitor's nonce to everyone else - what the session listener used to mark for us
        $event->getResponse()->setPrivate();
    }
}
