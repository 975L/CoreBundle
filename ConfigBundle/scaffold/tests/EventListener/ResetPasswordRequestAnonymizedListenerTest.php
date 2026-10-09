<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\ResetPasswordRequestAnonymizedListener;
use App\Repository\ResetPasswordRequestRepository;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use PHPUnit\Framework\TestCase;

class ResetPasswordRequestAnonymizedListenerTest extends TestCase
{
    // The anonymized account is the one whose pending requests go
    public function testAnAnonymizedAccountHasItsRequestsDeleted(): void
    {
        $user = new User();

        $repository = $this->createMock(ResetPasswordRequestRepository::class);
        $repository->expects($this->once())->method('removeForUser')->with($user)->willReturn(1);

        new ResetPasswordRequestAnonymizedListener($repository)(new UserAnonymizedEvent($user));
    }

    // A user class other than the site's own holds no request of this entity
    public function testAnotherUserClassIsLeftAlone(): void
    {
        $repository = $this->createMock(ResetPasswordRequestRepository::class);
        $repository->expects($this->never())->method('removeForUser');

        new ResetPasswordRequestAnonymizedListener($repository)(new UserAnonymizedEvent($this->createStub(InactivityAwareInterface::class)));
    }
}
