<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\MySqlOptionsFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

// Exports the data (no CREATE TABLE) of the tables matching a prefix to one SQL file that truncates each of them before its inserts, site_config always excluded (ConfigCrudController::exportSql has its own non-destructive export) - a snapshot of a whole prefix, never a dev export to replay on prod, where it would empty what visitors wrote and import dev's app tokens. Uses the site-backup-db-* credentials of c975l:config:backup, so it works even when a GUI tool's DB user lacks export privileges
#[AsCommand(
    name: 'c975l:config:export-tables',
    description: 'Exports the data of all tables matching a prefix to a single SQL file',
    help: <<<'HELP'
    Dumps the data of every table matching <info>--prefix</info> (default <info>site_</info>, <info>site_config</info> excluded) into one SQL file that TRUNCATEs each table before its inserts.

    A whole prefix is a snapshot, kept as a backup: never replay it from dev on prod, where it would empty what visitors wrote (form outputs, reviews, favorites, plays, the activity log...) and import dev's app tokens.

    Carrying content from one environment to another is done case by case and by hand: export the tables one by one with the table's own name as prefix, read the TRUNCATE list at the top of the file (a longer name sharing it is listed too), then replay only what has to move.

      <info>php %command.full_name%</info>                    every "site_*" table
      <info>php %command.full_name% --prefix=shop_</info>     every "shop_*" table
      <info>php %command.full_name% --output=my.sql</info>    custom output path
    HELP
)]
class ExportTablesCommand extends Command
{
    // The table list goes through "mysql --execute", where neither the database name nor the prefix can be bound as a parameter - so both are refused outside the character set of a MySQL identifier rather than escaped. That rules out "%", and the "_" an identifier may hold is escaped in the LIKE (see tableListQuery()): either would quietly match tables it was never meant to, and every table this command lists is TRUNCATEd on replay
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9_]+$/';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ConfigServiceInterface $configService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Table name prefix to export', 'site_')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output SQL file path (defaults to var/export/<prefix>_<timestamp>.sql)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->exportTables(
            (string) $input->getOption('prefix'),
            (string) $input->getOption('output') ?: null,
        );

        if (null !== $result['error']) {
            $io->error($result['error']);

            return Command::FAILURE;
        }

        if (empty($result['tables'])) {
            $io->warning($result['message']);

            return Command::SUCCESS;
        }

        $io->success($result['message']);
        foreach ($result['tables'] as $table) {
            $io->text('- ' . $table);
        }

        return Command::SUCCESS;
    }

    // Public so the dashboard can trigger it too; $writeFile is false there, the dump being streamed back
    public function exportTables(string $prefix = 'site_', ?string $outputPath = null, bool $writeFile = true): array
    {
        $database = (string) $this->configService->get('site-backup-database');
        if (empty($database)) {
            return ['error' => 'site-backup-database is not configured in ConfigBundle.', 'tables' => [], 'path' => null, 'content' => null, 'message' => null];
        }
        if (!preg_match(self::IDENTIFIER_PATTERN, $database)) {
            return ['error' => 'site-backup-database must only contain letters, digits and underscores.', 'tables' => [], 'path' => null, 'content' => null, 'message' => null];
        }
        if ('' !== $prefix && !preg_match(self::IDENTIFIER_PATTERN, $prefix)) {
            return ['error' => 'The table prefix must only contain letters, digits and underscores.', 'tables' => [], 'path' => null, 'content' => null, 'message' => null];
        }

        $credentialsFile = MySqlOptionsFile::write($this->configService, 'site_export_');

        try {
            [$tables, $listError] = $this->getTableList($database, $prefix, $credentialsFile);
            if (null !== $listError) {
                return ['error' => "mysql failed while listing tables: {$listError}", 'tables' => [], 'path' => null, 'content' => null, 'message' => null];
            }
            if (empty($tables)) {
                return ['error' => null, 'tables' => [], 'path' => null, 'content' => null, 'message' => sprintf('No table found matching prefix "%s" in "%s".', $prefix, $database)];
            }

            [$content, $dumpError] = $this->dumpTables($database, $tables, $credentialsFile);
            if (null !== $dumpError) {
                return ['error' => "mysqldump failed while exporting table data: {$dumpError}", 'tables' => [], 'path' => null, 'content' => null, 'message' => null];
            }

            $path = null;
            if ($writeFile) {
                $path = $this->resolveOutputPath($outputPath ?? '', $prefix);
                @mkdir(\dirname($path), 0755, true);
                file_put_contents($path, $content);
            }
        } finally {
            unlink($credentialsFile);
        }

        return [
            'error' => null,
            'tables' => $tables,
            'path' => $path,
            'content' => $content,
            'message' => $writeFile
                ? sprintf('Exported %d table(s) matching "%s%%" to %s', count($tables), $prefix, $path)
                : sprintf('Exported %d table(s) matching "%s%%"', count($tables), $prefix),
        ];
    }

    // Returns [tables, error], error being null on success site_config is excluded: ConfigBundle's own export has upsert semantics a TRUNCATE would destroy
    private function getTableList(string $database, string $prefix, string $credentialsFile): array
    {
        $query = $this->tableListQuery($database, $prefix);

        $process = new Process([
            'mysql',
            '--defaults-extra-file=' . $credentialsFile,
            '--database=' . $database,
            '--silent', '--raw',
            '--execute=' . $query,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            return [[], trim($process->getErrorOutput()) ?: 'unknown error (exit code ' . $process->getExitCode() . ')'];
        }

        $tables = array_values(array_filter(
            array_map(trim(...), explode("\n", $process->getOutput())),
            fn ($t) => $t && 'TABLE_NAME' !== $t
        ));

        return [$tables, null];
    }

    // The tables starting with the prefix, its "_" escaped so "site_" never matches "sitemap_cache" nor "sites" - both values already checked against IDENTIFIER_PATTERN
    private function tableListQuery(string $database, string $prefix): string
    {
        $like = str_replace('_', '\\_', $prefix);

        return 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES '
            . "WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME LIKE '{$like}%' AND TABLE_TYPE != 'VIEW' "
            . "AND TABLE_NAME != 'site_config'";
    }

    // Dumps data only, wrapped in FK-checks-off + a TRUNCATE per table so the file can be replayed as-is on a target that already has (possibly wrong) data in these tables
    // No "--force" here, unlike BackupCommand which dumps one table per process and can afford to skip a broken one: a single table missing from a file that truncates every one of them destroys that table's data on replay, so the whole export fails rather than being written half-done - which is exactly what the exit code says without --force, while stderr also carries plain warnings (MariaDB 11 deprecating the "mysqldump" name on every single call, for one) that say nothing about the dump's completeness
    // Returns [sql, error], error being null on success
    private function dumpTables(string $database, array $tables, string $credentialsFile): array
    {
        $process = new Process(array_merge(
            ['mysqldump',
                '--defaults-extra-file=' . $credentialsFile,
                '--skip-comments', '--compact', '--lock-tables',
                '--quick', '--single-transaction', '--triggers', '--hex-blob',
                '--no-create-info', '--complete-insert',
                $database,
            ],
            $tables
        ));
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            return [null, trim($process->getErrorOutput()) ?: 'unknown error (exit code ' . $process->getExitCode() . ')'];
        }

        // A dump that came back empty on a successful exit still truncates every listed table on replay, so it is refused like any other failure
        $dump = $process->getOutput();
        if ('' === trim($dump)) {
            return [null, 'mysqldump produced an empty dump'];
        }

        $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $table) {
            $sql .= "TRUNCATE TABLE `{$table}`;\n";
        }
        $sql .= "\n" . $dump . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return [$sql, null];
    }

    private function resolveOutputPath(string $output, string $prefix): string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        if ('' !== $output) {
            return str_starts_with($output, '/') ? $output : $projectDir . '/' . $output;
        }

        $timestamp = new \DateTimeImmutable()->format('Y-m-d_H-i-s');

        return sprintf('%s/var/export/%s_%s.sql', $projectDir, rtrim($prefix, '_'), $timestamp);
    }
}
