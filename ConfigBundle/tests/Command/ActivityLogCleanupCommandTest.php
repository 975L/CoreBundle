<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\ActivityLogCleanupCommand;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ActivityLogCleanupCommandTest extends TestCase
{
    private function runCommand(?string $retention, ActivityLogRepository $repository): int
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($retention);

        return new CommandTester(new ActivityLogCleanupCommand($repository, $configService))->execute([]);
    }

    public function testPurgesWithTheConfiguredRetention(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->once())->method('purgeOlderThan')->with(30)->willReturn(7);

        $this->assertSame(Command::SUCCESS, $this->runCommand('30', $repository));
    }

    // A site whose configs were never loaded still gets a bounded table
    public function testAMissingRowFallsBackOnTheDefault(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->once())->method('purgeOlderThan')->with(365)->willReturn(0);

        $this->assertSame(Command::SUCCESS, $this->runCommand(null, $repository));
    }

    public function testKeepsEverythingOnAZeroRetention(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->never())->method('purgeOlderThan');

        $this->assertSame(Command::SUCCESS, $this->runCommand('0', $repository));
    }
}
