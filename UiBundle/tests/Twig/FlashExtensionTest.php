<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\FlashExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

// The one guard standing between a first-time anonymous visitor and a session: the layout and the Form component both ask this before reading app.flashes, which starts one on its own
class FlashExtensionTest extends TestCase
{
    // Console or worker: no request to hold a flash, and nothing to render one into either
    public function testNoCurrentRequestHoldsNoFlash(): void
    {
        $this->assertFalse(new FlashExtension(new RequestStack())->canHoldFlash());
    }

    // A first visit carries neither the session cookie nor a started session, and is exactly what must not start one
    public function testAFirstVisitHoldsNoFlash(): void
    {
        $this->assertFalse($this->extension(new Request())->canHoldFlash());
    }

    // The visitor carries the session cookie, so a flash put on a previous request is waiting to be read
    public function testAVisitorCarryingTheSessionCookieHoldsAFlash(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request(cookies: [$session->getName() => 'a-session-id']);
        $request->setSession($session);

        $this->assertTrue($this->extension($request)->canHoldFlash());
    }

    // A login, a form submission: the request started the session itself, so the flash it just put is read on that very response
    public function testARequestThatStartedTheSessionHoldsAFlash(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);

        $this->assertTrue($this->extension($request)->canHoldFlash());
    }

    // A session attached but never started is what every anonymous request carries, and reading it is what would start it
    public function testASessionNeverStartedHoldsNoFlash(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->assertFalse($this->extension($request)->canHoldFlash());
    }

    private function extension(Request $request): FlashExtension
    {
        return new FlashExtension(new RequestStack([$request]));
    }
}
