<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\BackupOffsiteCommand;
use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathCollector;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\ConfigBundle\Management\OffsiteState;
use c975L\ConfigBundle\Management\OffsiteSynchronizer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class BackupOffsiteCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-backup-offsite-test-' . uniqid();
        mkdir($this->projectDir, 0775, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function createCommand(string $target = '', array $paths = [], ?\ArrayObject $calls = null): BackupOffsiteCommand
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturn($this->projectDir);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-backup-offsite-target' === $key ? $target : null
        );

        $provider = new readonly class ($paths) implements BackupPathProviderInterface {
            public function __construct(private array $paths)
            {
            }

            public function getBackupPaths(): array
            {
                return $this->paths;
            }
        };

        return new BackupOffsiteCommand(
            $bag,
            $configService,
            new BackupPathCollector([$provider], $bag),
            null === $calls
                ? new OffsiteSynchronizer($configService, $bag)
                : $this->createRecordingSynchronizer($configService, $bag, $calls),
            new OffsiteState(),
        );
    }

    // Records what rclone would have been asked instead of asking it: what is under test is the arguments this command builds, and a host that happens to have a real rclone would otherwise try to reach a Storage Box
    private function createRecordingSynchronizer(
        ConfigServiceInterface $configService,
        ParameterBagInterface $parameterBag,
        \ArrayObject $calls,
    ): OffsiteSynchronizer {
        return new class ($configService, $parameterBag, $calls) extends OffsiteSynchronizer {
            public function __construct(
                ConfigServiceInterface $configService,
                ParameterBagInterface $parameterBag,
                private readonly \ArrayObject $calls,
            ) {
                parent::__construct($configService, $parameterBag);
            }

            protected function run(array $arguments, int $timeout): array
            {
                $this->calls->append(implode(' ', $arguments));

                return ['ok' => true, 'error' => null, 'output' => '{"count":1,"bytes":2}'];
            }

            // The binary is never run here, but the command refuses to send anything when it can't be found - so a host without rclone, CI being one, would see this test assert on a command that was never built
            protected function findRclone(): ?string
            {
                return '/usr/bin/rclone';
            }
        };
    }

    // The pull model: an outside machine fetches the backups, then says so - without it, a site that is properly backed up would be reported as never having sent anything anywhere
    public function testAckRecordsTheCopyWithoutTransferringAnything(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--ack' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $state = new OffsiteState()->read($this->projectDir);
        $this->assertSame('ok', $state['status']);
        $this->assertSame('pulled', $state['what']);
    }

    // An install that deliberately pushes nothing must not have its scheduler report a failed task every night: the staleness alert is what speaks for it, and it speaks once rather than nightly
    public function testAnUnconfiguredTargetWarnsRatherThanFails(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('not configured', $tester->getDisplay());
    }

    // Nothing declared for mirroring is a normal state - an install may keep every irreplaceable file in "archive" mode - and must not be dressed up as an error
    public function testNoDeclaredFolderWarnsRatherThanFails(): void
    {
        $tester = new CommandTester($this->createCommand('storagebox:975l.com'));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // Whatever the back-office holds reaches a Process, so a target that isn't a plain rclone remote stops the run before rclone is ever invoked
    public function testAnInvalidTargetStopsBeforeAnyTransfer(): void
    {
        mkdir($this->projectDir . '/public/medias', 0775, true);

        $tester = new CommandTester($this->createCommand('remote:path; rm -rf /', [new BackupPath('public/medias', BackupPath::MODE_MIRROR)]));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        // The console wraps its warning block, so only the head of the message is matched here
        $this->assertStringContainsString('is not a valid', $tester->getDisplay());
        $this->assertNull(new OffsiteState()->read($this->projectDir));
    }

    // The folder --backup-dir fills and the folder the purge empties have to be the same one. Renaming one and leaving the other aims the purge at a folder that doesn't exist: the previous versions then pile up offsite for good, while every run goes on reporting success - which is why the name is asserted here rather than read twice
    public function testTheBackupDirAndThePurgeNameTheSameFolder(): void
    {
        mkdir($this->projectDir . '/public/medias', 0775, true);
        $calls = new \ArrayObject();

        $tester = new CommandTester($this->createCommand(
            'storagebox:975l.com',
            [new BackupPath('public/medias', BackupPath::MODE_MIRROR)],
            $calls
        ));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains(
            sprintf(
                'sync %s/public/medias storagebox:975l.com/files/public/medias --backup-dir storagebox:975l.com/previous/%s/public/medias --max-delete 100',
                $this->projectDir,
                new \DateTimeImmutable()->format('Y-m-d'),
            ),
            (array) $calls,
        );
        $this->assertContains('delete --rmdirs --min-age 15d storagebox:975l.com/previous', (array) $calls);
    }
}
