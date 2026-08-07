<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\BackupCommand;
use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathCollector;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\ConfigBundle\Management\BackupResultRecorder;
use c975L\ConfigBundle\Management\BackupRetentionPurger;
use c975L\ConfigBundle\Management\OffsiteState;
use c975L\ConfigBundle\Management\OffsiteSynchronizer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class BackupCommandTest extends TestCase
{
    private string $projectDir;
    private array $callOrder;
    private array $recorded;
    private array $declaredPaths;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-backup-command-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
        $this->callOrder = [];
        $this->recorded = [];
        $this->declaredPaths = [];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createParameterBag(): ParameterBagInterface
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturnCallback(
            fn (string $name): string => 'kernel.project_dir' === $name ? $this->projectDir : ''
        );

        return $bag;
    }

    // Bogus credentials make mysql fail instantly, driving execute() into its error-report path
    private function createConfigService(array $overrides = []): ConfigServiceInterface
    {
        $values = array_merge([
            'site-backup-database' => 'test_db',
            'site-backup-db-host' => '127.0.0.1',
            'site-backup-db-user' => 'c975l_test_invalid_user',
            'site-backup-db-password' => 'wrong',
            'site-url' => 'https://example.com',
            'site-backup-mailto' => 'admin@example.com',
            'site-backup-retention-days' => '15',
            // Left unconfigured on purpose: no test may reach out to a real remote, and an install that has an outside machine pull is configured exactly like this
            'site-backup-offsite-target' => '',
            'site-backup-offsite-max-age-hours' => '30',
            'email-from' => 'noreply@example.com',
        ], $overrides);
        $service = $this->createStub(ConfigServiceInterface::class);
        // Null for anything not listed, which is what ConfigService answers for an entry no row carries - a "" would say the row is there and empty, a different case for every int entry
        $service->method('get')->willReturnCallback(fn (string $key) => $values[$key] ?? null);

        return $service;
    }

    // Captures what the command hands over to be recorded, without going anywhere near a database
    private function createResultRecorder(): BackupResultRecorder
    {
        $recorder = $this->createStub(BackupResultRecorder::class);
        $recorder->method('record')->willReturnCallback(function (array $outcome): void {
            $this->recorded = $outcome;
        });

        return $recorder;
    }

    // The bundles' declarations, as the compiler pass would have collected them
    private function createPathCollector(): BackupPathCollector
    {
        $provider = new class ($this->declaredPaths) implements BackupPathProviderInterface {
            public function __construct(private readonly array $paths)
            {
            }

            public function getBackupPaths(): array
            {
                return $this->paths;
            }
        };

        return new BackupPathCollector([$provider], $this->createParameterBag());
    }

    private function createCommand(?EmailService $emailService = null, ?Connection $connection = null, array $configOverrides = []): BackupCommand
    {
        $configService = $this->createConfigService($configOverrides);

        return new BackupCommand(
            $this->createParameterBag(),
            $configService,
            $emailService ?? $this->createStub(EmailService::class),
            $connection ?? $this->createStub(Connection::class),
            new BackupRetentionPurger(new Filesystem()),
            $this->createResultRecorder(),
            $this->createPathCollector(),
            new OffsiteSynchronizer($configService, $this->createParameterBag()),
            new OffsiteState(),
        );
    }

    // A long dump can leave the connection idle past wait_timeout, so it must be closed first
    public function testExecuteClosesConnectionBeforeSendingReport(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('close')
            ->willReturnCallback(function (): void {
                $this->callOrder[] = 'close';
            });

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (): bool {
                $this->callOrder[] = 'send';

                return true;
            });

        $tester = new CommandTester($this->createCommand($emailService, $connection));
        $tester->execute([]);

        $this->assertSame(['close', 'send'], $this->callOrder);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // Regression guard: MySQL is always dumped table by table now, there's no more --full/whole-database mode
    public function testConfigureHasNoFullOption(): void
    {
        $this->assertFalse($this->createCommand()->getDefinition()->hasOption('full'));
    }

    // The command moved to ConfigBundle, but the schedulers and crontabs already deployed still name the old one
    public function testTheFormerSiteBundleNameIsKeptAsAnAlias(): void
    {
        $this->assertContains('c975l:site:backup', $this->createCommand()->getAliases());
    }

    // Only what a bundle declared is archived - not a root walked whole. public/ and private/ used to be tarred
    // entirely, which made this command's business to know where every other bundle stores things
    public function testOnlyTheDeclaredArchivePathsAreTarred(): void
    {
        file_put_contents($this->projectDir . '/.env.local', 'APP_SECRET=secret');
        mkdir($this->projectDir . '/public/medias', 0775, true);
        file_put_contents($this->projectDir . '/public/medias/photo.jpg', 'photo');
        $this->declaredPaths = [
            new BackupPath('.env.local', BackupPath::MODE_ARCHIVE),
            new BackupPath('public/medias', BackupPath::MODE_MIRROR),
        ];

        $this->runAndCaptureReport();

        $archives = glob($this->projectDir . '/var/backup/*/*/*/FILES_-_*.tar.bz2');
        $this->assertCount(1, $archives);

        $process = new Process(['tar', '--list', '--file', $archives[0]]);
        $process->run();

        // The mirrored folder is nowhere in it: nine gigabytes of JPEG have nothing to gain from bzip2 and everything to lose to a one-hour timeout
        $this->assertSame(['./.env.local'], array_values(array_filter(explode("\n", $process->getOutput()))));
    }

    // Handed absolute paths, tar stores home/…/.env.local, and an extraction lands it in a home/ folder of its own instead of overwriting the file it was meant to replace
    public function testTheArchiveStoresPathsRelativeToTheProject(): void
    {
        file_put_contents($this->projectDir . '/.env.local', 'APP_SECRET=secret');
        $this->declaredPaths = [new BackupPath('.env.local', BackupPath::MODE_ARCHIVE)];

        $this->assertStringNotContainsString($this->projectDir, $this->runAndCaptureReport());
    }

    // An install declaring nothing to archive must not be told its backup failed over it
    public function testNoDeclaredFileIsReportedAndNotAnError(): void
    {
        $report = $this->runAndCaptureReport();

        $this->assertStringContainsString('NO declared file to save', $report);
        $this->assertStringNotContainsString('Files tar failed', $report);
    }

    // Whoever copies this server offsite reads the manifest instead of knowing the layout, so a bundle installed tomorrow brings its folders along without a line changed on the machine doing the copying
    public function testTheManifestPublishesWhatIsMirroredRatherThanArchived(): void
    {
        mkdir($this->projectDir . '/public/medias', 0775, true);
        $this->declaredPaths = [new BackupPath('public/medias', BackupPath::MODE_MIRROR)];

        $this->runAndCaptureReport();

        $manifest = json_decode((string) file_get_contents($this->projectDir . '/var/backup/manifest.json'), true);

        $this->assertSame(['public/medias'], $manifest['mirror']);
        $this->assertSame('var/backup', $manifest['archives']);
        $this->assertSame(15, $manifest['retentionDays']);
    }

    // A dump that ran, was verified and never left the machine is a dump the fire takes with the server, and "backup ok" used to say exactly the same thing either way
    public function testABackupThatNeverLeftTheServerIsWarnedAbout(): void
    {
        $report = $this->runAndCaptureReport();

        $this->assertStringContainsString('Offsite: never', $report);
        $this->assertContains('Nothing has ever left this server: no offsite copy is recorded.', $this->recorded['warnings']);
    }

    // An install whose backups are pulled by an outside machine says so with --ack, and must not then be reported as never backed up offsite
    public function testAnAcknowledgedOffsiteCopyCountsAsHavingLeft(): void
    {
        (new OffsiteState())->recordSuccess($this->projectDir, ['what' => 'pulled']);

        $report = $this->runAndCaptureReport();

        $this->assertStringContainsString('Offsite: ok', $report);
        $this->assertSame('ok', $this->recorded['offsite']['status']);
    }

    // Past site-backup-offsite-max-age-hours, a copy that stopped leaving is what the dashboard has to show - the archives themselves being written all along, and looking perfectly healthy
    public function testAnOffsiteCopyPastItsMaxAgeIsWarnedAbout(): void
    {
        (new OffsiteState())->recordSuccess($this->projectDir, []);
        $state = json_decode((string) file_get_contents($this->projectDir . '/' . OffsiteState::FILE), true);
        $state['at'] = (new \DateTimeImmutable('-40 hours'))->format(\DateTimeInterface::ATOM);
        file_put_contents($this->projectDir . '/' . OffsiteState::FILE, json_encode($state));

        $this->runAndCaptureReport();

        $this->assertSame('stale', $this->recorded['offsite']['status']);
    }

    // The field emptied at the back-office comes back as a 0, and a 0 used to switch the staleness check off entirely - a copy that stopped leaving a month ago still reporting "ok"
    public function testAnEmptiedMaxAgeFallsBackInsteadOfSilencingTheAlert(): void
    {
        (new OffsiteState())->recordSuccess($this->projectDir, []);
        $state = json_decode((string) file_get_contents($this->projectDir . '/' . OffsiteState::FILE), true);
        $state['at'] = (new \DateTimeImmutable('-40 hours'))->format(\DateTimeInterface::ATOM);
        file_put_contents($this->projectDir . '/' . OffsiteState::FILE, json_encode($state));

        $this->runAndCaptureReport(['site-backup-offsite-max-age-hours' => '0']);

        $this->assertSame('stale', $this->recorded['offsite']['status']);
    }

    // Every run leaves a trace now, not only the weekly one carrying --report
    public function testTheRunOutcomeIsHandedOverToTheRecorder(): void
    {
        $this->runAndCaptureReport();

        $this->assertSame('https://example.com', $this->recorded['url']);
        $this->assertArrayHasKey('tables', $this->recorded);
        $this->assertArrayHasKey('retention', $this->recorded);
        $this->assertArrayHasKey('offsite', $this->recorded);
        $this->assertArrayHasKey('mirrorPaths', $this->recorded);
        $this->assertNotEmpty($this->recorded['errors']);
    }

    // The retention window is applied by the run itself, so the server keeps a rolling set of archives without anyone logging in
    public function testOldRunsArePurgedAndReportedOn(): void
    {
        $old = sprintf('%s/var/backup/2020/2020-01/2020-01-01', $this->projectDir);
        mkdir($old, 0775, true);
        file_put_contents($old . '/archive.tar.bz2', 'old');

        $report = $this->runAndCaptureReport();

        $this->assertDirectoryDoesNotExist($old);
        $this->assertStringContainsString('Retention (15 days): 1 run(s) deleted', $report);
    }

    // A "0" typed in the back-office means "keep every archive", and used to be read as an unset entry - ie. purged at 15 days, the very opposite, BackupRetentionPurger's own guard never getting a zero to act on
    public function testARetentionOfZeroKeepsEveryRun(): void
    {
        $old = sprintf('%s/var/backup/2020/2020-01/2020-01-01', $this->projectDir);
        mkdir($old, 0775, true);
        file_put_contents($old . '/archive.tar.bz2', 'old');

        $report = $this->runAndCaptureReport(['site-backup-retention-days' => '0']);

        $this->assertDirectoryExists($old);
        $this->assertStringContainsString('Retention (0 days): 0 run(s) deleted', $report);
    }

    // A field cleared at the back-office says the same thing as that zero, the entry being declared "int" - what it must not be confused with is the unset entry below
    public function testAnEmptiedRetentionKeepsEveryRun(): void
    {
        $old = sprintf('%s/var/backup/2020/2020-01/2020-01-01', $this->projectDir);
        mkdir($old, 0775, true);
        file_put_contents($old . '/archive.tar.bz2', 'old');

        $report = $this->runAndCaptureReport(['site-backup-retention-days' => '']);

        $this->assertDirectoryExists($old);
        $this->assertStringContainsString('Retention (0 days): 0 run(s) deleted', $report);
    }

    // The entry an install never loaded still gets the rolling window, the fallback being what the zero above must not be confused with
    public function testAnUnsetRetentionFallsBackToTheDefaultWindow(): void
    {
        $old = sprintf('%s/var/backup/2020/2020-01/2020-01-01', $this->projectDir);
        mkdir($old, 0775, true);
        file_put_contents($old . '/archive.tar.bz2', 'old');

        $report = $this->runAndCaptureReport(['site-backup-retention-days' => null]);

        $this->assertDirectoryDoesNotExist($old);
        $this->assertStringContainsString('Retention (15 days): 1 run(s) deleted', $report);
    }

    // An install without site-url gets its failure report all the same, the domain only ever labelling the subject
    public function testAFailureIsStillReportedWithoutASiteUrl(): void
    {
        $capturedEmail = null;

        (new CommandTester($this->createCommand($this->createCapturingEmailService($capturedEmail), null, ['site-url' => '', 'email-from' => ''])))->execute([]);

        $this->assertNotNull($capturedEmail);
        $this->assertSame('[ERROR] Backup failed - test_db', $capturedEmail->subject);
        $this->assertSame('admin@example.com', $capturedEmail->from);
    }

    // Replying to a backup alert must reach whoever sent it, not the site's public contact form, which is where a null replyTo would fall back to
    public function testTheErrorReportIsRepliedToItsOwnSender(): void
    {
        $capturedEmail = null;

        (new CommandTester($this->createCommand($this->createCapturingEmailService($capturedEmail))))->execute([]);

        $this->assertSame('noreply@example.com', $capturedEmail->replyTo);
    }

    // EmailService returns false where MailerInterface used to throw, so a report that never left has to be said out loud
    public function testAReportThatCouldNotBeSentIsSurfacedOnTheConsole(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturn(false);
        $emailService->method('getLastError')->willReturn('Connection could not be established');

        $tester = new CommandTester($this->createCommand($emailService));
        $tester->execute([]);

        // The console wraps its warning block, so only the head of the message is matched here
        $this->assertStringContainsString('The error report could not be sent', $tester->getDisplay());
    }

    // A stub whose send() succeeds, $captured keeping the request it was given
    private function createCapturingEmailService(?EmailSendRequest &$captured): EmailService
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('send')->willReturnCallback(
            static function (EmailSendRequest $request) use (&$captured): bool {
                $captured = $request;

                return true;
            },
        );

        return $emailService;
    }

    // Runs the command and returns the error-report email's body, which embeds the full run report
    private function runAndCaptureReport(array $configOverrides = []): string
    {
        $capturedEmail = null;

        (new CommandTester($this->createCommand($this->createCapturingEmailService($capturedEmail), null, $configOverrides)))->execute([]);

        return $capturedEmail->text;
    }
}
