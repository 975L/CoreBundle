<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\BackupDigestCommand;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\BackupDigestBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BackupDigestCommandTest extends TestCase
{
    private ?EmailSendRequest $sentEmail = null;
    private ?int $requestedDays = null;

    private function createBuilder(string $status = HealthCheckResult::STATUS_OK): BackupDigestBuilder
    {
        $builder = $this->createStub(BackupDigestBuilder::class);
        $builder->method('build')->willReturnCallback(function (int $days) use ($status): array {
            $this->requestedDays = $days;

            return [
                'status' => $status,
                'subject' => '[OK] Backups - example.com',
                'body' => "28 run(s): 28 ok\nLast run on 29/07/2026 06:07",
                'runs' => 28,
            ];
        });

        return $builder;
    }

    private function createCommand(string $status = HealthCheckResult::STATUS_OK, array $configs = [], ?EmailService $emailService = null): BackupDigestCommand
    {
        $values = array_merge([
            'site-backup-database' => 'example_db',
            'site-backup-mailto' => 'admin@example.com',
            'email-from' => 'noreply@example.com',
        ], $configs);
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => $values[$slug] ?? '');

        if (null === $emailService) {
            $emailService = $this->createStub(EmailService::class);
            $emailService->method('send')->willReturnCallback(function (EmailSendRequest $request): bool {
                $this->sentEmail = $request;

                return true;
            });
        }

        return new BackupDigestCommand($this->createBuilder($status), $configService, $emailService);
    }

    public function testTheDigestIsMailedToTheBackupRecipient(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('[OK] Backups - example.com', $this->sentEmail->subject);
        $this->assertStringContainsString('28 run(s): 28 ok', $this->sentEmail->text);
        $this->assertSame('admin@example.com', $this->sentEmail->to);
        // Replying to the digest must reach its sender, not the site's public contact form, which is where a null replyTo would fall back to
        $this->assertSame('noreply@example.com', $this->sentEmail->replyTo);
    }

    public function testTheWindowDefaultsToAWeek(): void
    {
        new CommandTester($this->createCommand())->execute([]);

        $this->assertSame(BackupDigestBuilder::DEFAULT_DAYS, $this->requestedDays);
    }

    public function testTheWindowCanBeWidened(): void
    {
        new CommandTester($this->createCommand())->execute(['--days' => '30']);

        $this->assertSame(30, $this->requestedDays);
    }

    // Setting up the schedule on a new server shouldn't mean waiting a week to see what will land in the mailbox
    public function testDryRunPrintsTheDigestWithoutSendingIt(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--dry-run' => true]);

        $this->assertNull($this->sentEmail);
        $this->assertStringContainsString('28 run(s): 28 ok', $tester->getDisplay());
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // An email is just as able to fail silently as the backups it reports on, so the scheduler gets the verdict too
    public function testAWindowWithoutAnyBackupExitsNonZero(): void
    {
        $tester = new CommandTester($this->createCommand(BackupDigestBuilder::STATUS_NONE));
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertNotNull($this->sentEmail);
    }

    // No backup is configured here, so a weekly "nothing ran" alarm would be pure noise
    public function testNothingIsSentWhenTheBackupIsNotConfigured(): void
    {
        $tester = new CommandTester($this->createCommand(BackupDigestBuilder::STATUS_NONE, ['site-backup-database' => '']));
        $tester->execute([]);

        $this->assertNull($this->sentEmail);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // A digest nobody receives is a digest that doesn't exist, and the scheduler is the only one left to tell
    public function testAMissingRecipientIsReportedAsAFailure(): void
    {
        $tester = new CommandTester($this->createCommand(HealthCheckResult::STATUS_OK, ['site-backup-mailto' => '']));
        $tester->execute([]);

        $this->assertNull($this->sentEmail);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // EmailService returns false rather than throwing on a transport failure, so the command is the one left to turn it into a verdict the scheduler can read
    public function testASendThatFailsIsReportedAsAFailure(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);
        $emailService->method('getLastError')->willReturn('Connection could not be established');

        $tester = new CommandTester($this->createCommand(HealthCheckResult::STATUS_OK, [], $emailService));
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Connection could not be established', $tester->getDisplay());
    }
}
