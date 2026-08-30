<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Service\ScaffoldInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Standalone, re-runnable equivalent of the scaffold-install step of c975l:site:create (which is gated by a one-time lock file). Meant to pull in a bundle's scaffold/{src,templates,tests,translations} after installing it into an *existing* site (e.g. "composer require c975l/shop-bundle" later on) - ScaffoldInstaller is idempotent, so running this again on an unmodified project is a no-op.
#[AsCommand(
    name: 'c975l:scaffold:install',
    description: 'Installs (or refreshes) every installed c975L bundle\'s scaffold files into the project'
)]
class ScaffoldInstallCommand extends Command
{
    public function __construct(
        private readonly ScaffoldInstaller $scaffoldInstaller,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict the run to these relative paths (--path=src/Scheduler, repeatable), instead of the whole scaffold of every installed bundle')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be copied and backed up, write nothing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite (or delete, for a withdrawn file) the ones this site customized too, backing each one up into existingFiles/ first - narrow it with --path rather than adopting a whole scaffold blind')
        ;
    }

    // Named one by one with the source to compare against: these are the files carrying whatever this site does differently, and the upgrade they still need is a decision only their author can make. The pairs are listed outside the warning block, whose padding would wrap a vendor/ path mid-word - these are meant to be pasted into a diff
    private function reportDiverged(SymfonyStyle $io, array $diverged): void
    {
        if (!$diverged) {
            return;
        }

        $io->warning(sprintf('%d file(s) left untouched, this site having customized them since they were installed.', count($diverged)));
        $io->listing(array_map(
            static fn (string $file, string $source): string => $file . "\n  ← " . $source,
            array_keys($diverged),
            $diverged
        ));
        $io->text('Compare each with its source to carry over what the new scaffold changed, or re-run with --force (narrowed by --path=…) to take the new version and find yours back in existingFiles/.');
        $io->newLine();
    }

    // A deletion is the one thing here nobody can undo by re-running the command, so each file is named rather than counted - and named even outside a dry run, where the developer would otherwise learn what left the project from a git status. Under --force this list also holds the ones the site had customized, backed up rather than lost, and the message says where to find them back
    private function reportDeleted(SymfonyStyle $io, array $deleted, bool $dryRun, bool $force): void
    {
        if (!$deleted) {
            return;
        }

        $io->text(match (true) {
            $dryRun && $force => 'The scaffold no longer ships these - they would be deleted, any this site customized being backed up into existingFiles/ first:',
            $force => 'The scaffold no longer ships these - deleted, any this site customized having been backed up into existingFiles/:',
            $dryRun => 'The scaffold no longer ships these, and this site never touched them - they would be deleted:',
            default => 'The scaffold no longer ships these, and this site never touched them - deleted:',
        });
        $io->listing($deleted);
    }

    // Same stance as 'diverged' for a file that merely changed: what the site wrote is left in place, the bundle that withdrew it being named so its UPGRADE.md says what replaced it
    private function reportObsolete(SymfonyStyle $io, array $obsolete): void
    {
        if (!$obsolete) {
            return;
        }

        $io->warning(sprintf('%d file(s) the scaffold no longer ships, left in place: this site customized them.', count($obsolete)));
        $io->listing(array_map(
            static fn (string $file, string $bundle): string => $file . "\n  ← withdrawn by " . $bundle,
            array_keys($obsolete),
            $obsolete
        ));
        $io->text('See that bundle\'s UPGRADE.md for what replaces them, then delete them yourself, or re-run with --force (narrowed by --path=…) to have them deleted and find yours back in existingFiles/.');
        $io->newLine();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $result = $this->scaffoldInstaller->install($input->getOption('path'), $dryRun, $force);

        // What would be overwritten is the whole point of the dry run, a count alone saying nothing about which files diverged
        if ($dryRun && $result['files']) {
            $io->listing($result['files']);
        }

        $this->reportDiverged($io, $result['diverged']);
        $this->reportDeleted($io, $result['deleted'], $dryRun, $force);
        $this->reportObsolete($io, $result['obsolete']);

        // Nothing is ever backed up outside --force, a customized file being left alone rather than saved elsewhere: stating the count on every run advertises a directory most sites will never see, so the fragment travels with the number it describes. A count of its own rather than a share of the copies, backups coming from the deletions too
        $backedUp = $result['backedUp'] > 0
            ? sprintf(
                $dryRun ? ', %d to back up into existingFiles/' : ', %d backed up into existingFiles/',
                $result['backedUp']
            )
            : '';

        // Withdrawn files are the exception rather than the rule too, and a site that has none must not read "0 deleted" for good
        $deleted = $result['deleted']
            ? sprintf($dryRun ? ', %d to delete' : ', %d deleted', count($result['deleted']))
            : '';

        $message = sprintf(
            $dryRun
                ? '%d file(s) to copy%s%s, %d already up to date. Nothing was written.'
                : '%d file(s) copied%s%s, %d already up to date.',
            $result['copied'],
            $backedUp,
            $deleted,
            $result['skipped']
        );

        // A --path naming nothing reports zero everywhere, which reads exactly like an already up-to-date site: a typo, or a path given as it stands in the bundle ('scaffold/src/Scheduler'), would otherwise go unnoticed across the dozen sites it was being propagated to - hence the non-zero exit code too, so a loop over them stops instead of scrolling green
        if ($result['unmatched']) {
            $io->warning($message);
            $io->error(sprintf('No scaffold file matches: %s', implode(', ', $result['unmatched'])));

            return Command::FAILURE;
        }

        $io->success($message);

        $reminder = $this->scaffoldInstaller->themeImportReminder();
        if (null !== $reminder) {
            $io->note($reminder);
        }

        return Command::SUCCESS;
    }
}
