<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Attribute\ActivityLogIgnored;
use c975L\ConfigBundle\Attribute\ActivityLogUnnamed;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Listener\ActivityLogListener;
use c975L\ConfigBundle\Management\ConfigLabelResolver;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;

class ActivityLogListenerTest extends TestCase
{
    /** @var list<ActivityLog> */
    private array $recorded = [];

    private function user(): UserInterface
    {
        return $this->createStub(UserInterface::class);
    }

    /** @return list<mixed> */
    private function recorded(string $getter): array
    {
        return array_map(static fn (ActivityLog $log): mixed => $log->{$getter}(), $this->recorded);
    }

    private function listener(?string $path = '/management/page/1/edit', ?UserInterface $user = null, ?ActivityLogRepository $repository = null, ?LoggerInterface $logger = null): ActivityLogListener
    {
        $requestStack = new RequestStack();
        if (null !== $path) {
            $requestStack->push(Request::create($path));
        }

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        if (null === $repository) {
            $repository = $this->createStub(ActivityLogRepository::class);
            $repository->method('record')->willReturnCallback(function (array $logs): void {
                $this->recorded = $logs;
            });
        }

        return new ActivityLogListener(
            $repository,
            $requestStack,
            $security,
            new ConfigLabelResolver(new IdentityTranslator()),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    /**
     * @param list<object>                     $insertions
     * @param list<object>                     $updates
     * @param list<object>                     $deletions
     * @param array<string, array<int, mixed>> $changeSet
     * @param list<PersistentCollection>       $collections
     */
    private function manager(array $insertions = [], array $updates = [], array $deletions = [], array $changeSet = [], array $collections = []): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn($collections);
        $unitOfWork->method('isScheduledForInsert')->willReturnCallback(static fn (object $entity): bool => \in_array($entity, $insertions, true));
        $unitOfWork->method('isScheduledForDelete')->willReturnCallback(static fn (object $entity): bool => \in_array($entity, $deletions, true));
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);
        $unitOfWork->method('getEntityIdentifier')->willReturn(['id' => 42]);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->method('getClassMetadata')->willReturnCallback(static fn (string $class): ClassMetadata => new ClassMetadata($class));

        return $manager;
    }

    private function collection(object $owner, string $field = 'categories', bool $owningSide = true): PersistentCollection
    {
        if ($owningSide) {
            $mapping = new ManyToManyOwningSideMapping($field, $owner::class, 'Target');
        } else {
            $mapping = new ManyToManyInverseSideMapping($field, $owner::class, 'Target');
            $mapping->mappedBy = 'owners';
        }

        $collection = new PersistentCollection($this->createStub(EntityManagerInterface::class), new ClassMetadata($owner::class), new ArrayCollection());
        $collection->setOwner($owner, $mapping);

        return $collection;
    }

