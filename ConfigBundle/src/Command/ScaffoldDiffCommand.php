<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Service\ScaffoldDiffer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The follow-up to "c975l:scaffold:install" reporting customized files: it names them, this one says which of them still matter. A file the site rewrote and a file whose upstream moved on read exactly alike in that warning, and the difference is the only thing worth acting on - see ScaffoldDiffer. Writes nothing, so it is safe to run on every update.
#[AsCommand(
    name: 'c975l:scaffold:diff',
    description: 'Tells the scaffold files this site customized on purpose from the ones whose scaffold has changed since'
)]
class ScaffoldDiffCommand extends Command
{
    public function __construct(
        private readonly ScaffoldDiffer $scaffoldDiffer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict the run to these relative paths (--path=templates/security, repeatable)')
            ->addOption('bundle-sources', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Directories holding git clones of the c975L bundles (a clone, or a directory of them), searched for a base the site\'s own history cannot give back - vendor/ is a Composer download, with no history of its own')
            ->addOption('acknowledge', null, InputOption::VALUE_NONE, 'Record that what the scaffold gained has been seen and turned down, so it stops being reported - no file is touched, only the recorded base moves forward, and only what the bundle changes after this is raised again. Narrow it with --path')
        ;
    }

    // The diff lines carry their own meaning, which colour merely makes readable at a glance - the text is already escaped by ScaffoldDiffer
    private function writeDiff(OutputInterface $output, string $diff): void
    {
        foreach (explode("\n", rtrim($diff)) as $line) {
            $style = match (true) {
                str_starts_with($line, '+') => 'fg=green',
                str_starts_with($line, '-') => 'fg=red',
                str_starts_with($line, '@@') => 'fg=cyan',
                default => null,
            };

            $output->writeln(null === $style ? '  ' . $line : sprintf('  <%s>%s</>', $style, $line));
        }
    }

    // One file, told in the order the reader decides on: what it is, what the scaffold did since, and only then where that was read from
    private function report(SymfonyStyle $io, OutputInterface $output, array $file): void
    {
        $io->section($file['file']);

        if (null === $file['base']) {
            $io->text('Delivered version unrecoverable - here is the plain diff against the current scaffold, yours on the right:');
            $io->newLine();
            $this->writeDiff($output, $file['fallback']);
            $io->newLine();

            return;
        }

        if ('' === $file['upstream']) {
            $io->text(sprintf('<info>Nothing to carry over</info> - the scaffold has not changed since %s, so this file holds this site\'s own work only.', $file['base']));
            $io->newLine();

            return;
        }

        $io->text(sprintf('<comment>The scaffold gained this since %s</comment>, and this site does not have it:', $file['base']));
        $io->newLine();
        $this->writeDiff($output, $file['upstream']);
        $io->newLine();
    }

    // The answer to a report the site has read and decided against - typically a template it redesigned, offered something it does not want. Named one by one rather than counted: this writes into a committed file, and what it moves on has to be readable at a glance before the commit
    private function acknowledge(SymfonyStyle $io, array $paths): int
    {
        $result = $this->scaffoldDiffer->acknowledge($paths);

        // Same stance as the diff branch below: a --path naming nothing is a typo, not a site with nothing to acknowledge
        if ($result['unmatched']) {
            $io->error(sprintf('No scaffold file matches: %s', implode(', ', $result['unmatched'])));

            return Command::FAILURE;
        }

        $files = $result['files'];
        if (!$files) {
            $io->success('No customized scaffold file to acknowledge.');

            return Command::SUCCESS;
        }

        $io->listing($files);
        $io->success(sprintf('%d file(s) acknowledged: their recorded base is now what the bundles ship today, and only what changes after this is reported. Nothing was copied - commit .c975l-scaffold.json.', count($files)));

        return Command::SUCCESS;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('acknowledge')) {
            return $this->acknowledge($io, $input->getOption('path'));
        }

        $result = $this->scaffoldDiffer->diff($input->getOption('path'), $input->getOption('bundle-sources'));

        // Same stance as "c975l:scaffold:install": a --path naming nothing reports a clean site, which is what a typo (or a path given as it stands in the bundle, "scaffold/src/…") would otherwise look like
        if ($result['unmatched']) {
            $io->error(sprintf('No scaffold file matches: %s', implode(', ', $result['unmatched'])));

            return Command::FAILURE;
        }

        $files = $result['files'];
        if (!$files) {
            $io->success('No customized scaffold file: nothing diverges from what the bundles ship.');

            return Command::SUCCESS;
        }

        foreach ($files as $file) {
            $this->report($io, $output, $file);
        }

        $behind = array_filter($files, static fn (array $file): bool => !empty($file['upstream']));
        $unknown = array_filter($files, static fn (array $file): bool => null === $file['base']);

        $message = sprintf(
            '%d customized file(s): %d with nothing to carry over, %d the scaffold has moved on from, %d undecided.',
            count($files),
            count($files) - count($behind) - count($unknown),
            count($behind),
            count($unknown)
        );

        // Never a failure, whatever it found: this runs at the tail of an update script, where a non-zero code would report a site as broken for holding a template it deliberately rewrote
        if (!$behind) {
            $io->success($message);

            return Command::SUCCESS;
        }

        // Named again under the warning, and not only in the sections above: an update script scrolls hundreds of lines past this, and what its end-of-run digest keeps is the warning block - a count alone would send the reader back through the whole output to learn which file it was about
        $io->warning($message);
        $io->listing(array_map(static fn (array $file): string => $file['file'], array_values($behind)));

        return Command::SUCCESS;
    }
}
