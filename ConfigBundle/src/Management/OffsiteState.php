<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// When something last *successfully* left this server, and how much of it. Written by whoever performs the transfer - c975l:config:backup:offsite when the site pushes, or --ack when an outside machine pulls - and read back by c975l:config:backup, so one HealthCheckResult row carries both "the backup ran" and "the backup left", which are not the same fact and used to be reported as if only the first existed.
class OffsiteState
{
    public const FILE = 'var/backup/.offsite.json';

    public function read(string $projectDir): ?array
    {
        $file = $projectDir . '/' . self::FILE;
        if (!is_file($file)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($file), true);

        return is_array($state) ? $state : null;
    }

    // Only a transfer that worked moves the clock forward. A failed run recorded as "at: now" would read as a fresh copy, and three nights of failures would look like three nights of success - the exact reassurance this file exists to withhold
    // Merges over the previous state rather than replacing it: two streams write here - the nightly mirror, which counts files and bytes, and the archives push every 6 hours, which counts neither - and a plain overwrite dropped whatever the other one had recorded
    public function recordSuccess(string $projectDir, array $details = []): void
    {
        $previous = $this->read($projectDir) ?? [];
        $failedWhat = $previous['failedWhat'] ?? null;

        // A stream only clears the failure it raised itself: the archives push succeeding says nothing about the mirror that failed last night, and clearing it there is how a month of broken mirrors read as "ok"
        // A failure no stream is named for - an older file, or one recorded without a stream - clears on any success
        $keepFailure = 'failed' === ($previous['status'] ?? null)
            && null !== $failedWhat
            && $failedWhat !== ($details['what'] ?? null);

        $this->write($projectDir, array_merge($previous, $details, [
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'status' => $keepFailure ? 'failed' : 'ok',
            'lastError' => $keepFailure ? ($previous['lastError'] ?? null) : null,
            'failedWhat' => $keepFailure ? $failedWhat : null,
        ]));
    }

    // Keeps the previous success's timestamp untouched, so the staleness the dashboard reads keeps growing while the failure is named alongside it
    public function recordFailure(string $projectDir, string $error, ?string $what = null): void
    {
        $previous = $this->read($projectDir) ?? [];

        $this->write($projectDir, array_merge($previous, [
            'status' => 'failed',
            'lastError' => $error,
            'failedWhat' => $what,
            'lastAttemptAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]));
    }

    // Hours since the last successful transfer, null when none was ever recorded - which the dashboard reads as "nothing has ever left this server", the worse of the two cases and not one to report as merely stale
    public function hoursSince(string $projectDir): ?float
    {
        $state = $this->read($projectDir);
        if (null === $state || empty($state['at'])) {
            return null;
        }

        $at = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $state['at']);

        return false === $at ? null : (time() - $at->getTimestamp()) / 3600;
    }

    private function write(string $projectDir, array $state): void
    {
        $file = $projectDir . '/' . self::FILE;
        $folder = \dirname($file);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
