<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\NotFoundCleanupCommand;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\NotFoundFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class NotFoundCleanupCommandTest extends TestCase
{
    /**
     * @return NotFoundRepository&MockObject
     */
    private function createRepository(): NotFoundRepository
    {
        return $this->createMock(NotFoundRepository::class);
    }

    private function createTester(NotFoundRepository $repository, ?string $retention): CommandTester
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($retention);

        return new CommandTester(new NotFoundCleanupCommand($repository, new NotFoundFilter(), $configService));
    }

    public function testPurgesWithTheConfiguredRetention(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->once())->method('purgeOlderThan')->with(30)->willReturn(7);

        $tester = $this->createTester($repository, '30');

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Deleted 7 recorded 404(s) not seen for 30 days', $tester->getDisplay());
    }

    // Only a missing row falls back to the default, as every other retention of the bundle reads it
    public function testPurgesWithTheDefaultRetentionWithoutAConfiguredValue(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->once())->method('purgeOlderThan')->with(90)->willReturn(0);

        $this->assertSame(Command::SUCCESS, $this->createTester($repository, null)->execute([]));
    }

    // A typed "0" means "keep everything" - purging on it would delete every row there is instead of none
    public function testKeepsEverythingOnAZeroRetention(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('purgeOlderThan');

        $tester = $this->createTester($repository, '0');

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('keep everything', $tester->getDisplay());
    }

    // Rows recorded before a rule caught them go whatever the retention, a "keep everything" one included: the broken link stays, the probe does not
    public function testDeletesTheRowsTheFilterNowTurnsDown(): void
    {
        $repository = $this->createRepository();
        $repository->method('findReferersIndexedByPath')->willReturn([
            '/item-1' => 'https://elsewhere.example/catalog',
            '/upload/server/php/' => 'https://example.com//upload/server/php',
            '/sitemap.xml.gz' => 'https://example.com',
        ]);
        $repository->expects($this->once())->method('deleteByPaths')->with(['/upload/server/php/', '/sitemap.xml.gz'])->willReturn(2);

        $tester = $this->createTester($repository, '0');

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Deleted 2 recorded 404(s) the filter now turns down', $tester->getDisplay());
    }
}
