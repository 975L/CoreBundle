<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\TrustedDevice;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TrustedDeviceTest extends TestCase
{
    // The browser given the mark is recognized for that account
    public function testRecognizesTheBrowserItMarked(): void
    {
        $user = new UserStub()->setPassword('hash');

        $this->assertTrue(new TrustedDevice('secret')->isTrusted($this->marked($user), $user));
    }

    // Another account on the same browser is not vouched for by the first one's mark
    public function testTheMarkBelongsToOneAccount(): void
    {
        $user = new UserStub()->setPassword('hash');

        $this->assertFalse(new TrustedDevice('secret')->isTrusted($this->marked($user), new UserStub('other@example.test')->setPassword('hash')));
    }

    // A new password forgets every browser
    public function testAPasswordChangeForgetsTheBrowser(): void
    {
        $user = new UserStub()->setPassword('hash');
        $request = $this->marked($user);

        $this->assertFalse(new TrustedDevice('secret')->isTrusted($request, $user->setPassword('new-hash')));
    }

    // A mark forged, stretched or missing is refused
    public function testRefusesAnythingElse(): void
    {
        $user = new UserStub()->setPassword('hash');
        $device = new TrustedDevice('secret');
        [, $signature] = explode('.', (string) $this->marked($user)->cookies->get(TrustedDevice::COOKIE));

        $this->assertFalse($device->isTrusted(new Request(), $user));
        $this->assertFalse($device->isTrusted(new Request(cookies: [TrustedDevice::COOKIE => (time() + 999999999) . '.' . $signature]), $user));
        $this->assertFalse($device->isTrusted(new Request(cookies: [TrustedDevice::COOKIE => 'garbage']), $user));
    }

    // Kept for thirty days, out of the page's scripts' reach
    public function testTheCookieLastsThirtyDays(): void
    {
        $cookie = new TrustedDevice('secret')->cookie(new Request(), new UserStub());

        $this->assertEqualsWithDelta(time() + 30 * 86400, $cookie->getExpiresTime(), 5);
        $this->assertTrue($cookie->isHttpOnly());
    }

    // A request carrying the cookie TrustedDevice gave that account
    private function marked(UserStub $user): Request
    {
        $cookie = new TrustedDevice('secret')->cookie(new Request(), $user);

        return new Request(cookies: [TrustedDevice::COOKIE => (string) $cookie->getValue()]);
    }
}
