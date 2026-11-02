<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Repository\ActivityLogRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;

// Deletes the activity log rows past their retention period, set by site-activity-log-retention-days. Usage: php bin/console c975l:config:activity-log-cleanup
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:activity-log-cleanup',
    description: 'Deletes the activity log rows past their retention period'
)]
class ActivityLogCleanupCommand extends AbstractRetentionCleanupCommand
{
    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository,
        ConfigServiceInterface $configService,
    ) {
        parent::__construct($configService);
    }

    // The setting of this log's retention
    #[\Override]
    protected function retentionSlug(): string
    {
        return 'site-activity-log-retention-days';
    }

    // What configs.json declares
    #[\Override]
    protected function defaultRetentionDays(): int
    {
        return 365;
    }

    // Deletes the rows past the retention
    #[\Override]
    protected function purge(int $retentionDays): string
    {
        return sprintf('Deleted %d activity log row(s) older than %d days.', $this->activityLogRepository->purgeOlderThan($retentionDays), $retentionDays);
    }
}
