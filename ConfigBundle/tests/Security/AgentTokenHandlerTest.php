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
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Security\AgentTokenHandler;
use c975L\ConfigBundle\Service\AgentTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

// A token an account made names that account, an unknown or expired one is turned away
class AgentTokenHandlerTest extends TestCase
{
    public function testAKnownTokenNamesItsAccount(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user-1@example.com');
        $manager = $this->createStub(AgentTokenManager::class);
        $manager->method('find')->willReturn(new AgentToken()->setUser($user));

        $this->assertSame('user-1@example.com', new AgentTokenHandler($manager)->getUserBadgeFrom('token-1')->getUserIdentifier());
    }

    public function testAnUnknownTokenIsTurnedAway(): void
    {
        $manager = $this->createStub(AgentTokenManager::class);
        $manager->method('find')->willReturn(null);

        $this->expectException(BadCredentialsException::class);

        new AgentTokenHandler($manager)->getUserBadgeFrom('token-2');
    }
}
