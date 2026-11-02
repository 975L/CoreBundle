<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Repository\NotFoundRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;

// Deletes the recorded 404s nothing has hit for the retention period set by site-not-found-retention-days: a link nobody follows any more is a link nobody publishes, and its row would only feed an alert with nothing left to do. Usage: php bin/console c975l:config:not-found-cleanup
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:not-found-cleanup',
    description: 'Deletes the recorded 404s past their retention period'
)]
class NotFoundCleanupCommand extends AbstractRetentionCleanupCommand
{
    public function __construct(
        private readonly NotFoundRepository $notFoundRepository,
        ConfigServiceInterface $configService,
    ) {
        parent::__construct($configService);
    }

    // The setting of this list's retention
    #[\Override]
    protected function retentionSlug(): string
    {
        return 'site-not-found-retention-days';
    }

    // What configs.json declares
    #[\Override]
    protected function defaultRetentionDays(): int
    {
        return 90;
    }

    // Deletes the 404s nothing has hit for the retention
    #[\Override]
    protected function purge(int $retentionDays): string
    {
        return sprintf('Deleted %d recorded 404(s) not seen for %d days.', $this->notFoundRepository->purgeOlderThan($retentionDays), $retentionDays);
    }
}
