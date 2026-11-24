<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Repository\AgentTokenRepository;
use c975L\ConfigBundle\Service\AgentTokenManager;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class AgentTokenManagerTest extends TestCase
{
    // A token issued is found by its secret, only its hash kept; an unknown one, one past its day or a malformed header finds nothing
    public function testIssueAndFind(): void
    {
        $agentToken = new AgentToken()->setUser($this->createStub(UserInterface::class))->setName('agent-1');
        $secret = new AgentTokenManager($this->createStub(AgentTokenRepository::class), $this->createStub(EntityManagerInterface::class), new MockClock())->issue($agentToken);
        $this->assertSame(hash('sha256', $secret), $agentToken->getTokenHash());

        $repository = $this->createStub(AgentTokenRepository::class);
        $repository->method('findOneByTokenHash')->willReturnCallback(static fn (string $hash): ?AgentToken => $hash === $agentToken->getTokenHash() ? $agentToken : null);
        $manager = new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock('2026-11-21 10:00'));

        $this->assertSame($agentToken, $manager->fromHeader('Bearer ' . $secret));
        $this->assertEquals(new \DateTimeImmutable('2026-11-21 10:00'), $agentToken->getLastUse());
        $this->assertNull($manager->find('other'));
        $this->assertNull($manager->fromHeader($secret));
        $this->assertNull($manager->fromHeader(null));

        $agentToken->setExpiration(new \DateTimeImmutable('2026-11-20'));
        $expired = new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock('2026-11-21 10:00'));
        $this->assertNull($expired->find($secret));
    }

    // A token of a disabled account opens nothing, the same token opening again once the account is enabled
    public function testADisabledAccountOpensNothing(): void
    {
        $enabled = false;
        $user = $this->createStub(InactivityAwareInterface::class);
        $user->method('isEnabled')->willReturnCallback(static function () use (&$enabled): bool {
            return $enabled;
        });
        $agentToken = new AgentToken()->setUser($user)->setName('agent-1');
        $repository = $this->createStub(AgentTokenRepository::class);
        $repository->method('findOneByTokenHash')->willReturn($agentToken);

        $this->assertNull(new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock())->find('secret-1'));

        $enabled = true;
        $this->assertSame($agentToken, new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock())->find('secret-1'));
    }

    // A token of a disabled account opens nothing, even when its User does not implement InactivityAwareInterface
    public function testADisabledAccountWithoutInactivityOpensNothing(): void
    {
        $user = new UserStub();
        $agentToken = new AgentToken()->setUser($user)->setName('agent-1');
        $repository = $this->createStub(AgentTokenRepository::class);
        $repository->method('findOneByTokenHash')->willReturn($agentToken);

        $this->assertNull(new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock())->find('secret-1'));

        $user->setIsEnabled(true);
        $this->assertSame($agentToken, new AgentTokenManager($repository, $this->createStub(EntityManagerInterface::class), new MockClock())->find('secret-1'));
    }

    // A token still opens on its expiry day, the day after it opens nothing
    public function testTheExpiryDayStillOpens(): void
    {
        $agentToken = new AgentToken()->setExpiration(new \DateTimeImmutable('2026-11-21'));

        $this->assertFalse($agentToken->isExpired(new \DateTimeImmutable('2026-11-21 23:30')));
        $this->assertTrue($agentToken->isExpired(new \DateTimeImmutable('2026-11-22 00:01')));
        $this->assertFalse(new AgentToken()->isExpired(new \DateTimeImmutable('2099-01-01')));
    }

    // A token allows every bundle while none is named, else only those named
    public function testAllows(): void
    {
        $agentToken = new AgentToken();
        $this->assertTrue($agentToken->allows('catalog'));

        $agentToken->setBundles(['catalog', ' ', 'catalog']);
        $this->assertSame(['catalog'], $agentToken->getBundles());
        $this->assertFalse($agentToken->allows('item'));
    }
}
