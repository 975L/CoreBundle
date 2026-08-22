<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the expired rows of the PdoSessionHandler table.
 *
 * PHP's own garbage collection is probabilistic (session.gc_probability/gc_divisor) and the
 * handler only prunes when PHP happens to call it, which on a managed host can be never:
 * 14 331 rows had piled up on a site in ten days, 14 329 of them expired. This is the same
 * DELETE the handler would run, on a cadence instead of on a dice roll.
 *
 * Usage:
 *   php bin/console c975l:config:sessions-cleanup
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:sessions-cleanup',
    description: 'Deletes the expired rows of the session table'
)]
class SessionsCleanupCommand extends Command
{
    // PdoSessionHandler's own defaults, which every c975L site keeps - a site that renamed them has no expired rows to find here and says so rather than failing
    private const string TABLE = 'sessions';
    private const string LIFETIME_COLUMN = 'sess_lifetime';

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Sites storing their sessions in files have no such table, and this bundle ships the task to all of them
        if (!$this->connection->createSchemaManager()->tablesExist([self::TABLE])) {
            $io->note(sprintf('No "%s" table, nothing to clean.', self::TABLE));

            return Command::SUCCESS;
        }

        // The column holds an expiry timestamp, not a duration - the very comparison PdoSessionHandler::close() makes, bound rather than written as the engine's own "now" so the statement stays portable
        $deleted = $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s < :now', self::TABLE, self::LIFETIME_COLUMN),
            ['now' => time()]
        );

        $io->success(sprintf('Deleted %d expired session(s).', $deleted));

        return Command::SUCCESS;
    }
}
