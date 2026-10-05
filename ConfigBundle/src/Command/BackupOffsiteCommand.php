<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathCollector;
use c975L\ConfigBundle\Management\ByteFormatter;
use c975L\ConfigBundle\Management\OffsiteState;
use c975L\ConfigBundle\Management\OffsiteSynchronizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mirrors the folders declared in "mirror" mode to wherever the install keeps its offsite copy.
 *
 * Usage:
 *   php bin/console c975l:config:backup:offsite            # mirror the declared folders
 *   php bin/console c975l:config:backup:offsite --ack      # transfer nothing, record that one happened
 *
 * Separate from c975l:config:backup because the two have neither the same cadence nor the same duration: the
 * database is small and wanted every few hours, the uploads are large and written once, so they are worth a
 * nightly pass and nothing more. The first run of a media-heavy site transfers everything and takes as long as
 * that takes - seed it by hand once, from an SSH session, rather than letting it block the scheduler's single
 * worker for an hour. Every run after that carries only the files added since, which is the whole point of
 * mirroring content that never changes.
 *
 * The mirror is exact, deletions included: the destination must take its own snapshots (a Hetzner Storage Box
 * does, read-only and out of this server's reach), which are what bring back a file deleted or overwritten here.
 *
 * --ack is for installs that don't push at all: an outside machine pulls the backups (the safer model, the
 * server then holding no credentials to its own backup) and calls this afterwards, so the dashboard knows the
 * files did leave. Without it, a site that pulls would be permanently reported as never backed up offsite.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:backup:offsite',
    description: 'Mirrors the declared folders to the offsite copy'
)]
class BackupOffsiteCommand extends Command
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly BackupPathCollector $pathCollector,
        private readonly OffsiteSynchronizer $offsiteSynchronizer,
        private readonly OffsiteState $offsiteState,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('ack', null, InputOption::VALUE_NONE, 'Record that an outside machine has pulled the backups, transferring nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        if ($input->getOption('ack')) {
            $this->offsiteState->recordSuccess($projectDir, ['what' => 'pulled']);
            $io->success('Offsite copy acknowledged.');

            return Command::SUCCESS;
        }

        $reason = $this->offsiteSynchronizer->getUnavailabilityReason();
        if (null !== $reason) {
            // Not a failure: an install that has an outside machine pull is configured exactly like this, and the dashboard already alerts on its own if nothing has left the server for too long
            $io->warning($reason);

            return Command::SUCCESS;
        }

        $paths = $this->pathCollector->getPaths(BackupPath::MODE_MIRROR);
        if (empty($paths)) {
            $io->warning('No folder is declared for mirroring, nothing to send.');

            return Command::SUCCESS;
        }

        $failures = [];

        foreach ($paths as $path) {
            $io->text(sprintf('Mirroring %s', $path));

            $result = $this->offsiteSynchronizer->sync($projectDir . '/' . $path, 'files/' . $path);

            if (!$result['ok']) {
                $failures[] = sprintf('%s: %s', $path, $result['error']);
                $io->error(sprintf('Mirroring %s failed: %s', $path, $result['error']));
            }
        }

        if (!empty($failures)) {
            $this->offsiteState->recordFailure($projectDir, implode(' | ', $failures), 'mirror');

            // The scheduler only logs the exit code, so the reason has to be logged here to reach the error mail
            $this->logger?->error('Offsite mirror failed: {failures}', ['failures' => implode(' | ', $failures)]);

            return Command::FAILURE;
        }

        $this->offsiteState->recordSuccess($projectDir, array_merge(
            ['what' => 'mirror', 'target' => $this->offsiteSynchronizer->getTarget(), 'paths' => $paths],
            $this->verify($io)
        ));

        $io->success('Offsite mirror completed.');

        return Command::SUCCESS;
    }

    // Read back from the destination rather than counted here: an rclone run exiting 0 says the transfer was accepted, not that the files are there - the same reason this bundle reads its archives back with bzip2 --test instead of trusting tar's exit code
    private function verify(SymfonyStyle $io): array
    {
        $size = $this->offsiteSynchronizer->size('files');
        if (null === $size) {
            return [];
        }

        $io->text(sprintf('Offsite now holds %d files (%s)', $size['count'], ByteFormatter::format($size['bytes'])));

        return ['files' => $size['count'], 'bytes' => $size['bytes']];
    }
}
