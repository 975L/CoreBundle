<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\OAuthLoginController;
use PHPUnit\Framework\TestCase;

// The one rule of this controller worth testing on its own: where it agrees to send a visitor back to once the provider answered. Reached by reflection rather than through a route, the alternative being a kernel and a firewall for a string check.
class OAuthLoginControllerTest extends TestCase
{
    private function relativePath(mixed $path): ?string
    {
        $method = new \ReflectionMethod(OAuthLoginController::class, 'relativePath');

        return $method->invoke(
            new \ReflectionClass(OAuthLoginController::class)->newInstanceWithoutConstructor(),
            $path
        );
    }

    public function testAPathOfThisSiteIsKept(): void
    {
        $this->assertSame('/shop/basket/paid/ABC123/deadbeefdeadbeef', $this->relativePath('/shop/basket/paid/ABC123/deadbeefdeadbeef'));
        $this->assertSame('/', $this->relativePath('/'));
        $this->assertSame('/account/orders?page=2', $this->relativePath('/account/orders?page=2'));
    }

    // An open redirect is a login page handing a visitor to whoever wrote the link they clicked - and "//host" is an absolute url a browser follows off-site just as "https://host" is
    public function testAnythingLeadingOffSiteIsDropped(): void
    {
        $this->assertNull($this->relativePath('//evil.test'));
        $this->assertNull($this->relativePath('https://evil.test'));
        $this->assertNull($this->relativePath('http://evil.test'));
        // Some browsers have read a backslash as a slash, which makes this an off-site host too
        $this->assertNull($this->relativePath('/\evil.test'));
        $this->assertNull($this->relativePath('/orders\..\..'));
    }

    public function testAnythingThatIsNotAPathIsDropped(): void
    {
        $this->assertNull($this->relativePath('relative/path'));
        $this->assertNull($this->relativePath(''));
        $this->assertNull($this->relativePath(null));
        $this->assertNull($this->relativePath(['/orders']));
    }
}
