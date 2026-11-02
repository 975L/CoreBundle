<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// A purge with nothing around it but a retention setting: the backup and messenger ones do more than that and stay on their own
abstract class AbstractRetentionCleanupCommand extends Command
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
        parent::__construct();
    }

    // The setting holding the retention, in days
    abstract protected function retentionSlug(): string;

    // The retention of a site whose setting was never loaded
    abstract protected function defaultRetentionDays(): int;

    // Deletes what is past the retention and returns the sentence reporting it
    abstract protected function purge(int $retentionDays): string;

    // Reads the retention and purges, unless it says to keep everything
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Read as the backup, health check and messenger retentions are: only a missing row falls back to the default, a typed "0" meaning "keep everything" rather than "delete everything"
        $configured = $this->configService->get($this->retentionSlug());
        $retentionDays = null === $configured ? $this->defaultRetentionDays() : (int) $configured;

        if ($retentionDays <= 0) {
            $io->note('Retention set to keep everything, nothing purged.');

            return Command::SUCCESS;
        }

        $io->success($this->purge($retentionDays));

        return Command::SUCCESS;
    }
}
