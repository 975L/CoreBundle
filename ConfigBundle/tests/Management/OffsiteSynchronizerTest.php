<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\OffsiteSynchronizer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class OffsiteSynchronizerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-offsite-sync-test-' . uniqid();
        mkdir($this->projectDir . '/var', 0775, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function createSynchronizer(?string $target): OffsiteSynchronizer
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-backup-offsite-target' === $key ? $target : null
        );

        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturn($this->projectDir);

        return new OffsiteSynchronizer($configService, $bag);
    }

    // The mode of every install that has an outside machine pull instead of pushing: nothing is sent, and that is a configuration, not a failure
    public function testAnEmptyTargetIsNotConfigured(): void
    {
        $synchronizer = $this->createSynchronizer('');

        $this->assertFalse($synchronizer->isConfigured());
        $this->assertStringContainsString('not configured', $synchronizer->getUnavailabilityReason());
    }

    // The entry is editable from the back-office, so what it holds reaches a Process: anything that isn't a plain rclone target is refused before that, an admin account being one compromise away from arbitrary arguments
    #[DataProvider('provideInvalidTargets')]
    public function testATargetThatIsNotAPlainRcloneRemoteIsRefused(string $target): void
    {
        $this->assertStringContainsString(
            'not a valid rclone target',
            (string) $this->createSynchronizer($target)->getUnavailabilityReason(),
        );
    }

    public static function provideInvalidTargets(): iterable
    {
        yield 'no remote' => ['/etc/passwd'];
        yield 'option smuggled in' => ['--config=/tmp/evil.conf'];
        yield 'shell metacharacters' => ['remote:path; rm -rf /'];
        yield 'space separated argument' => ['remote:path --delete-during'];
        yield 'command substitution' => ['remote:$(whoami)'];
    }

    // What a working install holds, which must get past the validation and fail - if it fails at all - only on rclone being absent
    public function testAPlainRemoteAndPathPassesValidation(): void
    {
        $reason = $this->createSynchronizer('storagebox:example.com')->getUnavailabilityReason();

        $this->assertTrue(null === $reason || str_contains($reason, 'rclone was not found'));
    }

    // A trailing slash is what anyone types, and would otherwise produce "remote:path//backup" on every sub-path
    public function testATrailingSlashIsDroppedFromTheTarget(): void
    {
        $this->assertSame('storagebox:example.com', $this->createSynchronizer('storagebox:example.com/')->getTarget());
    }

    // Nothing to impose when the install keeps its remotes where rclone looks by default
    public function testNoConfigFileIsPassedWhenTheProjectHasNone(): void
    {
        $this->assertNull($this->createSynchronizer('storagebox:example.com')->getConfigFile());
    }

    // The failure this guards against: rclone resolves HOME, which an interactive SSH session has and a task scheduler often hasn't, and starts with no remote configured at all - reported as an unknown target, which reads exactly like "rclone doesn't work on this host"
    public function testTheProjectsOwnConfigFileIsUsedWhenPresent(): void
    {
        touch($this->projectDir . '/rclone.conf');

        $this->assertSame(
            $this->projectDir . '/rclone.conf',
            $this->createSynchronizer('storagebox:example.com')->getConfigFile(),
        );
    }

    // var/ is a runtime scratch folder, and the local backup scripts that skip it would skip this file too - a copy left there after the move must not silently keep working, or the two locations diverge without anyone noticing
    public function testAConfigFileLeftUnderVarIsNotPickedUp(): void
    {
        touch($this->projectDir . '/var/rclone.conf');

        $this->assertNull($this->createSynchronizer('storagebox:example.com')->getConfigFile());
    }

    // The first run of every install: nothing has been overwritten yet, so there is no previous/ folder to purge and rclone says so as an error. Reported as a failure it warns that first night and then every night a site's files don't change, which is how the warning that matters goes unread
    public function testAMissingPreviousFolderIsNothingToPurgeRatherThanAFailure(): void
    {
        $result = $this->createSynchronizerReturning(
            ['ok' => false, 'error' => 'ERROR : error listing: directory not found', 'output' => '']
        )->purgeBackupDirs('previous', 15);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    // The tolerance is that one message and nothing more: credentials rclone won't take, or a destination out of space, are exactly what the run must still report
    public function testAnyOtherPurgeFailureIsStillReported(): void
    {
        $result = $this->createSynchronizerReturning(
            ['ok' => false, 'error' => 'ERROR : couldn\'t connect SSH: unable to authenticate', 'output' => '']
        )->purgeBackupDirs('previous', 15);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('unable to authenticate', $result['error']);
    }

    // What rclone is asked, checked once here: the purge is aimed at the dated folders under the target and bounded by the retention window, a --min-age dropped along the way deleting the whole history instead of its oldest part
    public function testThePurgeIsBoundedByTheRetentionWindow(): void
    {
        $captured = new \ArrayObject();
        $this->createSynchronizerReturning(['ok' => true, 'error' => null, 'output' => ''], $captured)
            ->purgeBackupDirs('previous', 15);

        $this->assertSame(
            ['delete', '--rmdirs', '--min-age', '15d', 'storagebox:example.com/previous'],
            $captured['arguments'],
        );
    }

    // Overriding the run rather than putting a fake binary on the PATH: what is under test is what this class makes of an exit code and a message, and a host that happens to have a real rclone would otherwise answer instead
    private function createSynchronizerReturning(array $result, ?\ArrayObject $captured = null): OffsiteSynchronizer
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-backup-offsite-target' === $key ? 'storagebox:example.com' : null
        );

        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturn($this->projectDir);

        return new class ($configService, $bag, $result, $captured ?? new \ArrayObject()) extends OffsiteSynchronizer {
            public function __construct(
                ConfigServiceInterface $configService,
                ParameterBagInterface $parameterBag,
                private readonly array $result,
                private readonly \ArrayObject $captured,
            ) {
                parent::__construct($configService, $parameterBag);
            }

            protected function run(array $arguments, int $timeout): array
            {
                $this->captured['arguments'] = $arguments;
                $this->captured['timeout'] = $timeout;

                return $this->result;
            }
        };
    }
}
