<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\MessengerCleanupCommand;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\MessengerFailedMessageService;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MessengerCleanupCommandTest extends TestCase
{
    private string $projectDir;

    // cleanup() stamps its "last alert" marker in var/, so the command needs a project directory it can actually write to
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l_messenger_' . bin2hex(random_bytes(4));
        mkdir($this->projectDir . '/var', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir . '/var/MessengerAlertDateTimeFile');
        @rmdir($this->projectDir . '/var');
        @rmdir($this->projectDir);
    }

    private function createParameterBag(): ParameterBagInterface
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturnCallback(
            fn (string $name): string => 'kernel.project_dir' === $name ? $this->projectDir : '',
        );

        return $bag;
    }

    private function createConfigService(array $values): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        // Null for anything not listed, which is what ConfigService answers for an entry no row carries - a "" would say the row is there and empty, a different case for every int entry
        $service->method('get')->willReturnCallback(static fn (string $slug): mixed => $values[$slug] ?? null);

        return $service;
    }

    private function createMessage(bool $important): array
    {
        return [
            'id' => 1,
            'createdAt' => new \DateTimeImmutable(),
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'exceptionMessage' => 'Connection refused',
            'important' => $important,
        ];
    }

    private function createService(array $messages, int $purged = 4): MessengerFailedMessageService
    {
        $service = $this->createStub(MessengerFailedMessageService::class);
        $service->method('findAll')->willReturn($messages);
        $service->method('purgeOlderThan')->willReturn($purged);

        return $service;
    }

    private function createCommand(
        MessengerFailedMessageService $service,
        array $configValues,
        ?EmailService $emailService = null,
    ): MessengerCleanupCommand {
        return new MessengerCleanupCommand(
            $service,
            $this->createConfigService($configValues),
            $emailService ?? $this->createSendingEmailService(),
            $this->createParameterBag(),
        );
    }

    // A stub whose send() succeeds, $sent capturing the request it was given - the default stub would answer false, which the command reads as a digest that never left
    private function createSendingEmailService(?EmailSendRequest &$sent = null): EmailService
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(
            static function (EmailSendRequest $request) use (&$sent): bool {
                $sent = $request;

                return true;
            },
        );

        return $emailService;
    }

    public function testCleanupPurgesAndReportsWithoutAlertingWhenNothingIsImportant(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $command = $this->createCommand(
            $this->createService([$this->createMessage(false)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com'],
            $emailService,
        );

        $stats = $command->cleanup();

        $this->assertSame(4, $stats['purged']);
        $this->assertSame(0, $stats['important']);
        $this->assertFalse($stats['alerted']);
    }

    // "0" says keep everything, as it does for the backup and health check retentions - and it is the one value that must never reach purgeOlderThan(), which would then delete every failed message rather than none. A field cleared at the back-office says the same, the entry being declared "int"
    public function testCleanupPurgesNothingWithARetentionOfZero(): void
    {
        foreach (['0', ''] as $days) {
            $service = $this->createMock(MessengerFailedMessageService::class);
            $service->method('findAll')->willReturn([$this->createMessage(false)]);
            $service->expects($this->never())->method('purgeOlderThan');

            $stats = $this->createCommand($service, ['site-messenger-cleanup-retention-days' => $days])->cleanup();

            $this->assertSame(0, $stats['purged']);
        }
    }

    // The entry an install never loaded still gets the nightly purge, at the default window
    public function testCleanupFallsBackToTheDefaultWindowWithoutAConfigEntry(): void
    {
        $service = $this->createMock(MessengerFailedMessageService::class);
        $service->method('findAll')->willReturn([]);
        $service->expects($this->once())->method('purgeOlderThan')->with(30)->willReturn(2);

        $stats = $this->createCommand($service, [])->cleanup();

        $this->assertSame(2, $stats['purged']);
    }

    // The mailto being the only switch for the digest, an install that never filled it in still gets its nightly purge
    public function testCleanupStillPurgesWhenNoMailtoIsConfigured(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $stats = $this->createCommand($this->createService([$this->createMessage(true)]), [], $emailService)->cleanup();

        $this->assertSame(4, $stats['purged']);
        $this->assertFalse($stats['alerted']);
    }

    public function testCleanupSendsOneDigestForNewImportantFailures(): void
    {
        $sent = null;
        $command = $this->createCommand(
            $this->createService([$this->createMessage(true), $this->createMessage(true)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com', 'email-from' => 'noreply@example.com'],
            $this->createSendingEmailService($sent),
        );

        $stats = $command->cleanup();

        $this->assertTrue($stats['alerted']);
        $this->assertSame(2, $stats['newImportant']);
        $this->assertInstanceOf(EmailSendRequest::class, $sent);
        $this->assertSame('noreply@example.com', $sent->from);
        $this->assertSame('admin@example.com', $sent->to);
        // Replying to the digest must reach its sender, not the site's public contact form, which is where a null replyTo would fall back to
        $this->assertSame('noreply@example.com', $sent->replyTo);
        $this->assertStringContainsString('Connection refused', $sent->text);
        $this->assertFileExists($this->projectDir . '/var/MessengerAlertDateTimeFile');
    }

    // An empty email-from would leave EmailService with no sender to resolve, and the digest being sent first, that would take the purge down with it
    public function testCleanupFallsBackOnTheRecipientAsSenderWhenEmailFromIsEmpty(): void
    {
        $sent = null;
        $command = $this->createCommand(
            $this->createService([$this->createMessage(true)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com'],
            $this->createSendingEmailService($sent),
        );

        $stats = $command->cleanup();

        $this->assertInstanceOf(EmailSendRequest::class, $sent);
        $this->assertSame('admin@example.com', $sent->from);
        $this->assertSame(4, $stats['purged']);
    }

    // EmailService swallows a transport failure, so the marker has to stay put on one: moved anyway, it would bury the alert for good, the next run finding nothing "new" since it
    public function testCleanupKeepsTheMarkerWhenTheDigestCouldntBeSent(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);

        $stats = $this->createCommand(
            $this->createService([$this->createMessage(true)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com'],
            $emailService,
        )->cleanup();

        $this->assertFalse($stats['alerted']);
        $this->assertFileDoesNotExist($this->projectDir . '/var/MessengerAlertDateTimeFile');
        $this->assertSame(4, $stats['purged']);
    }

    // The marker file's timestamp is what makes the digest a one-per-batch alert rather than a nightly repeat
    public function testCleanupDoesntAlertTwiceForTheSameFailures(): void
    {
        touch($this->projectDir . '/var/MessengerAlertDateTimeFile', time() + 60);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $stats = $this->createCommand(
            $this->createService([$this->createMessage(true)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com'],
            $emailService,
        )->cleanup();

        $this->assertSame(1, $stats['important']);
        $this->assertSame(0, $stats['newImportant']);
        $this->assertFalse($stats['alerted']);
    }

    // The purge succeeding on its own would leave a scheduler reading a green run while an alert nobody has seen is still owed
    public function testExecuteExitsNonZeroWhenTheDigestCouldntBeSent(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);
        $emailService->method('getLastError')->willReturn('Connection could not be established');

        $tester = new CommandTester($this->createCommand(
            $this->createService([$this->createMessage(true)]),
            ['site-messenger-cleanup-mailto' => 'admin@example.com'],
            $emailService,
        ));
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Connection could not be established', $tester->getDisplay());
    }

    // Nothing is owed where no recipient is configured, so the nightly purge stays a green run
    public function testExecuteSucceedsWhenNoRecipientIsConfigured(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('send');

        $tester = new CommandTester($this->createCommand(
            $this->createService([$this->createMessage(true)]),
            [],
            $emailService,
        ));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
