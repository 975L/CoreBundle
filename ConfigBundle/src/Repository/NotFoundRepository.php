<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\NotFound;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\NotFound>
 */
class NotFoundRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        // The rows are written from the exception listener, where a flush that fails would close the entity manager - and the error page rendering right after needs it for its own menus and blocks. A statement on the connection cannot do that, so the recording of a 404 can never turn a 404 page into a 500
        private readonly Connection $connection,
    ) {
        parent::__construct($registry, NotFound::class);
    }

    // Counts one more hit on a path already known, or opens its row. Two statements at worst, on a request that is already an error - and none at all on the vast majority of 404s, which carry no referer and never reach here
    public function record(string $path, string $referer, bool $internal, \DateTimeImmutable $seenAt): void
    {
        // Bound as immutable, which is what a clock hands over - the two types write the very same DATETIME, and only the immutable one accepts the value without converting it first
        $updated = $this->connection->executeStatement(
            sprintf('UPDATE %s SET hits = hits + 1, last_seen = :seenAt, referer = :referer, internal = :internal WHERE path = :path', NotFound::TABLE),
            ['seenAt' => $seenAt, 'referer' => $referer, 'internal' => $internal, 'path' => $path],
            ['seenAt' => Types::DATETIME_IMMUTABLE, 'internal' => ParameterType::BOOLEAN],
        );

        if ($updated > 0) {
            return;
        }

        try {
            $this->connection->insert(
                NotFound::TABLE,
                ['path' => $path, 'referer' => $referer, 'internal' => $internal, 'hits' => 1, 'first_seen' => $seenAt, 'last_seen' => $seenAt],
                ['internal' => ParameterType::BOOLEAN, 'first_seen' => Types::DATETIME_IMMUTABLE, 'last_seen' => Types::DATETIME_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            // Two requests hitting the same brand-new dead url at once: the other one opened the row, and losing a single hit on it is not worth a retry
        }
    }

    // Only the internal ones are alerted on (see NotFoundAlertProvider): a stale link on someone else's site is worth a redirect when convenient, a broken link on our own pages is worth knowing about today
    public function countInternal(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.internal = true')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function purgeOlderThan(int $days): int
    {
        $limit = new \DateTime(sprintf('-%d days', $days));

        return (int) $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.lastSeen < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->execute()
        ;
    }
}
