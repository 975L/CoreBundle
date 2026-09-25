<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\EventSubscriber\LastLoginSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LastLoginSubscriberTest extends TestCase
{
    // A login restarts the inactivity clock and withdraws the notice already sent
    public function testRecordsTheLoginAndWithdrawsTheNotice(): void
    {
        $user = $this->createMock(InactivityAwareInterface::class);
        $user->expects($this->once())->method('setLastLogin')->with($this->callback(fn (\DateTimeInterface $date): bool => abs($date->getTimestamp() - time()) < 5))->willReturnSelf();
        $user->expects($this->once())->method('setInactivityNoticeSentAt')->with(null)->willReturnSelf();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        new LastLoginSubscriber($entityManager)->onLoginSuccess($this->event($user));
    }

    // A user that doesn't track its inactivity is left alone, nothing being flushed for it
    public function testIgnoresAUserNotTrackingItsInactivity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        new LastLoginSubscriber($entityManager)->onLoginSuccess($this->event($this->createStub(UserInterface::class)));
    }

    private function event(UserInterface $user): LoginSuccessEvent
    {
        // The event reads its user from the passport, the one the authenticator loaded
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);

        return new LoginSuccessEvent($this->createStub(AuthenticatorInterface::class), $passport, $this->createStub(TokenInterface::class), new Request(), null, 'main');
    }
}
