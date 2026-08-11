<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\EventSubscriber\LoginRequestSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LoginRequestSubscriberTest extends TestCase
{
    private function createEvent(string $method, ?string $route, array $parameters = [], bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/login', $method, $parameters);
        $request->attributes->set('_route', $route);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }

    // Runs after RouterListener (32), which sets the route, and before the firewall (8), which would otherwise throw
    public function testGetSubscribedEventsRunsBetweenRouterAndFirewall(): void
    {
        $this->assertSame([KernelEvents::REQUEST => ['onKernelRequest', 9]], LoginRequestSubscriber::getSubscribedEvents());
    }

    // The case seen in production: a post with none of the form fields
    public function testOnKernelRequestRedirectsPostWithoutUsername(): void
    {
        $event = $this->createEvent('POST', 'app_login');

        new LoginRequestSubscriber()->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/login', $response->getTargetUrl());
    }

    // An array value reaches the authenticator the same way a missing one does, and would throw just as well
    public function testOnKernelRequestRedirectsPostWithArrayUsername(): void
    {
        $event = $this->createEvent('POST', 'app_login', ['_username' => ['a', 'b']]);

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    // A real login attempt must go through untouched
    public function testOnKernelRequestLeavesPostWithUsernameAlone(): void
    {
        $event = $this->createEvent('POST', 'app_login', ['_username' => 'user@example.com', '_password' => 'secret']);

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // An empty username is the ordinary failed login, handled by the authenticator without an error log
    public function testOnKernelRequestLeavesEmptyUsernameAlone(): void
    {
        $event = $this->createEvent('POST', 'app_login', ['_username' => '']);

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // Displaying the form is not a login attempt
    public function testOnKernelRequestLeavesGetAlone(): void
    {
        $event = $this->createEvent('GET', 'app_login');

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // Any other route posting without a _username field is none of this subscriber's business
    public function testOnKernelRequestLeavesOtherRoutesAlone(): void
    {
        $event = $this->createEvent('POST', 'app_contact');

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // Sub-requests never carry the incoming login post
    public function testOnKernelRequestLeavesSubRequestAlone(): void
    {
        $event = $this->createEvent('POST', 'app_login', [], false);

        new LoginRequestSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
