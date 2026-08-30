<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;

// Runs every registered HealthCheckProvider and persists their rows - called from c975l:health-check:run only (see HealthCheckProviderInterface), never from a controller, so a slow third-party API call never blocks a dashboard request
class HealthCheckRunner
{
    public function __construct(
        private readonly iterable $healthCheckProviders,
        private readonly EntityManagerInterface $entityManager,
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
    ) {
    }

    // $onlyKinds restricts the run to the given provider kinds (see HealthCheckProviderInterface::getKind()), $frequency to the providers declaring that cadence (see AsHealthCheck) - the latter is what the scheduler asks for, so a cron entry never names kinds and a newly installed bundle is picked up without editing it. Both empty (the default, and what the dashboard's "Run health check now" button always uses) runs every registered provider. Returns the number of rows persisted, per provider kind
    public function run(array $onlyKinds = [], ?string $frequency = null): array
    {
        $counts = [];
        $exhaustiveUrls = [];

        foreach ($this->healthCheckProviders as $provider) {
            if (!$this->answersThisRun($provider, $onlyKinds, $frequency)) {
                continue;
            }

            $rows = $this->runProvider($provider);
            if (null === $rows) {
                continue;
            }

            $kind = $provider->getKind();

            // Collected per kind rather than purged here, because one class can be registered several times over under the same kind, one instance per source (see DeclaredUrlsHealthCheckProvider) - purging inside the loop would have each instance delete what the ones before it just produced
            if ($provider instanceof HealthCheckExhaustiveInterface) {
                $exhaustiveUrls[$kind] = array_merge($exhaustiveUrls[$kind] ?? [], array_column($rows, 'url'));
            }

            $counts[$kind] = \count($rows);
        }

        // Same again before the write: the last provider's own http requests are just as long, and everything every provider produced would be lost on the flush below ("MySQL server has gone away")
        if (array_sum($counts) > 0 || $exhaustiveUrls) {
            $this->reconnectIfLost();
        }

        // Skips flush() entirely on a true no-op (no provider matched $onlyKinds, or every matched provider returned zero rows) - flush() walks the whole UnitOfWork's changeset, not just what this method touched, so there's a real cost to paying for it when nothing was persisted
        if (array_sum($counts) > 0) {
            $this->entityManager->flush();
        }

        // Drops what an exhaustive provider no longer declares (see HealthCheckExhaustiveInterface), after the flush so the rows just persisted are on the table and survive the delete on their own urls. Unconditional: a run returning nothing is exactly the case where every row of that kind is stale
        foreach ($exhaustiveUrls as $kind => $urls) {
            $this->healthCheckResultRepository->deleteByKindNotInUrls($kind, $urls);
        }

        return $counts;
    }

    // Whether this provider is one the run was narrowed to
    private function answersThisRun(object $provider, array $onlyKinds, ?string $frequency): bool
    {
        if ($onlyKinds && !\in_array($provider->getKind(), $onlyKinds, true)) {
            return false;
        }

        return !$frequency || $this->frequencyOf($provider) === $frequency;
    }

    // What one provider has to say, or null when it threw
    // One provider throwing must not take the run down with it: rows are only flushed once every provider has run, so an exception here would discard what the providers before it already produced, and the ones after it would never run at all
    private function runProvider(object $provider): ?array
    {
        $checkedAt = new \DateTime();

        // Before the provider rather than only before the flush: the one before it may have spent minutes on http requests, and a provider reading the database as it opens (see ContentQualityAnalyzer) would throw on a dropped connection - straight into the catch below, which would skip it without a trace
        $this->reconnectIfLost();

        try {
            $rows = $provider->runChecks();
        } catch (\Throwable) {
            return null;
        }

        foreach ($rows as $row) {
            $this->entityManager->persist($this->buildResult($provider->getKind(), $row, $checkedAt));
        }

        return $rows;
    }

    // Closes a connection the server has already dropped, so the next query opens a fresh one - a health check run spends minutes on http requests without touching the database, which is long enough for the server to have dropped it meanwhile, and Messenger's own ping happens before the message is handled, so it is of no help here - DBAL reconnects lazily, and the pending persists live in the UnitOfWork, not in the connection, so nothing is lost. Same shape as Symfony's DoctrinePingConnectionMiddleware: a connection never opened is left alone, a live one is only paid a "SELECT 1" for
    private function reconnectIfLost(): void
    {
        $connection = $this->entityManager->getConnection();

        if (!$connection->isConnected()) {
            return;
        }

        try {
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (DBALException) {
            $connection->close();
        }
    }

    // The cadence a provider declares: the instance is asked first, for the rare provider whose class is registered several times over and whose instances differ (see HealthCheckFrequencyAwareInterface), then the class attribute, then weekly. Read by reflection rather than resolved at compile time on purpose - this runs once a week from a cron, and the alternative is a second compiler pass for a string
    private function frequencyOf(HealthCheckProviderInterface $provider): string
    {
        if ($provider instanceof HealthCheckFrequencyAwareInterface) {
            return $provider->getFrequency();
        }

        $attributes = new \ReflectionClass($provider)->getAttributes(AsHealthCheck::class);

        return $attributes ? $attributes[0]->newInstance()->frequency : AsHealthCheck::FREQUENCY_WEEKLY;
    }

    // One row as its provider returned it (see HealthCheckProviderInterface::runChecks()), turned into the entity persisted for it - only url/status/summary are required, the rest is what the Health check panel shows when the provider can supply it
    private function buildResult(string $kind, array $row, \DateTime $checkedAt): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind($kind)
            ->setUrl($row['url'])
            ->setLabel($row['label'] ?? null)
            ->setStatus($row['status'])
            ->setSummary($row['summary'])
            ->setDetails($row['details'] ?? null)
            ->setEditUrl($row['editUrl'] ?? null)
            ->setCheckedAt($checkedAt);
    }

    // The kinds whose provider says it checks the site once rather than page by page (see HealthCheckSiteWideInterface) - what a bundle installed beside this one has to declare its own rows site-wide, HealthCheckController's list being written here and closed to them
    public function getSiteWideKinds(): array
    {
        $kinds = [];

        foreach ($this->healthCheckProviders as $provider) {
            if ($provider instanceof HealthCheckSiteWideInterface) {
                $kinds[$provider->getKind()] = true;
            }
        }

        return array_keys($kinds);
    }

    // Every registered provider's kind, deduplicated and in registration order - lets a caller queue one job per kind (see HealthCheckController::run()) rather than one job running them all, so a bundle declaring thousands of urls doesn't drag the free, fast checks down with it, nor take them with it when it fails
    public function getKinds(): array
    {
        $kinds = [];

        foreach ($this->healthCheckProviders as $provider) {
            $kinds[$provider->getKind()] = true;
        }

        return array_keys($kinds);
    }
}
