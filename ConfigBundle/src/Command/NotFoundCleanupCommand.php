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
use c975L\ConfigBundle\Service\NotFoundFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Deletes the recorded 404s nothing has hit for the retention period set by site-not-found-retention-days: a link nobody follows any more is a link nobody publishes, and its row would only feed an alert with nothing left to do. The rows NotFoundFilter now turns down go first, whatever the retention: recorded before a rule caught them, they are scanner noise rather than links to keep. Usage: php bin/console c975l:config:not-found-cleanup
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
        private readonly NotFoundFilter $notFoundFilter,
        ConfigServiceInterface $configService,
    ) {
        parent::__construct($configService);
    }

    // Deletes the noise the filter now turns down, then purges by retention - a "keep everything" retention keeps the broken links, never the probes
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $noise = array_keys(array_filter(
            $this->notFoundRepository->findReferersIndexedByPath(),
            fn (string $referer, int | string $path): bool => !$this->notFoundFilter->isWorthRecording((string) $path, $referer),
            \ARRAY_FILTER_USE_BOTH,
        ));
        $deleted = $this->notFoundRepository->deleteByPaths(array_map(strval(...), $noise));
        new SymfonyStyle($input, $output)->text(sprintf('Deleted %d recorded 404(s) the filter now turns down.', $deleted));

        return parent::execute($input, $output);
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
