<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Controller\LoginCodeController;
use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Security\TrustedDevice;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

// Holds an admin's password login from an unknown browser on the code page until the emailed code is typed, every other page redirecting there meanwhile
class LoginCodeSubscriber implements EventSubscriberInterface
{
    // The password form only: Google's login, remember-me and the other programmatic ones never reach it
    private const string LOGIN_ROUTE = 'app_login';

    // What stays reachable while the code is awaited, the way out included
    private const array OPEN_ROUTES = [LoginCodeController::ROUTE, LoginCodeController::RESEND_ROUTE, 'app_logout'];

    public function __construct(
        private readonly LoginCode $loginCode,
        private readonly TrustedDevice $trustedDevice,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 7 runs after the firewall (8), the session being the only thing read
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    // Parks the login and sends the code, the page the firewall was heading to kept for afterwards
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if (self::LOGIN_ROUTE !== $request->attributes->get('_route') || !$request->hasSession()) {
            return;
        }

        $user = $event->getUser();
        if (!$this->loginCode->isRequired($event->getAuthenticatedToken()) || $this->trustedDevice->isTrusted($request, $user)) {
            return;
        }

        $target = $event->getResponse()?->headers->get('Location') ?? $request->getBasePath() . '/';
        if ($this->loginCode->start($request->getSession(), $user, $target)) {
            $event->getPassport()->getBadge(RememberMeBadge::class)?->disable();
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(LoginCodeController::ROUTE)));
        }
    }

    // Any other page sends back to the code while it is awaited, the profiler's own routes aside
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || !$this->loginCode->isPending($request->getSession())) {
            return;
        }

        $route = (string) $request->attributes->get('_route');
        if (!\in_array($route, self::OPEN_ROUTES, true) && !str_starts_with($route, '_')) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(LoginCodeController::ROUTE)));
        }
    }
}
