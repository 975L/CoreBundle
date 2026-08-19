<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\HealthCheckResult>
 */
class HealthCheckResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthCheckResult::class);
    }

    // One row per (url, kind), keeping only the most recent checkedAt - backs the "Health check" dashboard table, which shows the current state, not the full history. Deduped in PHP (dataset stays small, see HealthCheckResult's class comment) then re-sorted by url/kind for a stable display order
    public function findLatestPerUrlAndKind(): array
    {
        $rows = $this->createQueryBuilder('h')
            ->orderBy('h.checkedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $row) {
            $key = $row->getUrl() . '|' . $row->getKind();
            $latest[$key] ??= $row;
        }
        $latest = array_values($latest);

        usort($latest, static fn (HealthCheckResult $a, HealthCheckResult $b) => [$a->getUrl(), $a->getKind()] <=> [$b->getUrl(), $b->getKind()]);

        return $latest;
    }

    // One row per kind for a single url, most recent checkedAt only - backs a per-page health check panel (e.g. c975l/site-bundle's Page edit screen), the same dedup rule as findLatestPerUrlAndKind() but scoped to one page instead of every page
    public function findLatestByUrl(string $url): array
    {
        $rows = $this->createQueryBuilder('h')
            ->andWhere('h.url = :url')
            ->setParameter('url', $url)
            ->orderBy('h.checkedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->getKind()] ??= $row;
        }
        $latest = array_values($latest);

        usort($latest, static fn (HealthCheckResult $a, HealthCheckResult $b) => $a->getKind() <=> $b->getKind());

        return $latest;
    }

    // The single most recent row of one (url, kind), or null if that pair was never checked - what a provider comparing a run against the one before it needs (see IntrusionHealthCheckProvider, which reads one integer out of it). Bounded in SQL rather than deduped in PHP like findLatestPerUrlAndKind(): reading one row must not hydrate a history that grows with every run
    public function findLatestByUrlAndKind(string $url, string $kind): ?HealthCheckResult
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.url = :url')
            ->andWhere('h.kind = :kind')
            ->setParameter('url', $url)
            ->setParameter('kind', $kind)
            ->orderBy('h.checkedAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // The most recent rows of a single kind, newest first - unlike findLatestPerUrlAndKind() this keeps the history rather than deduping it, so a caller can compare a run against the one before it (see BackupResultRecorder, which flags an archive that suddenly shrank, and BackupAlertProvider, which only needs the first row)
    // @return HealthCheckResult[]
    public function findLatestByKind(string $kind, int $limit = 2): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.kind = :kind')
            ->setParameter('kind', $kind)
            ->orderBy('h.checkedAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Every row of a single kind recorded since a given moment, newest first - what the weekly digest reads (see BackupDigestBuilder), findLatestByKind()'s limit being a number of rows and not a period: how many runs a week holds depends on the schedule, and a window asked for in days must not be cut short by a count guessed here
    // @return HealthCheckResult[]
    public function findByKindSince(string $kind, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.kind = :kind')
            ->andWhere('h.checkedAt >= :since')
            ->setParameter('kind', $kind)
            ->setParameter('since', $since)
            ->orderBy('h.checkedAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // The id of the most recent row of each (url, kind) - what the retention purge has to keep whatever its age, those being the very rows the dashboard reads (see findLatestPerUrlAndKind()). MAX(id) rather than the row carrying MAX(checkedAt): rows are only ever appended, so a group's highest id is its latest run, and this stays one grouped query on the table the purge exists to keep small
    // @return int[]
    public function findLatestIdPerUrlAndKind(): array
    {
        return array_map(intval(...), $this->createQueryBuilder('h')
            ->select('MAX(h.id)')
            ->groupBy('h.url')
            ->addGroupBy('h.kind')
            ->getQuery()
            ->getSingleColumnResult());
    }

    // Deletes the rows checked before $limit, except those whose id is listed in $keepIds - a bulk DQL delete rather than a hydrate-then-remove loop, the rows to delete being counted in thousands and none of them of any use to the identity map afterwards
    public function deleteOlderThan(\DateTimeInterface $limit, array $keepIds = []): int
    {
        $qb = $this->createQueryBuilder('h')
            ->delete()
            ->andWhere('h.checkedAt < :limit')
            ->setParameter('limit', $limit);

        if ([] !== $keepIds) {
            $qb
                ->andWhere('h.id NOT IN (:keep)')
                ->setParameter('keep', $keepIds);
        }

        return (int) $qb->getQuery()->execute();
    }

    // Deletes every row of one kind whose url is not in $urls - what an exhaustive provider's run leaves behind (see HealthCheckExhaustiveInterface): a url it no longer returns has nothing left to check, and its last row would otherwise stand as the dashboard's current state for good, the retention purge preserving the latest row of each (url, kind) whatever its age. An empty $urls clears the kind, that being what an exhaustive run returning nothing means. A bulk DQL delete for the same reason as deleteOlderThan(): none of these rows is of any use to the identity map afterwards
    public function deleteByKindNotInUrls(string $kind, array $urls): int
    {
        $qb = $this->createQueryBuilder('h')
            ->delete()
            ->andWhere('h.kind = :kind')
            ->setParameter('kind', $kind);

        if ([] !== $urls) {
            $qb
                ->andWhere('h.url NOT IN (:urls)')
                ->setParameter('urls', array_values(array_unique($urls)));
        }

        return (int) $qb->getQuery()->execute();
    }

    // The distinct kinds having recorded at least one row since a given moment - how the Health check page tells how many of a queued run's jobs have landed (see HealthCheckRunProgress), the jobs themselves running in a Messenger worker the web request knows nothing about. A DISTINCT aggregate rather than a hydration: the answer is a handful of strings whatever the number of rows behind them, and this is polled every few seconds
    // @return string[]
    public function findKindsCheckedSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('h')
            ->select('DISTINCT h.kind')
            ->andWhere('h.checkedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleColumnResult();
    }

    // Status counts (ok/warning/error) grouped by calendar day, across every kind/url - the "is our site's health improving or degrading" trend chart on the Health check page (see HealthCheckController), not a per-page breakdown. Capped to the last $maxDates distinct days so the chart stays readable as history accumulates (see HealthCheckResult's own class comment on why history isn't pruned)
    public function findStatusCountsByDate(int $maxDates = 12): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.url', 'h.kind', 'h.status', 'h.checkedAt')
            ->orderBy('h.checkedAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Dedupe to the latest run per (day, url, kind) before counting - a check re-run several times the same day (manual click + cron, or repeated testing) must not inflate that day's counts, same rule as findLatestPerUrlAndKind() applied per day instead of overall
        $latestPerDayAndCheck = [];
        foreach ($rows as $row) {
            $key = $row['checkedAt']->format('Y-m-d') . '|' . $row['url'] . '|' . $row['kind'];
            $latestPerDayAndCheck[$key] = $row;
        }

        $byDate = [];
        foreach ($latestPerDayAndCheck as $row) {
            $day = $row['checkedAt']->format('Y-m-d');
            $byDate[$day][$row['status']] = ($byDate[$day][$row['status']] ?? 0) + 1;
        }

        $dates = \array_slice(array_keys($byDate), -$maxDates);

        $series = [HealthCheckResult::STATUS_OK => [], HealthCheckResult::STATUS_WARNING => [], HealthCheckResult::STATUS_ERROR => []];
        foreach ($dates as $date) {
            foreach ($series as $status => &$values) {
                $values[] = $byDate[$date][$status] ?? 0;
            }
        }

        return ['dates' => $dates, 'series' => $series];
    }
}
