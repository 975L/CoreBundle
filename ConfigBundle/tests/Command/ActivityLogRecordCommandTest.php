<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\ActivityLogRecordCommand;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ActivityLogRecordCommandTest extends TestCase
{
    private function runCommand(array $options, ActivityLogRepository $repository, ?UserInterface $account = null): int
    {
        $accounts = $this->createStub(EntityRepository::class);
        $accounts->method('findOneBy')->willReturn($account);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($accounts);

        return new CommandTester(new ActivityLogRecordCommand($repository, $manager))->execute($options);
    }

    public function testRecordsARowMarkedAsComingFromTheConsole(): void
    {
        $account = $this->createStub(UserInterface::class);

        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->once())->method('record')->with($this->callback(static function (array $logs) use ($account): bool {
            $log = $logs[0];

            return $account === $log->getUser()
                && ActivityLog::ACTION_UPDATE === $log->getAction()
                && 'site_page' === $log->getEntityType()
                && '12' === $log->getEntityId()
                && 'Fixed a slug' === $log->getLabel()
                && [] === $log->getChanges()
                && ActivityLog::PATH_CONSOLE === $log->getPath();
        }));

        $this->assertSame(Command::SUCCESS, $this->runCommand(['--action' => 'update', '--type' => 'site_page', '--id' => '12', '--label' => 'Fixed a slug', '--by' => 'someone@example.com'], $repository, $account));
    }

    // A note signed by nobody would read like the work of a deleted account
    public function testAnUnknownAccountRecordsNothing(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->never())->method('record');

        $this->assertSame(Command::INVALID, $this->runCommand(['--action' => 'update', '--type' => 'site_page', '--label' => 'Fixed a slug', '--by' => 'nobody@example.com'], $repository));
    }

    public function testAnIncompleteNoteRecordsNothing(): void
    {
        $account = $this->createStub(UserInterface::class);
        $by = 'someone@example.com';

        foreach ([['--type' => 'site_page', '--label' => 'x', '--by' => $by], ['--action' => 'update', '--label' => 'x', '--by' => $by], ['--action' => 'update', '--type' => 'site_page', '--by' => $by], ['--action' => 'typo', '--type' => 'site_page', '--label' => 'x', '--by' => $by], ['--action' => 'update', '--type' => 'site_page', '--label' => 'x']] as $options) {
            $repository = $this->createMock(ActivityLogRepository::class);
            $repository->expects($this->never())->method('record');

            $this->assertSame(Command::INVALID, $this->runCommand($options, $repository, $account));
        }
    }

    // Past the column's size the insert would fail, after the SQL being noted has already run
    public function testAnIdLongerThanTheColumnRecordsNothing(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->never())->method('record');

        $this->assertSame(Command::INVALID, $this->runCommand(['--action' => 'update', '--type' => 'site_page', '--label' => 'x', '--id' => str_repeat('1', 65), '--by' => 'someone@example.com'], $repository, $this->createStub(UserInterface::class)));
    }

    public function testAnIdFillingTheColumnIsKept(): void
    {
        $repository = $this->createMock(ActivityLogRepository::class);
        $repository->expects($this->once())->method('record');

        $this->assertSame(Command::SUCCESS, $this->runCommand(['--action' => 'update', '--type' => 'site_page', '--label' => 'x', '--id' => str_repeat('1', 64), '--by' => 'someone@example.com'], $repository, $this->createStub(UserInterface::class)));
    }
}
