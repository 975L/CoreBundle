<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\StatusReportBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: StatusDumpCommand::NAME,
    description: 'Prints this site\'s status report (versions, packages, health check summary) - the same JSON the /status/report route serves, without needing a key or a network'
)]
class StatusDumpCommand extends Command
{
    public const NAME = 'c975l:status:dump';

    public function __construct(
        private readonly StatusReportBuilder $statusReportBuilder,
    ) {
        parent::__construct();
    }

    // Written straight to the output rather than through SymfonyStyle, which would wrap and decorate it - this is meant to be readable, but also pipeable into a file or jq
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->statusReportBuilder->build();

        $output->writeln(json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
