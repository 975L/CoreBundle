<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\IndexNowNotifier;
use c975L\ConfigBundle\Management\SitemapWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'c975l:sitemaps:create',
    description: 'Creates a sitemap for every registered SitemapProvider (site pages, books, products...), plus the sitemap index, and submits the urls that changed to IndexNow'
)]
class SitemapCreateCommand extends Command
{
    public function __construct(
        private readonly SitemapWriter $sitemapWriter,
        private readonly IndexNowNotifier $indexNowNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $names = $this->sitemapWriter->write();

        // Before the early return below: a site whose last url just went still has removals to announce
        $this->notifyIndexNow($io);

        if ([] === $names) {
            $io->warning('No SitemapProvider has any url to declare - nothing to write.');

            return Command::SUCCESS;
        }

        $io->success('Sitemaps created: ' . implode(', ', $names) . ', plus the index.');

        return Command::SUCCESS;
    }

    // A warning rather than a failure: the sitemaps are written either way, the urls go again on the next run, and a console error in production raises an alert for a third party being down
    private function notifyIndexNow(SymfonyStyle $io): void
    {
        try {
            $submitted = $this->indexNowNotifier->notify($this->sitemapWriter->getDeclaredUrls());
        } catch (\RuntimeException | \JsonException $e) {
            $io->warning('IndexNow: ' . $e->getMessage() . ' The urls will be submitted again on the next run.');

            return;
        }

        if (null !== $submitted && $submitted > 0) {
            $io->note(sprintf('IndexNow: %d url(s) submitted.', $submitted));
        }
    }
}
