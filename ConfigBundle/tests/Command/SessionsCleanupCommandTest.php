<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\SessionsCleanupCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SessionsCleanupCommandTest extends TestCase
{
    // The expiry column holds a timestamp, so the bound value is the current time and never a duration
    public function testDeletesTheExpiredRows(): void
    {
        $connection = $this->connection(true);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('DELETE FROM sessions WHERE sess_lifetime < :now'),
                $this->callback(static fn (array $parameters): bool => abs($parameters['now'] - time()) < 5)
            )
            ->willReturn(42);

        $tester = new CommandTester(new SessionsCleanupCommand($connection));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Deleted 42 expired session(s)', $tester->getDisplay());
    }

    // The task is shipped to every site, including those keeping their sessions in files
    public function testSaysSoAndDeletesNothingWithoutTheTable(): void
    {
        $connection = $this->connection(false);
        $connection->expects($this->never())->method('executeStatement');

        $tester = new CommandTester(new SessionsCleanupCommand($connection));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('nothing to clean', $tester->getDisplay());
    }

    private function connection(bool $tableExists): Connection
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn($tableExists);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        return $connection;
    }
}
