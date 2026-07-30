<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\BlockUserListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

// The listener assigns whatever implements c975L\ConfigBundle\Contract\UserInterface, not the application's App\Entity\User directly, so a plain stub of that interface covers the "somebody is logged in" branches without the app being there
class BlockUserListenerTest extends TestCase
{
    private function createPersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    private function createUpdateArgs(object $entity, EntityManagerInterface $entityManager): PreUpdateEventArgs
    {
        $changeSet = [];

        return new PreUpdateEventArgs($entity, $entityManager, $changeSet);
    }

    // Skipped rather than stubbed while the interface ships in a ConfigBundle newer than the released one this checkout pulls - duplicating it here would hide the day the real one changes
    private function createUserStub(): UserInterface
    {
        if (!interface_exists(UserInterface::class)) {
            self::markTestSkipped('c975L\ConfigBundle\Contract\UserInterface not available in the installed c975l/config-bundle');
        }

        return $this->createStub(UserInterface::class);
    }

    public function testPrePersistIgnoresEntitiesThatAreNotBlockOrMedia(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        (new BlockUserListener($security))->prePersist($this->createPersistArgs(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function testPrePersistLeavesUserNullWhenNobodyIsLoggedInForMedia(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $media = new Media();

        (new BlockUserListener($security))->prePersist($this->createPersistArgs($media));

        $this->assertNull($media->getUser());
    }

    public function testPrePersistLeavesUserNullWhenNobodyIsLoggedInForBlock(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $block = new Block();

        (new BlockUserListener($security))->prePersist($this->createPersistArgs($block));

        $this->assertNull($block->getUser());
    }

    public function testPrePersistAssignsTheLoggedInUser(): void
    {
        $user = $this->createUserStub();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $media = new Media();

        (new BlockUserListener($security))->prePersist($this->createPersistArgs($media));

        $this->assertSame($user, $media->getUser());
    }

    // Regression guard: prePersist used to skip assignment entirely when the entity already had a user (e.g. explicitly set by an import/fixture loader before persist()) - it no longer does, by design (see the class comment: $user always reflects the last editor, not just the creator)
    public function testPrePersistOverwritesAnAlreadyAssignedUser(): void
    {
        $newUser = $this->createUserStub();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($newUser);

        $block = (new Block())->setUser($this->createUserStub());

        (new BlockUserListener($security))->prePersist($this->createPersistArgs($block));

        $this->assertSame($newUser, $block->getUser());
    }

    public function testPreUpdateIgnoresEntitiesThatAreNotBlockOrMedia(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('getUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getUnitOfWork');

        (new BlockUserListener($security))->preUpdate($this->createUpdateArgs(new \stdClass(), $entityManager));

        $this->addToAssertionCount(1);
    }

    // Nobody logged in (CLI import, expired session...): the changeset must not be recomputed for nothing - only a real assignment justifies the extra recompute cost
    public function testPreUpdateDoesNotRecomputeChangeSetWhenNobodyIsLoggedInForMedia(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getUnitOfWork');

        $media = new Media();

        (new BlockUserListener($security))->preUpdate($this->createUpdateArgs($media, $entityManager));

        $this->assertNull($media->getUser());
    }

    public function testPreUpdateDoesNotRecomputeChangeSetWhenNobodyIsLoggedInForBlock(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getUnitOfWork');

        $block = new Block();

        (new BlockUserListener($security))->preUpdate($this->createUpdateArgs($block, $entityManager));

        $this->assertNull($block->getUser());
    }

    // Somebody logged in: the entity's user is overwritten (even if it already had one - same "last editor, not just creator" intent as prePersist) and the changeset is recomputed so Doctrine actually includes "user" in the SQL UPDATE, even when it's the only field that changed
    public function testPreUpdateAssignsTheLoggedInUserAndRecomputesTheChangeSet(): void
    {
        $newUser = $this->createUserStub();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($newUser);

        // A real instance rather than a stub - PHPUnit's mock generator otherwise trips one of ClassMetadata's own @deprecated methods while building the double
        $classMetadata = new ClassMetadata(Block::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())
            ->method('recomputeSingleEntityChangeSet')
            ->with($classMetadata, $this->isInstanceOf(Block::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('getClassMetadata')->with(Block::class)->willReturn($classMetadata);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $block = (new Block())->setUser($this->createUserStub());

        (new BlockUserListener($security))->preUpdate($this->createUpdateArgs($block, $entityManager));

        $this->assertSame($newUser, $block->getUser());
    }
}