    private function flush(ActivityLogListener $listener, EntityManagerInterface $manager): void
    {
        $listener->onFlush(new OnFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));
    }

    // Turning the maintenance mode on and off are the two rows the log has to tell apart, on a value stored as text
    public function testTheMaintenanceToggleRecordsTheNewStateAndWho(): void
    {
        $config = new Config()->setSlug('site-maintenance')->setKind(Config::TYPE_BOOL)->setLabel('Maintenance');

        $user = $this->user();
        $this->flush($this->listener('/management/config/maintenance-toggle', $user), $this->manager(updates: [$config], changeSet: ['value' => ['false', 'true'], 'modification' => [null, new \DateTime()]]));

        $this->assertCount(1, $this->recorded);
        $this->assertSame($user, $this->recorded[0]->getUser());
        $this->assertSame(ActivityLog::ACTION_UPDATE, $this->recorded[0]->getAction());
        $this->assertSame('Config', $this->recorded[0]->getEntityType());
        $this->assertSame('Maintenance', $this->recorded[0]->getLabel());
        $this->assertSame(['value' => true], $this->recorded[0]->getChanges());
        $this->assertSame('/management/config/maintenance-toggle', $this->recorded[0]->getPath());

        $this->flush($this->listener('/management/config/maintenance-toggle', $this->user()), $this->manager(updates: [$config], changeSet: ['value' => ['true', 'false']]));

        $this->assertSame(['value' => false], $this->recorded[0]->getChanges());
    }

    // A sensitive setting, a password or a page's text: only the name of the field may ever land in a dump
    public function testNoValueIsKept(): void
    {
        $config = new Config()->setSlug('site-api-key')->setKind(Config::TYPE_TEXT)->setLabel('Key');

        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [$config], changeSet: ['value' => ['old-secret', 'new-secret']]));

        $this->assertSame(['value' => null], $this->recorded[0]->getChanges());
        $this->assertNotContains('new-secret', $this->recorded[0]->getChanges());
    }

    // A flush touching only what every save touches decides nothing
    public function testARowCarryingOnlyBookkeepingFieldsIsSkipped(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [new ActivityLogListenerPage()], changeSet: ['modification' => [null, new \DateTime()], 'position' => [1, 2], 'user' => [null, null]]));

        $this->assertSame([], $this->recorded);
    }

    public function testTheRecycleBinHasItsOwnActions(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [new ActivityLogListenerPage()], changeSet: ['isDeleted' => [false, true]]));

        $this->assertSame(ActivityLog::ACTION_TRASH, $this->recorded[0]->getAction());
        $this->assertSame([], $this->recorded[0]->getChanges());

        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [new ActivityLogListenerPage()], changeSet: ['isDeleted' => [true, false]]));

        $this->assertSame(ActivityLog::ACTION_RESTORE, $this->recorded[0]->getAction());
    }

    public function testCreationsAndDeletionsAreRecordedWithTheirIdAndLabel(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(insertions: [new ActivityLogListenerPage()], deletions: [new ActivityLogListenerPage()]));

        $this->assertSame([ActivityLog::ACTION_CREATE, ActivityLog::ACTION_DELETE], $this->recorded('getAction'));
        $this->assertSame(['42', '42'], $this->recorded('getEntityId'));
        $this->assertSame(['Home', 'Home'], $this->recorded('getLabel'));
    }

    // The account is named by the relation, which its anonymization empties: a copy of the address here would outlive it
    public function testAnAccountIsNeverNamed(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [$this->user()], changeSet: ['roles' => [[], ['ROLE_ADMIN']]]));

        $this->assertNull($this->recorded[0]->getLabel());
    }

    // A gift card's code or a contributor's address would otherwise stay in the table and its dumps
    public function testAnUnnamedEntityKeepsItsTypeAndIdOnly(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(deletions: [new ActivityLogListenerSecret()]));

        $this->assertNull($this->recorded[0]->getLabel());
        $this->assertSame('ActivityLogListenerSecret', $this->recorded[0]->getEntityType());
        $this->assertSame('42', $this->recorded[0]->getEntityId());
    }

    // A join table is no column of the entity: no change set tells a product's categories were rewritten
    public function testAManyToManyAloneRecordsAnUpdate(): void
    {
        $owner = new ActivityLogListenerPage();

        $this->flush($this->listener(user: $this->user()), $this->manager(collections: [$this->collection($owner)]));

        $this->assertCount(1, $this->recorded);
        $this->assertSame(ActivityLog::ACTION_UPDATE, $this->recorded[0]->getAction());
        $this->assertSame(['categories' => null], $this->recorded[0]->getChanges());
        $this->assertSame('42', $this->recorded[0]->getEntityId());
    }

    public function testAManyToManyJoinsTheFieldsOfTheSameRow(): void
    {
        $owner = new ActivityLogListenerPage();

        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [$owner], changeSet: ['title' => ['a', 'b']], collections: [$this->collection($owner)]));

        $this->assertCount(1, $this->recorded);
        $this->assertSame(['title' => null, 'categories' => null], $this->recorded[0]->getChanges());
    }

    // Moving to the recycle bin stays told as such, whatever else was written with it
    public function testAManyToManyDoesNotHideTheRecycleBin(): void
    {
        $owner = new ActivityLogListenerPage();

        $this->flush($this->listener(user: $this->user()), $this->manager(updates: [$owner], changeSet: ['isDeleted' => [false, true]], collections: [$this->collection($owner)]));

        $this->assertSame(ActivityLog::ACTION_TRASH, $this->recorded[0]->getAction());
        $this->assertSame(['categories' => null], $this->recorded[0]->getChanges());
    }

    // The inverse side writes nothing, an owner being created or deleted has its own row, and a bookkeeping field decides nothing
    public function testACollectionWithNothingToTellRecordsNothingOfItsOwn(): void
    {
        $owner = new ActivityLogListenerPage();

        $this->flush($this->listener(user: $this->user()), $this->manager(collections: [$this->collection($owner, owningSide: false), $this->collection($owner, 'position')]));
        $this->assertSame([], $this->recorded);

        $this->flush($this->listener(user: $this->user()), $this->manager(insertions: [$owner], collections: [$this->collection($owner)]));
        $this->assertSame([ActivityLog::ACTION_CREATE], $this->recorded('getAction'));

        $this->flush($this->listener(user: $this->user()), $this->manager(deletions: [$owner], collections: [$this->collection($owner)]));
        $this->assertSame([ActivityLog::ACTION_DELETE], $this->recorded('getAction'));
    }

    public function testAFailingToStringDoesNotFailTheSave(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(insertions: [new ActivityLogListenerBrokenLabel()]));

        $this->assertNull($this->recorded[0]->getLabel());
    }

    public function testAnIgnoredEntityIsSkipped(): void
    {
        $this->flush($this->listener(user: $this->user()), $this->manager(insertions: [new ActivityLogListenerIgnored()]));

        $this->assertSame([], $this->recorded);
    }

    // The front's own writes, the console's and a path merely starting like the back office's are no part of the log
    public function testOnlyASignedInAccountInTheBackOfficeIsLogged(): void
    {
        foreach ([['/favorite/book/1', $this->user()], ['/management-de-projet', $this->user()], [null, $this->user()], ['/management/page', null]] as [$path, $user]) {
            $this->flush($this->listener($path, $user), $this->manager(insertions: [new ActivityLogListenerPage()]));

            $this->assertSame([], $this->recorded);
        }
    }

    // TranslationWriteListener flushes again from its own postFlush, which must not write the same rows twice
    public function testASecondFlushDoesNotWriteTheRowsAgain(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->once())->method('record');

        $listener = $this->listener(user: $this->user(), repository: $repository);
        $manager = $this->manager(insertions: [new ActivityLogListenerPage()]);

        $listener->onFlush(new OnFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));
    }

    // A failed flush never reaches postFlush and closes its manager: the next manager's flush must not write a row for what was cancelled
    public function testAFailedFlushLeavesNoRowForTheNextManager(): void
    {
        $listener = $this->listener(user: $this->user());

        $listener->onFlush(new OnFlushEventArgs($this->manager(updates: [new ActivityLogListenerPage()], changeSet: ['title' => ['a', 'b']])));
        $this->flush($listener, $this->manager());

        $this->assertSame([], $this->recorded);
    }

    public function testASecondFlushOfTheSameManagerKeepsWhatTheFirstNoted(): void
    {
        $listener = $this->listener(user: $this->user());
        $manager = $this->manager(updates: [new ActivityLogListenerPage()], changeSet: ['title' => ['a', 'b']]);

        $listener->onFlush(new OnFlushEventArgs($manager));
        $listener->onFlush(new OnFlushEventArgs($manager));
        $listener->postFlush(new PostFlushEventArgs($manager));

        $this->assertCount(2, $this->recorded);
    }

    public function testAnIdThatCannotBeReadOnlyLeavesAWarning(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityIdentifier')->willThrowException(new EntityNotFoundException());
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([new ActivityLogListenerPage()]);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->method('getClassMetadata')->willReturnCallback(static fn (string $class): ClassMetadata => new ClassMetadata($class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->flush($this->listener(user: $this->user(), logger: $logger), $manager);

        $this->assertSame([], $this->recorded);
    }

    // A site whose migration has not run yet: the save went through and stays a success
    public function testAMissingTableOnlyLeavesAWarning(): void
    {
        $repository = $this->createStub(ActivityLogRepository::class);
        $repository->method('record')->willThrowException($this->createStub(TableNotFoundException::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->flush($this->listener(user: $this->user(), repository: $repository, logger: $logger), $this->manager(insertions: [new ActivityLogListenerPage()]));
    }
}

class ActivityLogListenerPage implements \Stringable
{
    public function __toString(): string
    {
        return 'Home';
    }
}

class ActivityLogListenerBrokenLabel implements \Stringable
{
    public function __toString(): string
    {
        throw new \RuntimeException('Gone.');
    }
}

#[ActivityLogIgnored]
class ActivityLogListenerIgnored
{
}

#[ActivityLogUnnamed]
class ActivityLogListenerSecret implements \Stringable
{
    public function __toString(): string
    {
        return 'GIFT-CODE';
    }
}
