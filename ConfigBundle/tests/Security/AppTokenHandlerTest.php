<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AppToken;
use c975L\ConfigBundle\Security\AppTokenHandler;
use c975L\ConfigBundle\Service\AppTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

// A token the site issued names its account, any other is turned away
class AppTokenHandlerTest extends TestCase
{
    public function testAnIssuedTokenNamesItsAccount(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('parent@example.com');
        $manager = $this->createStub(AppTokenManager::class);
        $manager->method('find')->willReturn(new AppToken()->setUser($user));

        $this->assertSame('parent@example.com', new AppTokenHandler($manager)->getUserBadgeFrom('the-token')->getUserIdentifier());
    }

    public function testATokenNobodyIssuedIsTurnedAway(): void
    {
        $manager = $this->createStub(AppTokenManager::class);
        $manager->method('find')->willReturn(null);

        $this->expectException(BadCredentialsException::class);

        new AppTokenHandler($manager)->getUserBadgeFrom('a-token-nobody-issued');
    }
}
