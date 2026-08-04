<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\SeoFilesWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Schedule it right after c975l:sitemaps:create: robots.txt only declares the sitemap index once that command has really written one, and llms.txt is built from the very urls the sitemap providers declare
#[AsCommand(
    name: 'c975l:seo:files:create',
    description: 'Creates public/robots.txt, public/humans.txt and public/llms.txt from the "seo" configs and the urls every registered SitemapProvider declares'
)]
class SeoFilesCreateCommand extends Command
{
    public function __construct(
        private readonly SeoFilesWriter $seoFilesWriter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $files = $this->seoFilesWriter->write();

        $io->success('SEO files created: ' . implode(', ', $files) . '.');

        // Says so rather than staying silent on a file the site may well have been expecting: nothing to index and nothing configured to say about the site is a normal state, not a failure
        if (!in_array('llms.txt', $files, true)) {
            $io->note('llms.txt was not written: no url declares a title and the "seo-llms-summary" config is empty.');
        }

        return Command::SUCCESS;
    }
}
