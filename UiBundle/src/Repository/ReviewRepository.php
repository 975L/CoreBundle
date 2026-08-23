<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    // The reviews a page displays, newest first and nothing else: the ordering criterion never looks at the rating, showing a subset being defensible only when what picks it is blind to how good it is
    // Published only, here and in every other method serving a visitor - a review this site has not let through must not be reachable by asking for it another way
    /**
     * @return Review[]
     */
    public function findForDisplay(?string $source = null, ?int $limit = null): array
    {
        $queryBuilder = $this->publishedQueryBuilder()
            ->orderBy('r.publishedAt', 'DESC')
        ;

        if (null !== $source) {
            $queryBuilder
                ->andWhere('r.source = :source')
                ->setParameter('source', $source)
            ;
        }

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    // The reviews written about one listed thing, whatever their source: a book gathers what visitors wrote here and what a platform was asked for under the same heading
    /**
     * @return Review[]
     */
    public function findForOwner(string $ownerType, int $ownerId, ?int $limit = null): array
    {
        $queryBuilder = $this->publishedQueryBuilder()
            ->andWhere('r.ownerType = :ownerType')
            ->andWhere('r.ownerId = :ownerId')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerId', $ownerId)
            ->orderBy('r.publishedAt', 'DESC')
        ;

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    // Total and average over every review held, never over the subset displayed - the figure a visitor reads has to cover the whole, or the subset above turns into a selection
    // Reviews carrying no rating are counted in neither: they said something without scoring anything, and averaging them in as a zero would punish the site for having been talked to
    /**
     * @return array{count: int, average: float|null}
     */
    public function getAggregate(?string $source = null): array
    {
        $queryBuilder = $this->publishedQueryBuilder()
            ->select('COUNT(r.id) AS total, AVG(r.rating) AS average')
            ->andWhere('r.rating IS NOT NULL')
        ;

        if (null !== $source) {
            $queryBuilder
                ->andWhere('r.source = :source')
                ->setParameter('source', $source)
            ;
        }

        $result = $queryBuilder->getQuery()->getSingleResult();
        $count = (int) $result['total'];

        return [
            'count' => $count,
            'average' => 0 === $count ? null : round((float) $result['average'], 1),
        ];
    }

    // How many reviews are waiting for a decision, for the badge the management menu shows: a review nobody looks at is a review nobody publishes
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', ReviewStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // The row a sync updates rather than duplicates, matched on what the source itself calls this review
    public function findOneFromSource(string $source, string $externalId): ?Review
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    // The rows a run no longer got from its source, so the synchronizer can remove what the platform dropped - entities rather than a bulk delete, the cache tag being emptied by a Doctrine listener that only sees managed removals
    /**
     * @param string[] $externalIds
     *
     * @return Review[]
     */
    public function findMissing(string $source, array $externalIds): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.source = :source')
            ->andWhere('r.externalId NOT IN (:externalIds)')
            ->setParameter('source', $source)
            ->setParameter('externalIds', $externalIds)
            ->getQuery()
            ->getResult()
        ;
    }

    // Drops what was written about a thing that no longer exists. Called by whoever owns that thing, this bundle hearing about neither (same reasoning as deleteForOwner() on ratings)
    public function deleteForOwner(string $ownerType, int $ownerId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.ownerType = :ownerType')
            ->andWhere('r.ownerId = :ownerId')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->execute()
        ;
    }

    // Said once here rather than at each of the methods above, so that adding one never adds a way to serve a review that was not let through
    private function publishedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :published')
            ->setParameter('published', ReviewStatus::Published)
        ;
    }
}
