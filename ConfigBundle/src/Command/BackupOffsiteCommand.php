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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
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
    // Past this many deletions the sync aborts rather than carrying them over. The failure this guards against is
    // not the exotic one: it's a gallery emptied by mistake, or a hacked site, propagated to the backup within hours.
    // Aborting is the right answer - it costs a night's mirroring and a look from a human, against a copy that
    // faithfully reproduces the damage
    private const MAX_DELETE = 100;

    private const DEFAULT_KEEP_DAYS = 15;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ConfigServiceInterface $configService,
        private readonly BackupPathCollector $pathCollector,
        private readonly OffsiteSynchronizer $offsiteSynchronizer,
        private readonly OffsiteState $offsiteState,
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
            // Not a failure: an install that has an outside machine pull is configured exactly like this, and the
            // dashboard already alerts on its own if nothing has left the server for too long
            $io->warning($reason);

            return Command::SUCCESS;
        }

        $paths = $this->pathCollector->getPaths(BackupPath::MODE_MIRROR);
        if (empty($paths)) {
            $io->warning('No folder is declared for mirroring, nothing to send.');

            return Command::SUCCESS;
        }

        $dated = (new \DateTimeImmutable())->format('Y-m-d');
        $failures = [];

        foreach ($paths as $path) {
            $io->text(sprintf('Mirroring %s', $path));

            $result = $this->offsiteSynchronizer->sync(
                $projectDir . '/' . $path,
                'files/' . $path,
                sprintf('deleted/%s/%s', $dated, $path),
                self::MAX_DELETE
            );

            if (!$result['ok']) {
                $failures[] = sprintf('%s: %s', $path, $result['error']);
                $io->error(sprintf('Mirroring %s failed: %s', $path, $result['error']));
            }
        }

        if (!empty($failures)) {
            $this->offsiteState->recordFailure($projectDir, implode(' | ', $failures), 'mirror');

            return Command::FAILURE;
        }

        $this->purgeDeletedFolders($io);
        $this->offsiteState->recordSuccess($projectDir, array_merge(
            ['what' => 'mirror', 'target' => $this->offsiteSynchronizer->getTarget(), 'paths' => $paths],
            $this->verify($io)
        ));

        $io->success('Offsite mirror completed.');

        return Command::SUCCESS;
    }

    // Read back from the destination rather than counted here: an rclone run exiting 0 says the transfer was
    // accepted, not that the files are there - the same reason this bundle reads its archives back with bzip2 --test
    // instead of trusting tar's exit code
    private function verify(SymfonyStyle $io): array
    {
        $size = $this->offsiteSynchronizer->size('files');
        if (null === $size) {
            return [];
        }

        $io->text(sprintf('Offsite now holds %d files (%s)', $size['count'], ByteFormatter::format($size['bytes'])));

        return ['files' => $size['count'], 'bytes' => $size['bytes']];
    }

    // The dated folders --backup-dir fills with what was overwritten or deleted. Where the destination takes its own
    // snapshots this is belt and braces - and the snapshots are the better half of it: on a Storage Box they sit in a
    // read-only ZFS directory this server couldn't touch even if its credentials leaked, which no purge run from here can claim
    private function purgeDeletedFolders(SymfonyStyle $io): void
    {
        $configured = $this->configService->get('site-backup-offsite-keep-days');
        $days = null === $configured ? self::DEFAULT_KEEP_DAYS : (int) $configured;

        if ($days <= 0) {
            return;
        }

        $result = $this->offsiteSynchronizer->purgeBackupDirs('deleted', $days);
        if (!$result['ok']) {
            $io->warning(sprintf('Purging the offsite deleted/ folders failed: %s', $result['error']));
        }
    }
}
