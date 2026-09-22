<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Service\AiSearchIndexer;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\AiSiteSearchClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Purges the answers past their retention in any case, so a search switched off still keeps the privacy policy's promise, then rebuilds the index from the site's public pages. Indexes nothing on a site whose search isn't configured: scheduled on every site, it would otherwise read the whole site each night for a field nobody can use
#[AsCommand(name: 'c975l:ui:ai-search:index', description: 'Indexes the public pages the site search answers from, and purges the answers past their retention')]
class AiSearchIndexCommand extends Command
{
    public function __construct(
        private readonly AiSiteSearchClient $client,
        private readonly AiSearchIndexer $indexer,
        private readonly AiSiteSearch $aiSiteSearch,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note(sprintf('%d question(s) purgée(s).', $this->aiSiteSearch->purge()));

        if (!$this->client->isEnabled()) {
            $io->note('Recherche IA non configurée (ui-ai-assistant-site-*) : rien à indexer.');

            return Command::SUCCESS;
        }

        $result = $this->indexer->index();
        if (0 === $result['pages']) {
            $io->warning('Aucune page lue : index conservé tel quel.');
        } else {
            $io->success(sprintf(
                '%d page(s), %d passage(s) - %s.',
                $result['pages'],
                $result['chunks'],
                $result['changed'] ? 'index mis à jour' : 'contenu inchangé',
            ));
        }

        return Command::SUCCESS;
    }
}
