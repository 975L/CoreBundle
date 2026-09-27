<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\Controller\LoginCodeController;
use c975L\ConfigBundle\EventSubscriber\LoginCodeSubscriber;
use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Security\TrustedDevice;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginCodeSubscriberTest extends TestCase
{
    // An admin's password login from an unknown browser is held on the code page
    public function testHoldsThePasswordLoginOfAnAdmin(): void
    {
        $event = $this->loginEvent('app_login');
        $this->subscriber(required: true)->onLoginSuccess($event);

        $this->assertSame('/login/code', $event->getResponse()?->headers->get('Location'));
    }

    // The remember-me cookie would let the held login in without the code, so it is not set
    public function testDropsTheRememberMeCookieOfAHeldLogin(): void
    {
        $event = $this->loginEvent('app_login');
        $badge = new RememberMeBadge()->enable();
        $event->getPassport()->addBadge($badge);
        $this->subscriber(required: true)->onLoginSuccess($event);

        $this->assertFalse($badge->isEnabled());
    }

    // Google's login, remember-me and the rest never go through the password form
    public function testLeavesTheOtherLoginsAlone(): void
    {
        $event = $this->loginEvent('config_oauth_login_check');
        $this->subscriber(required: true)->onLoginSuccess($event);

        $this->assertSame('/management', $event->getResponse()?->headers->get('Location'));
    }

    // A browser already confirmed, or an account the setting does not cover, goes straight in
    public function testLetsATrustedBrowserOrAMemberThrough(): void
    {
        $user = new UserStub();
        $cookie = new TrustedDevice('secret')->cookie(new Request(), $user);
        $trusted = $this->loginEvent('app_login', [TrustedDevice::COOKIE => (string) $cookie->getValue()], $user);
        $this->subscriber(required: true)->onLoginSuccess($trusted);
        $member = $this->loginEvent('app_login');
        $this->subscriber(required: false)->onLoginSuccess($member);

        $this->assertSame('/management', $trusted->getResponse()?->headers->get('Location'));
        $this->assertSame('/management', $member->getResponse()?->headers->get('Location'));
    }

    // While the code is awaited, any page sends back to it but the code's own and the logout
    public function testLocksTheSiteWhileTheCodeIsAwaited(): void
    {
        $subscriber = $this->subscriber(pending: true);

        $blocked = $this->requestEvent('config_account');
        $subscriber->onKernelRequest($blocked);
        $this->assertSame('/login/code', $blocked->getResponse()?->headers->get('Location'));

        foreach ([LoginCodeController::ROUTE, 'app_logout', '_wdt'] as $route) {
            $open = $this->requestEvent($route);
            $subscriber->onKernelRequest($open);
            $this->assertNull($open->getResponse(), $route);
        }
    }

    // Nothing awaited, nothing locked
    public function testLeavesTheSiteOpenOtherwise(): void
    {
        $event = $this->requestEvent('config_account');
        $this->subscriber(pending: false)->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    private function subscriber(bool $required = false, bool $pending = false): LoginCodeSubscriber
    {
        $loginCode = $this->createStub(LoginCode::class);
        $loginCode->method('isRequired')->willReturn($required);
        $loginCode->method('start')->willReturn(true);
        $loginCode->method('isPending')->willReturn($pending);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login/code');

        return new LoginCodeSubscriber($loginCode, new TrustedDevice('secret'), $urlGenerator);
    }

    // A login on that route, the firewall heading to the back office
    /** @param array<string, string> $cookies */
    private function loginEvent(string $route, array $cookies = [], ?UserStub $user = null): LoginSuccessEvent
    {
        $request = new Request(attributes: ['_route' => $route], cookies: $cookies);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $passport = new SelfValidatingPassport(new UserBadge('user@example.test', static fn (): UserStub => $user ?? new UserStub()));

        return new LoginSuccessEvent($this->createStub(AuthenticatorInterface::class), $passport, $this->createStub(TokenInterface::class), $request, new RedirectResponse('/management'), 'main');
    }

    // A page of that route asked within an existing session
    private function requestEvent(string $route): RequestEvent
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request(attributes: ['_route' => $route], cookies: [$session->getName() => 'session-id']);
        $request->setSession($session);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
