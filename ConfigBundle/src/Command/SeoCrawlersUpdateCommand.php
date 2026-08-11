<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\AiCrawlerListUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Deliberately not scheduled, unlike every other command of this bundle: what a site blocks is its own call, and the monthly AiCrawlersHealthCheckProvider is what says when there is something to run here. Run c975l:seo:files:create afterwards for the new list to reach the deployed robots.txt
#[AsCommand(
    name: 'c975l:seo:crawlers:update',
    description: 'Adds to the "seo-robots-ai-crawlers" config the AI crawlers that appeared in the community list since it was last updated'
)]
class SeoCrawlersUpdateCommand extends Command
{
    public function __construct(
        private readonly AiCrawlerListUpdater $aiCrawlerListUpdater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be added, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $comparison = $this->aiCrawlerListUpdater->compare();

        if (null === $comparison['source']) {
            $io->note('The "seo-robots-ai-crawlers-source" config names no source, so this site keeps its list by hand - nothing to do.');

            return Command::SUCCESS;
        }

        $io->text('Source: ' . $comparison['source']);

        // Named one by one rather than counted: this is the list deciding what the site stops serving, and it is meant to be read before it is applied
        if ($comparison['answerEngines']) {
            $io->section('Left out - these answer a question by citing the page they read, blocking them only costs visibility');
            $io->listing($comparison['answerEngines']);
        }

        if ([] === $comparison['missing']) {
            $io->success('The AI crawler list is up to date.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('%d crawler(s) to add', \count($comparison['missing'])));
        $io->listing($comparison['missing']);

        if ($dryRun) {
            $io->warning('Nothing was written.');

            return Command::SUCCESS;
        }

        $updated = $this->aiCrawlerListUpdater->apply($comparison['missing']);

        $io->success(sprintf('%d crawler(s) added, the list now holds %d. Run "c975l:seo:files:create" for robots.txt to carry them.', \count($comparison['missing']), \count($updated)));

        return Command::SUCCESS;
    }
}
