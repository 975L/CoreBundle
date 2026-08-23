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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the recorded 404s nothing has hit for the retention period.
 *
 * A broken link that stopped being followed is a link nobody publishes any more - the page
 * carrying it was fixed, or the site that had it moved on. Keeping the row would only leave
 * a listing of things there is nothing left to do about, and an alert with it.
 *
 * Usage:
 *   php bin/console c975l:config:not-found-cleanup
 *
 * Settings managed via ConfigBundle (site-not-found-retention-days).
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:not-found-cleanup',
    description: 'Deletes the recorded 404s past their retention period'
)]
class NotFoundCleanupCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly NotFoundRepository $notFoundRepository,
        private readonly ConfigServiceInterface $configService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Read as the backup, health check and messenger retentions are: only a missing row falls back to the default, a typed "0" meaning "keep everything" rather than "delete everything"
        $configured = $this->configService->get('site-not-found-retention-days');
        $retentionDays = null === $configured ? self::DEFAULT_RETENTION_DAYS : (int) $configured;

        if ($retentionDays <= 0) {
            $io->note('Retention set to keep everything, nothing purged.');

            return Command::SUCCESS;
        }

        $purged = $this->notFoundRepository->purgeOlderThan($retentionDays);
        $io->success(sprintf('Deleted %d recorded 404(s) not seen for %d days.', $purged, $retentionDays));

        return Command::SUCCESS;
    }
}
