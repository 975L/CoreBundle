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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Sends a login post carrying no usable username straight back to the form. Scanners post to the login route with none of the expected fields, and FormLoginAuthenticator answers that with a BadRequestHttpException - a legitimate 400, but one the kernel error listener logs at ERROR level, so a few bots are enough to bury the real errors of a production log. Nothing is being let through here: such a request could never authenticate anyone, it just gets the redirect a failed login would have gotten anyway, without the exception.
class LoginRequestSubscriber implements EventSubscriberInterface
{
    // Name of the login route, as scaffolded by this bundle and used across the c975L apps
    private const LOGIN_ROUTE = 'app_login';

    // Default username_parameter of Symfony's form_login
    private const USERNAME_PARAMETER = '_username';

    public static function getSubscribedEvents(): array
    {
        // Priority 9 runs after RouterListener (32), so the route is known, and before the firewall (8), so the authenticator never sees the request
        return [KernelEvents::REQUEST => ['onKernelRequest', 9]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST') || self::LOGIN_ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        // Read through all() rather than get(): an InputBag throws on a non-scalar value, which is one of the very shapes to catch here
        $username = $request->request->all()[self::USERNAME_PARAMETER] ?? null;

        // An empty string is left alone: the authenticator turns that one into a BadCredentialsException, which is the ordinary failed-login path and is not logged as an error
        if (\is_string($username) || $username instanceof \Stringable) {
            return;
        }

        $event->setResponse(new RedirectResponse($request->getUri()));
    }
}
