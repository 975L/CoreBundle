<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        // The rows are written from postFlush (see ActivityLogListener), where a flush of our own would start the whole cycle over
        private readonly Connection $connection,
    ) {
        parent::__construct($registry, ActivityLog::class);
    }

    // Writes the rows of one save in one commit, whole or not at all
    /** @param list<ActivityLog> $logs */
    public function record(array $logs): void
    {
        $this->connection->transactional(function () use ($logs): void {
            foreach ($logs as $log) {
                $this->connection->insert(
                    ActivityLog::TABLE,
                    [
                        'occurred_at' => $log->getOccurredAt(),
                        'user_id' => $log->getUser()?->getId(),
                        'action' => $log->getAction(),
                        'entity_type' => $log->getEntityType(),
                        'entity_id' => $log->getEntityId(),
                        'label' => $log->getLabel(),
                        'changes' => $log->getChanges(),
                        'path' => $log->getPath(),
                    ],
                    ['occurred_at' => Types::DATETIME_IMMUTABLE, 'changes' => Types::JSON],
                );
            }
        });
    }

    // Deletes the rows past the retention and returns how many went
    public function purgeOlderThan(int $days): int
    {
        return (int) $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE occurred_at < :limit', ActivityLog::TABLE),
            ['limit' => new \DateTimeImmutable(sprintf('-%d days', $days))],
            ['limit' => Types::DATETIME_IMMUTABLE],
        );
    }
}
