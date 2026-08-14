<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\UrlMetadataSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'c975l:url-metadata:sync',
    description: 'Lists in "Descriptions d\'urls" every url a bundle declares and that has no row yet, ready to be described',
    help: 'Run it at deployment, beside c975l:sitemaps:create: a listing added by a release is then waiting in the screen instead of having to be typed in by hand. It only ever creates empty rows - anything already written is left untouched, and a row whose url is gone is reported rather than deleted.',
)]
class UrlMetadataSyncCommand extends Command
{
    public function __construct(
        private readonly UrlMetadataSynchronizer $urlMetadataSynchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->urlMetadataSynchronizer->synchronize();

        if ([] === $result['created']) {
            $io->success(sprintf('%d urls declared, all of them listed already.', $result['declared']));
        } else {
            $io->success(sprintf('%d urls declared, %d added, waiting to be described.', $result['declared'], count($result['created'])));
            $io->listing($result['created']);
        }

        // Never deleted here: the sentence written for an url is work, and an url can leave a listing for one release and come back. Named so it can be removed from the screen, by whoever knows whether it is gone for good
        if ([] !== $result['orphaned']) {
            $io->warning(sprintf('%d described urls are no longer declared by any bundle - remove them by hand if they are gone for good:', count($result['orphaned'])));
            $io->listing($result['orphaned']);
        }

        return Command::SUCCESS;
    }
}
