<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Repository;

use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ActivityLogRepositoryTest extends TestCase
{
    // One commit for the rows of one save, an import under /management otherwise paying one per entity
    public function testTheRowsOfOneSaveGoInOneTransaction(): void
    {
        $inTransaction = false;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(static function (\Closure $writes) use (&$inTransaction): void {
            $inTransaction = true;
            $writes();
            $inTransaction = false;
        });
        $connection->expects($this->exactly(2))->method('insert')->willReturnCallback(function () use (&$inTransaction): int {
            $this->assertTrue($inTransaction);

            return 1;
        });

        new ActivityLogRepository($this->createStub(ManagerRegistry::class), $connection)->record([$this->log(), $this->log()]);
    }

    private function log(): ActivityLog
    {
        return new ActivityLog(new \DateTimeImmutable(), null, ActivityLog::ACTION_UPDATE, 'Page', '42', 'Home', [], '/management');
    }
}
