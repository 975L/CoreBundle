<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\ScaffoldInstallCommand;
use c975L\ConfigBundle\Service\ScaffoldInstaller;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldInstallCommandTest extends TestCase
{
    private string | false $columns;

    // SymfonyStyle wraps its output to the terminal's width, which would otherwise decide inside a narrow terminal whether a vendor/ path is asserted whole or cut in two
    protected function setUp(): void
    {
        $this->columns = getenv('COLUMNS');
        putenv('COLUMNS=120');
    }

    protected function tearDown(): void
    {
        false === $this->columns ? putenv('COLUMNS') : putenv('COLUMNS=' . $this->columns);
    }

    // ScaffoldInstaller is only ever called once per execute(), no further expectations needed here
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReportsCopiedBackedUpAndSkippedCounts(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn(['copied' => 3, 'backedUp' => 1, 'skipped' => 5, 'files' => [], 'diverged' => [], 'deleted' => [], 'obsolete' => [], 'unmatched' => []]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('3 file(s) copied, 1 backed up into existingFiles/, 5 already up to date.', $tester->getDisplay());
    }

    // Only --force ever writes into existingFiles/, so a run that backed nothing up must not name the directory at all - a site that never forces anything would otherwise read "0 backed up into existingFiles/" for good
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteLeavesTheBackupDirectoryOutOfTheMessageWhenNothingWasBackedUp(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn(['copied' => 3, 'backedUp' => 0, 'skipped' => 5, 'files' => [], 'diverged' => [], 'deleted' => [], 'obsolete' => [], 'unmatched' => []]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('3 file(s) copied, 5 already up to date.', $display);
        $this->assertStringNotContainsString('existingFiles/', $display);
    }

    // themeImportReminder() non-null: the app.css @import still needs to be added by hand
    public function testExecuteDisplaysTheThemeImportReminderWhenPresent(): void
    {
        $scaffoldInstaller = $this->createConfiguredStub(ScaffoldInstaller::class, [
            'install' => ['copied' => 1, 'backedUp' => 0, 'skipped' => 0, 'files' => [], 'diverged' => [], 'deleted' => [], 'obsolete' => [], 'unmatched' => []],
            'themeImportReminder' => 'Add @import url("./themes/site.css"); to assets/styles/app.css.',
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute([]);

        $this->assertStringContainsString('themes/site.css', $tester->getDisplay());
    }

    // A regression silently dropping any of the three options would overwrite files the site diverged from, so the call itself is what is asserted here, the resulting behaviour being ScaffoldInstallerTest's job
    public function testExecutePassesThePathRestrictionAndTheDryRunFlagToTheInstaller(): void
    {
        $scaffoldInstaller = $this->createMock(ScaffoldInstaller::class);
        $scaffoldInstaller
            ->expects($this->once())
            ->method('install')
            ->with(['src/Scheduler', 'tests/Scheduler'], true, false)
            ->willReturn(['copied' => 0, 'backedUp' => 0, 'skipped' => 0, 'files' => [], 'diverged' => [], 'deleted' => [], 'obsolete' => [], 'unmatched' => []])
        ;
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute(['--path' => ['src/Scheduler', 'tests/Scheduler'], '--dry-run' => true]);
    }

    // --force is what overwrites a customized file, so it reaching the installer is worth an assertion of its own
    public function testExecutePassesTheForceFlagToTheInstaller(): void
    {
        $scaffoldInstaller = $this->createMock(ScaffoldInstaller::class);
        $scaffoldInstaller
            ->expects($this->once())
            ->method('install')
            ->with([], false, true)
            ->willReturn(['copied' => 1, 'backedUp' => 1, 'skipped' => 0, 'files' => [], 'diverged' => [], 'deleted' => [], 'obsolete' => [], 'unmatched' => []])
        ;
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute(['--force' => true]);
    }

    // A count would say nothing about which file to go and look at, and these are exactly the ones whose upgrade nobody but their author can do
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteNamesTheCustomizedFilesItLeftAloneWithTheirSource(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn([
            'copied' => 0,
            'backedUp' => 0,
            'skipped' => 0,
            'files' => [],
            'diverged' => ['templates/security/login.html.twig' => 'vendor/c975l/config-bundle/scaffold/templates/security/login.html.twig'],
            'deleted' => [],
            'obsolete' => [],
            'unmatched' => [],
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $statusCode = $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('templates/security/login.html.twig', $display);
        $this->assertStringContainsString('vendor/c975l/config-bundle/scaffold/templates/security/login.html.twig', $display);
        $this->assertStringContainsString('--force', $display);
    }

    // Which files would be overwritten is the whole point of a dry run, a count alone saying nothing
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteListsTheFilesAndStatesNothingWasWrittenOnADryRun(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn([
            'copied' => 2,
            'backedUp' => 1,
            'skipped' => 0,
            'files' => ['src/Scheduler/MaintenanceSchedule.php', 'tests/Scheduler/MaintenanceScheduleTest.php'],
            'diverged' => [],
            'deleted' => [],
            'obsolete' => [],
            'unmatched' => [],
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute(['--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('src/Scheduler/MaintenanceSchedule.php', $display);
        $this->assertStringContainsString('tests/Scheduler/MaintenanceScheduleTest.php', $display);
        $this->assertStringContainsString('Nothing was written.', $display);
    }

    // A deletion is what nobody can undo by re-running the command, so it is named even on a real run - a count alone would leave the developer to find out from a git status
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteNamesEveryFileItDeleted(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn([
            'copied' => 0,
            'backedUp' => 0,
            'skipped' => 0,
            'files' => [],
            'diverged' => [],
            'deleted' => ['src/Security/EmailVerifier.php', 'tests/Security/EmailVerifierTest.php'],
            'obsolete' => [],
            'unmatched' => [],
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('src/Security/EmailVerifier.php', $display);
        $this->assertStringContainsString('tests/Security/EmailVerifierTest.php', $display);
        $this->assertStringContainsString('2 deleted', $display);
    }

    // A withdrawn file the site made its own is left where it is, and the bundle that withdrew it is what points at the UPGRADE.md saying what took its place
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteNamesTheWithdrawnFilesItLeftInPlaceWithTheBundleThatWithdrewThem(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn([
            'copied' => 0,
            'backedUp' => 0,
            'skipped' => 0,
            'files' => [],
            'diverged' => [],
            'deleted' => [],
            'obsolete' => ['src/Form/RegistrationFormType.php' => 'c975LSiteBundle'],
            'unmatched' => [],
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $statusCode = $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('src/Form/RegistrationFormType.php', $display);
        $this->assertStringContainsString('c975LSiteBundle', $display);
        $this->assertStringContainsString('UPGRADE.md', $display);
        // Nothing was deleted, so the count must stay out of the closing message
        $this->assertStringNotContainsString('0 deleted', $display);
    }

    // Zero counts and a green success are what a propagation scripted over a dozen sites would show on a typo'd path, so the run has to fail out loud instead
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteFailsWhenAPathMatchedNoScaffoldFile(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn([
            'copied' => 0,
            'backedUp' => 0,
            'skipped' => 0,
            'files' => [],
            'diverged' => [],
            'deleted' => [],
            'obsolete' => [],
            'unmatched' => ['src/Sheduler'],
        ]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $statusCode = $tester->execute(['--path' => ['src/Sheduler']]);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('src/Sheduler', $tester->getDisplay());
    }
}
