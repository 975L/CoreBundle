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
        (new Filesystem())->remove($this->projectDir);
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
        $reason = $this->createSynchronizer('storagebox:975l.com')->getUnavailabilityReason();

        $this->assertTrue(null === $reason || str_contains($reason, 'rclone was not found'));
    }

    // A trailing slash is what anyone types, and would otherwise produce "remote:path//backup" on every sub-path
    public function testATrailingSlashIsDroppedFromTheTarget(): void
    {
        $this->assertSame('storagebox:975l.com', $this->createSynchronizer('storagebox:975l.com/')->getTarget());
    }

    // Nothing to impose when the install keeps its remotes where rclone looks by default
    public function testNoConfigFileIsPassedWhenTheProjectHasNone(): void
    {
        $this->assertNull($this->createSynchronizer('storagebox:975l.com')->getConfigFile());
    }

    // The failure this guards against: rclone resolves HOME, which an interactive SSH session has and a task
    // scheduler often hasn't, and starts with no remote configured at all - reported as an unknown target, which
    // reads exactly like "rclone doesn't work on this host"
    public function testTheProjectsOwnConfigFileIsUsedWhenPresent(): void
    {
        touch($this->projectDir . '/rclone.conf');

        $this->assertSame(
            $this->projectDir . '/rclone.conf',
            $this->createSynchronizer('storagebox:975l.com')->getConfigFile(),
        );
    }

    // var/ is a runtime scratch folder, and the local backup scripts that skip it would skip this file too - a copy
    // left there after the move must not silently keep working, or the two locations diverge without anyone noticing
    public function testAConfigFileLeftUnderVarIsNotPickedUp(): void
    {
        touch($this->projectDir . '/var/rclone.conf');

        $this->assertNull($this->createSynchronizer('storagebox:975l.com')->getConfigFile());
    }
}
