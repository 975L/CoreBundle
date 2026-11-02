<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Attribute\ActivityLogIgnored;
use c975L\ConfigBundle\Attribute\ActivityLogUnnamed;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Controller\Management\DashboardController;
use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\ConfigLabelResolver;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

// Writes the activity log: whatever a signed-in account saves under the back office's path, whichever bundle the entity comes from. The front's own writes (a play, a favorite, a form sent) and the console's are no part of it, which is what keeps the log readable with no list of entities to maintain
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class ActivityLogListener
{
    // Written on every save whatever was edited: a row carrying nothing else is no decision of anyone's. "position" goes with them, as moving one block renumbers all its siblings
    private const array IGNORED_FIELDS = ['modification', 'updatedAt', 'user', 'position'];

    private const string TRASH_FIELD = 'isDeleted';

    // onFlush knows what is written and an inserted row's id only exists at postFlush
    /** @var list<array{entity: object, user: UserInterface, action: string, entityType: string, entityId: ?string, label: ?string, changes: array<string, bool|null>, path: string}> */
    private array $pending = [];

    private ?EntityManagerInterface $pendingManager = null;

    /** @var array<class-string, array<class-string, bool>> */
    private array $carriedAttributes = [];

    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly ConfigLabelResolver $configLabelResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Notes what this flush is about to write, while the deletions still have their id
    public function onFlush(OnFlushEventArgs $args): void
    {
        $manager = $args->getObjectManager();

        // A failed flush closes its manager and never reaches postFlush: what it left here was cancelled, and must not be told by the next manager's flush
        if ($manager !== $this->pendingManager) {
            $this->pending = [];
            $this->pendingManager = $manager;
        }

        $path = $this->requestStack->getMainRequest()?->getPathInfo();
        if (null === $path || !DashboardController::isManagementPath($path)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $unitOfWork = $manager->getUnitOfWork();
        $relations = $this->relationChanges($unitOfWork);

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->collect($manager, $entity, ActivityLog::ACTION_CREATE, [], $user, $path, false);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $objectId = spl_object_id($entity);
            $changes = $this->changes($entity, $unitOfWork->getEntityChangeSet($entity)) + ($relations[$objectId]['changes'] ?? []);
            unset($relations[$objectId]);

            // Moving to the recycle bin and back is told by its own action rather than by a field name
            $trashed = $changes[self::TRASH_FIELD] ?? null;
            unset($changes[self::TRASH_FIELD]);

            if (null !== $trashed) {
                $this->collect($manager, $entity, $trashed ? ActivityLog::ACTION_TRASH : ActivityLog::ACTION_RESTORE, $changes, $user, $path, true);
            } elseif ([] !== $changes) {
                $this->collect($manager, $entity, ActivityLog::ACTION_UPDATE, $changes, $user, $path, true);
            }
        }

        // An entity whose relations alone changed is not among the scheduled updates
        foreach ($relations as ['owner' => $owner, 'changes' => $changes]) {
            $this->collect($manager, $owner, ActivityLog::ACTION_UPDATE, $changes, $user, $path, true);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $this->collect($manager, $entity, ActivityLog::ACTION_DELETE, [], $user, $path, true);
        }
    }

    // Writes the rows noted by onFlush, now that an insertion has its id
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }

        // Emptied first, as another listener's postFlush may flush again and bring us straight back here
        $pending = $this->pending;
        $this->pending = [];

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $occurredAt = new \DateTimeImmutable();

        // A site whose migration has not run yet takes no note, and a log never turns a save that went through into a 500
        try {
            $logs = [];
            foreach ($pending as $entry) {
                $logs[] = new ActivityLog(
                    $occurredAt,
                    $entry['user'],
                    $entry['action'],
                    $entry['entityType'],
                    $entry['entityId'] ?? implode('-', $unitOfWork->getEntityIdentifier($entry['entity'])),
                    $entry['label'],
                    $entry['changes'],
                    $entry['path'],
                );
            }

            $this->activityLogRepository->record($logs);
        } catch (DbalException | EntityNotFoundException $exception) {
            $this->logger->warning('Activity log not written: ' . $exception->getMessage());
        }
    }

    // The many-to-many fields rewritten, by owner: a join table is no column of the entity, so no change set names them. The owning side only, the inverse one writing nothing, and an owner being created or deleted already has its own row
    /** @return array<int, array{owner: object, changes: array<string, bool|null>}> */
    private function relationChanges(UnitOfWork $unitOfWork): array
    {
        $relations = [];
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $owner = $collection->getOwner();
            if (null === $owner || !$collection->getMapping()->isManyToManyOwningSide() || $unitOfWork->isScheduledForInsert($owner) || $unitOfWork->isScheduledForDelete($owner)) {
                continue;
            }

            $changes = $this->changes($owner, [$collection->getMapping()->fieldName => [null, null]]);
            if ([] !== $changes) {
                $objectId = spl_object_id($owner);
                $relations[$objectId]['owner'] = $owner;
                $relations[$objectId]['changes'] = ($relations[$objectId]['changes'] ?? []) + $changes;
            }
        }

        return $relations;
    }

    // A deletion's id is gone once flushed, so what exists at this point is read now and an insertion's left for postFlush
    /** @param array<string, bool|null> $changes */
    private function collect(EntityManagerInterface $manager, object $entity, string $action, array $changes, UserInterface $user, string $path, bool $identified): void
    {
        $class = $manager->getClassMetadata($entity::class)->getName();
        if ($this->carries($class, ActivityLogIgnored::class)) {
            return;
        }

        $this->pending[] = [
            'entity' => $entity,
            'user' => $user,
            'action' => $action,
            'entityType' => new \ReflectionClass($class)->getShortName(),
            'entityId' => $identified ? implode('-', $manager->getUnitOfWork()->getEntityIdentifier($entity)) : null,
            'label' => $this->carries($class, ActivityLogUnnamed::class) ? null : $this->label($entity),
            'changes' => $changes,
            'path' => mb_substr($path, 0, 255),
        ];
    }

    // Keeps the names of the fields written, and the new value of the boolean ones
    /**
     * @param array<string, mixed> $changeSet
     *
     * @return array<string, bool|null>
     */
    private function changes(object $entity, array $changeSet): array
    {
        $changes = [];
        foreach ($changeSet as $field => $values) {
            if (\in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }

            $new = $values[1] ?? null;

            // A Config stores all its values as text, and turning the maintenance mode on or off is precisely what this log has to tell apart
            if ($entity instanceof Config && 'value' === $field && Config::TYPE_BOOL === $entity->getKind()) {
                $new = filter_var($new, \FILTER_VALIDATE_BOOL);
            }

            $changes[$field] = \is_bool($new) ? $new : null;
        }

        return $changes;
    }

    // What the entity is called at this moment, if it can be said in the log
    private function label(object $entity): ?string
    {
        if ($entity instanceof UserInterface) {
            return null;
        }

        if ($entity instanceof Config) {
            return mb_substr($this->configLabelResolver->resolve($entity), 0, 255);
        }

        if (!$entity instanceof \Stringable) {
            return null;
        }

        // A __toString() reading a relation whose row is gone would otherwise fail the very save being logged
        try {
            return mb_substr((string) $entity, 0, 255);
        } catch (\Throwable) {
            return null;
        }
    }

    // Whether an entity class carries one of the log's attributes, read once per class
    private function carries(string $class, string $attribute): bool
    {
        return $this->carriedAttributes[$attribute][$class] ??= [] !== new \ReflectionClass($class)->getAttributes($attribute);
    }
}
