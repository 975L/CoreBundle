<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\NotFound;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Listener\NotFoundRedirectListener;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\TestCase;

class NotFoundRedirectListenerTest extends TestCase
{
    // A redirect answering for a broken link takes it off the list
    public function testANewRedirectDeletesItsBrokenLink(): void
    {
        $repository = $this->createMock(NotFoundRepository::class);
        $repository->expects($this->once())->method('deleteByPath')->with('/histoire/disparue');

        $this->persist($repository, new Redirect()->setFromPath('histoire/disparue'));
    }

    // Only a Redirect answers for a path
    public function testAnotherEntityIsLeftAlone(): void
    {
        $repository = $this->createMock(NotFoundRepository::class);
        $repository->expects($this->never())->method('deleteByPath');

        $this->persist($repository, new NotFound());
    }

    // Fires postPersist as Doctrine would
    private function persist(NotFoundRepository $repository, object $entity): void
    {
        $listener = new NotFoundRedirectListener($repository);
        $listener->postPersist(new PostPersistEventArgs($entity, $this->createStub(EntityManagerInterface::class)));
    }
}
