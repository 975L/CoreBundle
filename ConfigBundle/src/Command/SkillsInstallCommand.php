<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Service\SkillsInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Run it once after installing a bundle, and again whenever a composer update brings a new one in - the links themselves never go stale, pointing at the package rather than holding a copy of it
#[AsCommand(
    name: 'c975l:skills:install',
    description: 'Links every agent skill the installed bundles ship into .claude/skills/'
)]
class SkillsInstallCommand extends Command
{
    public function __construct(
        private readonly SkillsInstaller $skillsInstaller,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be linked and pruned, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $this->skillsInstaller->install($dryRun);

        // Named rather than counted: the point of the run is which skills an agent can load from now on, and a site seeing an unexpected name there learns which package ships it
        if ($result['linked']) {
            $io->text($dryRun ? 'To link:' : 'Linked:');
            $io->listing($result['linked']);
        }

        // A link this command did not make would be the one thing it could destroy, so the files it stepped around are named too - a skill of the site's own carrying the name of a shipped one is otherwise shadowed in silence
        if ($result['kept']) {
            $io->warning(sprintf('%d skill(s) left untouched: these are not links, so they hold something this site wrote.', count($result['kept'])));
            $io->listing($result['kept']);
            $io->text('Rename or delete them to let the shipped skill of that name be linked here.');
        }

        if ($result['pruned']) {
            $io->text($dryRun ? 'Dead links to delete (the skill is gone from the package):' : 'Dead links deleted (the skill is gone from the package):');
            $io->listing($result['pruned']);
        }

        $message = sprintf(
            $dryRun ? '%d skill(s) to link, %d already linked. Nothing was written.' : '%d skill(s) linked, %d already linked.',
            count($result['linked']),
            $result['skipped']
        );

        $io->success($message);

        return Command::SUCCESS;
    }
}
