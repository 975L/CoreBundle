<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

// Keeps site_health_check_result down to the last site-health-check-retention-days days. The table was written as pure history on the assumption that weekly and monthly runs stay a modest row count for years - which held until BackupResultRecorder started appending a row carrying a full details payload every six hours, ie. some 1500 rows a year that every SQL dump then carries too. Deliberately not a plain delete-by-age: the dashboard shows the latest row of each (url, kind), so dropping one just because it fell out of the window would blank the very line saying how that check went
class HealthCheckRetentionPurger
{
    public const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Deletes the history older than the configured window, each check's latest row surviving whatever its age - returns how many rows went
    public function purge(): int
    {
        // Only a missing row - a site whose configs were never loaded - falls back to the default. The entry is declared "int", so the field emptied at the back-office comes back as a 0 like a typed one, and both mean "keep everything": `?: DEFAULT` would instead turn the one value asking for that into a 90-day purge
        $configured = $this->configService->get('site-health-check-retention-days');
        $days = null === $configured ? self::DEFAULT_RETENTION_DAYS : (int) $configured;

        // A zero or a negative in an entry anyone can mistype means "keep everything", never "delete everything"
        if ($days <= 0) {
            return 0;
        }

        return $this->healthCheckResultRepository->deleteOlderThan(
            new \DateTimeImmutable(sprintf('-%d days', $days)),
            $this->healthCheckResultRepository->findLatestIdPerUrlAndKind(),
        );
    }
}
