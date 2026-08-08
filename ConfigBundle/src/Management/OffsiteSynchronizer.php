<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

// Sends what the backup produced to wherever the install keeps its offsite copy, through rclone.
//
// What this class deliberately does not hold: credentials. site-backup-offsite-target names a remote
// ("storagebox:975l.com"), nothing more - the secrets stay in rclone's own configuration, outside the
// application and outside the database. Nor is the binary's location configurable: a free-form path read
// from a back-office entry is an arbitrary code execution offered to any admin account that gets
// compromised, which is not something a public bundle does.
//
// An install that would rather have an outside machine pull its backups leaves the entry empty: nothing is
// sent, and c975l:config:backup:offsite --ack is what that machine calls to say the files did leave.
class OffsiteSynchronizer
{
    // A remote name as rclone spells it, then an optional path under it - letters, digits and the handful of separators a path needs, so nothing reaching Process can be read as an option or as another command
    private const TARGET_PATTERN = '#^[A-Za-z0-9_-]+:[A-Za-z0-9._/-]*$#';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function getTarget(): string
    {
        return rtrim((string) $this->configService->get('site-backup-offsite-target'), '/');
    }

    public function isConfigured(): bool
    {
        return '' !== $this->getTarget();
    }

    // Says why it can't run rather than failing later with rclone's own wording, the two reasons being a target nobody set and a binary the host doesn't have
    public function getUnavailabilityReason(): ?string
    {
        if (!$this->isConfigured()) {
            return 'site-backup-offsite-target is not configured, nothing is sent offsite from this server.';
        }

        if (1 !== preg_match(self::TARGET_PATTERN, $this->getTarget())) {
            return sprintf('site-backup-offsite-target "%s" is not a valid rclone target (expected "remote:path").', $this->getTarget());
        }

        return null === $this->findRclone() ? 'rclone was not found in the PATH nor in the project\'s bin/ folder.' : null;
    }

    // The dated archives: copied, never synced. They are added to and purged locally on their own retention window, and a sync would mirror that purge onto the offsite copy - which is precisely the history the offsite copy exists to keep longer
    public function copy(string $localPath, string $remoteSubPath, int $timeout = 1800): array
    {
        return $this->run(['copy', $localPath, $this->remote($remoteSubPath)], $timeout);
    }

    // The mirrored folders: synced, so a destination that has diverged comes back in line - but never destructively.
    // --backup-dir moves what would be overwritten or deleted into a dated folder instead of losing it, which is the
    // protection against the failure that actually happens: a gallery emptied by mistake, propagated within hours.
    // --max-delete aborts the run outright past that many deletions, so the mistake doesn't even reach the backup-dir
    public function sync(string $localPath, string $remoteSubPath, string $backupDirSubPath, int $maxDelete, int $timeout = 3600): array
    {
        return $this->run([
            'sync', $localPath, $this->remote($remoteSubPath),
            '--backup-dir', $this->remote($backupDirSubPath),
            '--max-delete', (string) $maxDelete,
        ], $timeout);
    }

    // What is actually at the destination, read back from it rather than counted here: an rclone run that exits 0 says the transfer was accepted, not that the files are there, and this bundle already reads its archives back rather than trusting tar's exit code
    public function size(string $remoteSubPath, int $timeout = 600): ?array
    {
        $result = $this->run(['size', '--json', $this->remote($remoteSubPath)], $timeout);
        if (!$result['ok']) {
            return null;
        }

        $size = json_decode($result['output'], true);

        return is_array($size) && isset($size['count'], $size['bytes'])
            ? ['count' => (int) $size['count'], 'bytes' => (int) $size['bytes']]
            : null;
    }

    // Deletes the dated backup-dir folders past the retention window, the destination's own snapshots being the better answer where it has them: on a Storage Box they live in a read-only ZFS directory this server could not touch even if its credentials leaked, which is not something a purge run from here can claim
    public function purgeBackupDirs(string $remoteSubPath, int $keepDays, int $timeout = 600): array
    {
        $result = $this->run([
            'delete', '--rmdirs', '--min-age', sprintf('%dd', $keepDays), $this->remote($remoteSubPath),
        ], $timeout);

        // A destination where nothing has ever been overwritten or deleted has no previous/ folder at all, which rclone
        // reports as an error. There is nothing to purge, and it is not a failure: left as one it warns on the very
        // first run, then every night for as long as the site's files don't change - a permanent warning being how a
        // real one goes unnoticed
        return !$result['ok'] && str_contains((string) $result['error'], 'directory not found')
            ? ['ok' => true, 'error' => null, 'output' => '']
            : $result;
    }

    private function remote(string $subPath): string
    {
        $subPath = trim($subPath, '/');

        return '' === $subPath ? $this->getTarget() : $this->getTarget() . '/' . $subPath;
    }

    // Modest concurrency and no buffering on purpose: rclone is written in Go and will happily take hundreds of megabytes, which a managed host capping memory per process kills without a word.
    // Protected rather than private so the tests can check how rclone's own output is read back without a binary to run - the whole point being what this class makes of an exit code and a message
    protected function run(array $arguments, int $timeout): array
    {
        $reason = $this->getUnavailabilityReason();
        if (null !== $reason) {
            return ['ok' => false, 'error' => $reason, 'output' => ''];
        }

        $process = new Process(array_merge(
            [$this->findRclone()],
            $this->configArgument(),
            $arguments,
            ['--transfers', '2', '--checkers', '4', '--buffer-size', '0', '--retries', '3']
        ));
        $process->setTimeout($timeout);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'error' => $process->isSuccessful() ? null : trim($process->getErrorOutput()),
            'output' => $process->getOutput(),
        ];
    }

    // rclone.conf at the root of the project when the install has one, rclone's own default otherwise.
    //
    // Left to itself, rclone reads ~/.config/rclone/rclone.conf - which resolves in an interactive SSH session and
    // may not under a task scheduler, where HOME is often absent or different. rclone then starts with no remote
    // configured at all and reports the target as unknown, a failure that reads exactly like "rclone doesn't work on
    // this host". A path under the project depends on nothing but the project.
    //
    // The root rather than var/: var/ is a runtime scratch folder nobody writes to by hand, and the local backup
    // scripts that skip it would skip this file too - the one file whose loss stops the backups from leaving. At the
    // root it sits with the other tool configuration an operator maintains, still outside the document root.
    //
    // It is a location, not a setting: nothing in the database points here, so no back-office entry can aim rclone at
    // a file of someone else's choosing. The scaffold adds it to .gitignore, credentials never being committed
    public function getConfigFile(): ?string
    {
        $file = $this->parameterBag->get('kernel.project_dir') . '/rclone.conf';

        return is_file($file) ? $file : null;
    }

    private function configArgument(): array
    {
        $file = $this->getConfigFile();

        return null === $file ? [] : ['--config', $file];
    }

    // The PATH first, then the project's own bin/ - where a host that ships no rclone takes the single static binary its own admin put there. Never a path read from configuration
    private function findRclone(): ?string
    {
        $local = $this->parameterBag->get('kernel.project_dir') . '/bin/rclone';

        return (new ExecutableFinder())->find('rclone', is_executable($local) ? $local : null);
    }
}
