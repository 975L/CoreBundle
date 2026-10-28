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
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\EventSubscriber\AppTokenSubscriber;
use c975L\ConfigBundle\Repository\AppTokenRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class AppTokenSubscriberTest extends TestCase
{
    // An anonymized account keeps its row, so its apps are signed out here rather than by the cascade
    public function testAnAnonymizedAccountLosesItsTokens(): void
    {
        $user = $this->createStub(InactivityAwareInterface::class);
        $repository = $this->createMock(AppTokenRepository::class);
        $repository->expects($this->once())->method('deleteOfUser')->with($user);

        $this->assertSame(UserAnonymizedEvent::class, new \ReflectionMethod(AppTokenSubscriber::class, 'onUserAnonymized')->getAttributes(AsEventListener::class)[0]->newInstance()->event);

        new AppTokenSubscriber($repository)->onUserAnonymized(new UserAnonymizedEvent($user));
    }
}
