<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\Controller\OAuthLoginController;
use c975L\ConfigBundle\EventSubscriber\OAuthLoginFlagSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class OAuthLoginFlagSubscriberTest extends TestCase
{
    // A login through the form after one through a provider gives the password change back
    public function testClearsTheFlagOnLogin(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(OAuthLoginController::SESSION_OAUTH_LOGIN, true);
        $request = new Request();
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'session-id');

        new OAuthLoginFlagSubscriber()->onLoginSuccess($this->event($request));

        $this->assertFalse($session->has(OAuthLoginController::SESSION_OAUTH_LOGIN));
    }

    // A stateless login carries no session to clear
    public function testIgnoresARequestWithoutSession(): void
    {
        new OAuthLoginFlagSubscriber()->onLoginSuccess($this->event(new Request()));

        $this->addToAssertionCount(1);
    }

    // A stateless firewall on a site with sessions enabled must not open one on every call
    public function testDoesNotStartASessionWithoutSessionCookie(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        new OAuthLoginFlagSubscriber()->onLoginSuccess($this->event($request));

        $this->assertFalse($session->isStarted());
    }

    private function event(Request $request): LoginSuccessEvent
    {
        return new LoginSuccessEvent($this->createStub(AuthenticatorInterface::class), $this->createStub(Passport::class), $this->createStub(TokenInterface::class), $request, null, 'main');
    }
}
