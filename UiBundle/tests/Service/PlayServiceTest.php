<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Play;
use c975L\UiBundle\Repository\PlayRepository;
use c975L\UiBundle\Service\PlayService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// A play is one row under its holder's key, and a device's plays move to the account only when they are not already there
class PlayServiceTest extends TestCase
{
    public function testAPlayIsWrittenUnderItsHolder(): void
    {
        $repository = $this->createStub(PlayRepository::class);
        $repository->method('countsByHolder')->willReturn(['book:12' => 1]);

        $persisted = null;
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $play) use (&$persisted): void {
            $persisted = $play;
        });

        $counts = new PlayService($repository, $entityManager)->record('book', 12, 'u42');

        $this->assertInstanceOf(Play::class, $persisted);
        $this->assertSame(['book', 12, 'u42'], [$persisted->getOwnerType(), $persisted->getOwnerId(), $persisted->getHolder()]);
        $this->assertSame(['book:12' => 1], $counts);
    }

    public function testADevicesPlaysMoveToTheAccount(): void
    {
        $repository = $this->createMock(PlayRepository::class);
        $repository->expects($this->once())->method('moveHolder')->with('token', 'u42');

        $service = new PlayService($repository, $this->createStub(EntityManagerInterface::class));
        $service->merge('token', 'u42');
        // Already its own: nothing to move
        $service->merge('u42', 'u42');
    }
}
