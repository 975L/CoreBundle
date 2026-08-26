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
use c975L\ConfigBundle\Management\BackupResultRecorder;
use c975L\ConfigBundle\Management\BackupRetentionPurger;
use c975L\ConfigBundle\Management\ByteFormatter;
use c975L\ConfigBundle\Management\FileCounter;
use c975L\ConfigBundle\Management\OffsiteState;
use c975L\ConfigBundle\Management\OffsiteSynchronizer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Console command to back up what no git clone and no rebuild can bring back.
 *
 * Usage:
 *   php bin/console c975l:config:backup           # database table by table, plus the files declared
 *                                                  # in "archive" mode, then both sent offsite
 *   php bin/console c975l:config:backup --report  # also send a summary email after backup
 *
 * What it does *not* archive is as deliberate as what it does. The code is in git and comes back with a
 * clone; the configuration is in the database and comes back with the dump; the uploaded files are written
 * once and never change, so they are mirrored by c975l:config:backup:offsite rather than tarred. This
 * command used to roll public/ and private/ whole into a monthly tar.bz2, which meant compressing nine
 * gigabytes of JPEG for about one percent of gain, an hour of CPU against a one-hour timeout, and a copy of
 * it all kept for the retention window - to produce an archive whose only use was to be extracted whole.
 *
 * Lives in ConfigBundle rather than in SiteBundle, where it started: backing up the database and the
 * user files is not a concern of the pages/menus/blocks domain, it's what every install needs whichever
 * satellite bundles it happens to have - and none of ShopBundle, GalleryBundle, BookBundle or
 * CrowdfundingBundle depends on SiteBundle, so a shop-only or gallery-only install used to have no backup at all.
 *
 * All settings are managed via ConfigBundle (site-backup-* keys).
 * MySQL credentials (host/user/password) are written to a temporary file at runtime
 * and deleted immediately after the backup completes.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:backup',
    description: 'Backs up the database and the declared files',
    aliases: ['c975l:site:backup']
)]
class BackupCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 15;
    private const int DEFAULT_OFFSITE_MAX_AGE_HOURS = 30;

    // How much of a failed mirror's message the row carries. rclone names every file it couldn't move, which runs to a thousand characters of log for a single cause - enough of it to say which folder and why, the whole of it being one SSH session away in var/backup/.offsite.json
    private const int OFFSITE_ERROR_LENGTH = 200;

    private string $projectDir;
    private string $credentialsFile; // path to the runtime-generated temp file
    private string $database;
    private string $siteDomain;
    private string $mailto;
    private \DateTimeImmutable $startedAt;
    private string $backupFolder;
    private string $finalFolder;
    private string $report = '';
    private array $errors = [];
    private array $warnings = [];
    private array $archives = [];
    private array $tables = ['expected' => 0, 'dumped' => 0, 'missing' => []];
    private int $sqlBytes = 0;
    private int $filesBytes = 0;
    private int $filesCount = 0;
    private array $mirrorPaths = [];
    private array $offsite = ['status' => 'none', 'hours' => null, 'target' => '', 'mirrorError' => null];
    private int $durationSeconds = 0;
    private array $retention = [];

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ConfigServiceInterface $configService,
        private readonly EmailService $emailService,
        private readonly Connection $connection,
        private readonly BackupRetentionPurger $retentionPurger,
        private readonly BackupResultRecorder $resultRecorder,
        private readonly BackupPathCollector $pathCollector,
        private readonly OffsiteSynchronizer $offsiteSynchronizer,
        private readonly OffsiteState $offsiteState,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('report', null, InputOption::VALUE_NONE, 'Send a summary email after the backup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sendReport = $input->getOption('report');

        $this->projectDir = $this->parameterBag->get('kernel.project_dir');
        $this->database = (string) $this->configService->get('site-backup-database');
        $this->siteDomain = parse_url((string) $this->configService->get('site-url'), PHP_URL_HOST) ?? '';
        $this->mailto = (string) $this->configService->get('site-backup-mailto');

        if (empty($this->database)) {
            $io->error('site-backup-database is not configured in ConfigBundle.');

            return Command::FAILURE;
        }

        $this->startedAt = new \DateTimeImmutable();
        $this->backupFolder = $this->projectDir . '/var/backup';
        $this->finalFolder = sprintf(
            '%s/%s/%s/%s',
            $this->backupFolder,
            $this->startedAt->format('Y'),
            $this->startedAt->format('Y-m'),
            $this->startedAt->format('Y-m-d')
        );
        if (!is_dir($this->finalFolder)) {
            mkdir($this->finalFolder, 0755, true);
        }

        $this->credentialsFile = $this->createTempCredentialsFile();
        try {
            $this->backupMySql();
            $this->backupFiles();
            $this->writeManifest();
            $this->cleanup();
            $this->purgeOldBackups();
            $this->sendArchivesOffsite();
        } finally {
            unlink($this->credentialsFile);
        }

        // A long mysqldump may outlast wait_timeout, so the connection is forced back up first
        $this->connection->close();

        $this->recordOutcome();

        if (!empty($this->errors)) {
            if (!$this->sendErrorReport()) {
                $io->warning(sprintf('The error report could not be sent: %s', $this->emailService->getLastError() ?? 'unknown error'));
            }
            $io->error('Backup completed with errors.');

            return Command::FAILURE;
        }

        // The backup itself succeeded and says so, but a --report run whose report never left has not done what it was asked to do
        $reportSent = !$sendReport || $this->sendReport();
        if (!$reportSent) {
            $io->warning(sprintf('The report could not be sent: %s', $this->emailService->getLastError() ?? 'unknown error'));
        }

        $io->success('Backup completed.');

        return $reportSent ? Command::SUCCESS : Command::FAILURE;
    }

    private function backupMySql(): void
    {
        $db = $this->database;
        $dateTime = $this->startedAt->format('Y-m-d_-_H-i');

        $this->report .= sprintf("\nMySQL backup for \"%s\": %s\n", $db, $this->startedAt->format('Y-m-d H:i:s'));

        $tables = $this->getMySqlTableList(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$db}' AND TABLE_TYPE != 'VIEW'"
        );
        $this->tables['expected'] = \count($tables);

        foreach ($tables as $table) {
            $this->dumpTable($db, $table, $this->finalFolder . "/{$db}_-_{$table}.sql");
        }

        $this->reportMissingTables();
        $this->sqlBytes = $this->compressSqlFiles("MYSQL_-_{$db}_-_{$dateTime}_-_Tables.sql.tar.bz2");
    }

    private function createTempCredentialsFile(): string
    {
        $host = (string) ($this->configService->get('site-backup-db-host') ?: 'localhost');
        $user = (string) $this->configService->get('site-backup-db-user');
        $password = (string) $this->configService->get('site-backup-db-password');

        $tmpFile = tempnam(sys_get_temp_dir(), 'site_backup_');
        chmod($tmpFile, 0600);
        file_put_contents($tmpFile, "[client]\nhost={$host}\nuser={$user}\npassword={$password}\n");

        return $tmpFile;
    }

    private function getMySqlTableList(string $query): array
    {
        $process = new Process([
            'mysql',
            '--defaults-extra-file=' . $this->credentialsFile,
            '--database=' . $this->database,
            '--silent', '--raw',
            '--execute=' . $query,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'MySQL table list failed: ' . $process->getErrorOutput();

            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode("\n", $process->getOutput())),
            fn ($t) => $t && 'TABLE_NAME' !== $t
        ));
    }

    private function dumpTable(string $db, string $table, string $outFile): void
    {
        $process = new Process([
            'mysqldump',
            '--defaults-extra-file=' . $this->credentialsFile,
            '--skip-comments', '--compact', '--force', '--lock-tables',
            '--quick', '--single-transaction', '--triggers', '--hex-blob',
            $db, $table,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = "mysqldump failed for table {$table}: " . $process->getErrorOutput();
            $this->tables['missing'][] = $table;
            $this->report .= "- {$table} FAILED\n";

            return;
        }

        // Tables are dumped one by one, so restoring a single file in isolation must not fail on FK constraints referencing a table restored later (or not at all)
        file_put_contents($outFile, "SET FOREIGN_KEY_CHECKS=0;\n" . $process->getOutput() . "SET FOREIGN_KEY_CHECKS=1;\n");

        // Reported only now that the file exists, and with its size: the table list used to be written before the dump was even attempted, so a table appearing in the report proved nothing about it having been saved
        ++$this->tables['dumped'];
        $this->report .= sprintf("- %s (%s)\n", $table, $this->formatBytes((int) filesize($outFile)));
    }

    // A table counted in INFORMATION_SCHEMA but not dumped is an error in its own right, and not one the per-table failures always cover: a table created between the listing and the dump, or skipped for any other reason, would otherwise just be absent from a report nobody compares against anything
    private function reportMissingTables(): void
    {
        $missing = $this->tables['expected'] - $this->tables['dumped'];
        if ($missing <= 0) {
            return;
        }

        $this->report .= sprintf("MISSING: %d of %d tables were not dumped\n", $missing, $this->tables['expected']);

        // Only the difference the per-table failures above don't already account for: those have reported their own error each, naming the table, and repeating them here would double every count the dashboard shows
        $unexplained = $missing - \count($this->tables['missing']);
        if ($unexplained > 0) {
            $this->errors[] = sprintf('%d of %d tables were not dumped, with no failure reported for them.', $unexplained, $this->tables['expected']);
        }
    }

    private function compressSqlFiles(string $archiveName): int
    {
        $sqlFiles = glob($this->finalFolder . '/*.sql');
        if (empty($sqlFiles)) {
            return 0;
        }

        $process = new Process(array_merge(
            ['nice', 'tar', '--remove-files', '--bzip2', '--create', '--file', $archiveName],
            array_map(basename(...), $sqlFiles)
        ), $this->finalFolder);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'SQL tar compression failed: ' . $process->getErrorOutput();

            return 0;
        }

        return $this->verifyArchive($this->finalFolder . '/' . $archiveName);
    }

    // Reads the archive back and checks its integrity, rather than trusting tar's exit code: a truncated or corrupted archive is exactly the kind of backup that looks fine for months and fails the day it's needed.
    // Returns the archive size, which is also what tells an empty dump from a real one
    private function verifyArchive(string $path): int
    {
        if (!is_file($path)) {
            $this->errors[] = 'Archive missing after creation: ' . basename($path);

            return 0;
        }

        $process = new Process(['nice', 'bzip2', '--test', $path]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'Archive integrity check failed for ' . basename($path) . ': ' . $process->getErrorOutput();

            return 0;
        }

        $bytes = (int) filesize($path);
        $this->archives[] = ['name' => basename($path), 'bytes' => $bytes];
        $this->report .= sprintf("Archive %s: %s (integrity verified)\n", basename($path), $this->formatBytes($bytes));

        return $bytes;
    }

    // The files declared in "archive" mode: small, neither in git nor in the database, and dated on every run so their history is kept. .env.local is the case that matters - a restored server with every photo and no APP_SECRET does not start, and that is the discovery nobody wants to make on the day of the incident
    private function backupFiles(): void
    {
        $this->report .= sprintf("\nFiles backup for \"%s\": %s\n", $this->subjectLabel(), $this->startedAt->format('Y-m-d H:i:s'));

        $paths = $this->pathCollector->getPaths(BackupPath::MODE_ARCHIVE);
        if (empty($paths)) {
            $this->report .= "NO declared file to save\n";

            return;
        }

        foreach ($paths as $path) {
            $this->report .= sprintf("- %s\n", $path);
            $this->filesCount += FileCounter::count($this->projectDir . '/' . $path);
        }

        // -C changes into the project so archive members are relative ('./.env.local'), which is where a restore has to put them back. Handed absolute paths, tar stores home/…/.env.local and an extraction lands it in a home/ folder of its own instead of overwriting the file it was meant to replace
        $archive = sprintf(
            '%s/FILES_-_%s_-_%s.tar.bz2',
            $this->finalFolder,
            $this->subjectLabel(),
            $this->startedAt->format('Y-m-d_-_H-i')
        );

        $process = new Process(array_merge(
            ['nice', 'tar', '--bzip2', '--create', '-C', $this->projectDir, '--file', $archive],
            array_map(static fn (string $path) => './' . $path, $paths)
        ));
        $process->setTimeout(1800);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'Files tar failed: ' . $process->getErrorOutput();

            return;
        }

        $this->filesBytes = $this->verifyArchive($archive);
    }

    // What is mirrored rather than archived, published for whoever copies this server offsite - a puller that reads it stops having to know the site's layout, and a bundle installed tomorrow brings its own folders along without a single line changed on the machine that does the copying
    private function writeManifest(): void
    {
        $this->mirrorPaths = $this->pathCollector->getPaths(BackupPath::MODE_MIRROR);

        file_put_contents($this->backupFolder . '/manifest.json', json_encode([
            'site' => $this->subjectLabel(),
            'generatedAt' => $this->startedAt->format(\DateTimeInterface::ATOM),
            // Relative to the project directory, as everything else here is
            'archives' => 'var/backup',
            'mirror' => $this->mirrorPaths,
            // So whoever pulls knows to keep a longer window than the server does, rather than re-downloading what it has just purged here
            'retentionDays' => $this->retentionDays(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->report .= empty($this->mirrorPaths)
            ? "\nNo folder declared for mirroring\n"
            : sprintf("\nMirrored, not archived (see c975l:config:backup:offsite): %s\n", implode(', ', $this->mirrorPaths));
    }

    // The archives, copied rather than synced: they are added to here and purged on the local retention window, and a sync would carry that purge over to the offsite copy - which is exactly the history it exists to keep longer. An install that has an outside machine pull instead leaves the target empty and says so with --ack
    private function sendArchivesOffsite(): void
    {
        $this->offsite['target'] = $this->offsiteSynchronizer->getTarget();

        if ($this->offsiteSynchronizer->isConfigured()) {
            $result = $this->offsiteSynchronizer->copy($this->backupFolder, 'backup');

            if ($result['ok']) {
                $this->offsiteState->recordSuccess($this->projectDir, ['what' => 'archives', 'target' => $this->offsite['target']]);
                $this->report .= sprintf("\nArchives sent to %s/backup\n", $this->offsite['target']);
            } else {
                $this->errors[] = 'Sending the archives offsite failed: ' . $result['error'];
                $this->offsiteState->recordFailure($this->projectDir, $result['error'], 'archives');
            }
        }

        // Read back whichever wrote it, this run or the machine that pulls: a backup that ran perfectly and never left the server is not a backup, and until now nothing in the report or on the dashboard said so
        $hours = $this->offsiteState->hoursSince($this->projectDir);
        $maxAge = $this->offsiteMaxAgeHours();
        $state = $this->offsiteState->read($this->projectDir) ?? [];

        // What the last mirror run counted at the destination, so the row says how much is held offsite rather than leaving the mirrored folders as a silent hole - the whole point of declaring them instead of excluding them
        $this->offsite['mirrorFiles'] = $state['files'] ?? null;
        $this->offsite['mirrorBytes'] = $state['bytes'] ?? null;
        $this->offsite['hours'] = $hours;
        $this->offsite['status'] = null === $hours ? 'never' : ($hours > $maxAge ? 'stale' : 'ok');

        if ('never' === $this->offsite['status']) {
            $this->warnings[] = 'Nothing has ever left this server: no offsite copy is recorded.';
        } elseif ('stale' === $this->offsite['status']) {
            $this->warnings[] = sprintf('The last offsite copy is %d hours old, past the %d configured.', (int) $hours, $maxAge);
        }

        $this->report .= sprintf(
            "Offsite: %s%s\n",
            $this->offsite['status'],
            null === $hours ? '' : sprintf(' (%d hours ago)', (int) $hours)
        );

        $this->offsiteMirrorFailure($state);
    }

    // The mirror runs in its own command, on its own night, and until now recorded its failures where nothing read them: a mirror red since January sat under a row saying "offsite ok", the archives push having refreshed the timestamp the status is computed from every six hours.
    // Only the mirror. The archives push is this command's own, reported above the moment it fails, and reading its failure back here would say the same thing twice
    private function offsiteMirrorFailure(array $state): void
    {
        if ('failed' !== ($state['status'] ?? null) || 'mirror' !== ($state['failedWhat'] ?? null)) {
            return;
        }

        $this->offsite['mirrorError'] = $this->shorten((string) ($state['lastError'] ?? ''));
        $this->warnings[] = sprintf('The last offsite mirror failed: %s', $this->offsite['mirrorError']);
        $this->report .= sprintf("Offsite mirror FAILED: %s\n", $this->offsite['mirrorError']);
    }

    // Newlines folded rather than kept: the warning goes on a dashboard row and in an email, both of which read a paragraph of rclone log as one broken line
    private function shorten(string $error): string
    {
        $error = trim(preg_replace('/\s*\R\s*/', ' | ', $error));

        return mb_strlen($error) > self::OFFSITE_ERROR_LENGTH
            ? mb_substr($error, 0, self::OFFSITE_ERROR_LENGTH) . '...'
            : $error;
    }

    private function cleanup(): void
    {
        // An archive small enough to land here holds nothing usable, so it goes - but it goes on the record too: deleting it silently is how a table that dumped empty used to disappear without leaving any sign it had
        foreach (new Finder()->files()->in($this->finalFolder)->size('< 50') as $file) {
            $this->warnings[] = sprintf('Discarded empty file %s (%d bytes).', $file->getFilename(), $file->getSize());
            $this->report .= sprintf("DISCARDED empty file: %s (%d bytes)\n", $file->getFilename(), $file->getSize());
            unlink($file->getRealPath());
        }

        $this->deleteEmptyDirectories($this->backupFolder);

        $this->durationSeconds = time() - $this->startedAt->getTimestamp();
        $this->report .= sprintf(
            "\nEnd of backup: %s - Duration: %d minutes and %d seconds\n",
            new \DateTime()->format('Y-m-d H:i:s'),
            intdiv($this->durationSeconds, 60),
            $this->durationSeconds % 60
        );

        if (!empty($this->errors)) {
            $this->report .= "\nERRORS:\n" . implode("\n", $this->errors) . "\n";
        }
    }

    // Keeps the server's own rolling window of archives, whoever copies them offsite being expected to keep a longer one
    // Only a missing row falls back to the default. The entry is declared "int", so the field emptied at the back-office comes back as a 0 like a typed one, and both mean "keep every archive": `?: DEFAULT` read them as unset instead and purged at 15 days, BackupRetentionPurger's own "keep everything" guard never being reached
    private function retentionDays(): int
    {
        $configured = $this->configService->get('site-backup-retention-days');

        return null === $configured ? self::DEFAULT_RETENTION_DAYS : (int) $configured;
    }

    // How old the last offsite copy may get before the row goes stale
    // Unlike retentionDays(), a 0 here falls back too: the field emptied at the back-office comes back as a 0 for an "int" entry, and 0 hours names no window at all - it used to read as "never stale", so a mirror that stopped leaving a month ago still reported ok
    private function offsiteMaxAgeHours(): int
    {
        $configured = (int) $this->configService->get('site-backup-offsite-max-age-hours');

        return $configured > 0 ? $configured : self::DEFAULT_OFFSITE_MAX_AGE_HOURS;
    }

    private function purgeOldBackups(): void
    {
        $days = $this->retentionDays();
        $this->retention = array_merge(['days' => $days], $this->retentionPurger->purge($this->backupFolder, $days));

        $this->report .= sprintf(
            "\nRetention (%d days): %d run(s) deleted, %d run(s) kept on the server%s\n",
            $days,
            $this->retention['deleted'],
            $this->retention['runs'],
            null === $this->retention['oldest'] ? '' : sprintf(', oldest %s', $this->retention['oldest'])
        );
    }

    // The run as a HealthCheckResult row, so the dashboard sees every run and not only the weekly one carrying --report
    private function recordOutcome(): void
    {
        try {
            $this->resultRecorder->record($this->outcome());
        } catch (\Throwable $e) {
            // A backup that ran fine must not be reported as failed because its bookkeeping row couldn't be written - but the failure is worth an error report of its own, the dashboard now being blind
            $this->errors[] = 'Recording the backup result failed: ' . $e->getMessage();
        }
    }

    // What BackupResultRecorder turns into the row's status, summary and details
    private function outcome(): array
    {
        return [
            'url' => (string) ($this->configService->get('site-url') ?: $this->database),
            'database' => $this->database,
            'tables' => $this->tables,
            'sqlBytes' => $this->sqlBytes,
            'filesBytes' => $this->filesBytes,
            'filesCount' => $this->filesCount,
            'mirrorPaths' => $this->mirrorPaths,
            'offsite' => $this->offsite,
            'archives' => $this->archives,
            'durationSeconds' => $this->durationSeconds,
            'retention' => $this->retention,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    private function deleteEmptyDirectories(string $path): void
    {
        foreach (glob($path . '/*', GLOB_ONLYDIR) as $dir) {
            $this->deleteEmptyDirectories($dir);
            if (!new \FilesystemIterator($dir)->valid()) {
                rmdir($dir);
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        return ByteFormatter::format($bytes);
    }

    // Both reports answer whether they left: EmailService returns false where MailerInterface used to throw, and a report nobody knows never arrived is worth as little as no report at all. An unconfigured mailto is no failure, nothing being owed then
    private function sendErrorReport(): bool
    {
        if (empty($this->mailto)) {
            return true;
        }

        // An alert is replied to its own sender, not to the site's contact form: left null, replyTo would fall back to the public "email-reply-to" config key
        return $this->emailService->send(new EmailSendRequest(
            subject: '[ERROR] Backup failed - ' . $this->subjectLabel(),
            context: [],
            from: $this->emailFrom(),
            to: $this->mailto,
            replyTo: $this->emailFrom(),
            text: implode("\n", $this->errors) . "\n\nFull report:\n" . $this->report,
        ));
    }

    private function sendReport(): bool
    {
        if (empty($this->mailto)) {
            return true;
        }

        return $this->emailService->send(new EmailSendRequest(
            subject: 'Backup Report - ' . $this->subjectLabel(),
            context: [],
            from: $this->emailFrom(),
            to: $this->mailto,
            replyTo: $this->emailFrom(),
            text: $this->report,
        ));
    }

    // The domain only ever labels the subject and the archives, so an install without site-url falls back to the database name rather than getting no failure report at all
    private function subjectLabel(): string
    {
        return '' !== $this->siteDomain ? $this->siteDomain : $this->database;
    }

    // An empty sender leaves EmailService with no From to resolve, which would take the whole report down with it - the recipient doubles as the sender then, an address that is by definition deliverable here
    private function emailFrom(): string
    {
        $from = (string) $this->configService->get('email-from');

        return '' !== $from ? $from : $this->mailto;
    }
}
