<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\SkillsInstallCommand;
use c975L\ConfigBundle\Service\SkillsInstaller;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SkillsInstallCommandTest extends TestCase
{
    private string | false $columns;

    // SymfonyStyle wraps its output to the terminal's width, which would otherwise decide inside a narrow terminal whether a skill path is asserted whole or cut in two
    protected function setUp(): void
    {
        $this->columns = getenv('COLUMNS');
        putenv('COLUMNS=120');
    }

    protected function tearDown(): void
    {
        false === $this->columns ? putenv('COLUMNS') : putenv('COLUMNS=' . $this->columns);
    }

    private function tester(array $result): CommandTester
    {
        $skillsInstaller = $this->createStub(SkillsInstaller::class);
        $skillsInstaller->method('install')->willReturn($result + ['linked' => [], 'skipped' => 0, 'kept' => [], 'pruned' => []]);

        return new CommandTester(new SkillsInstallCommand($skillsInstaller));
    }

    // SkillsInstaller is only ever called once per execute(), no further expectations needed here
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteNamesTheSkillsItLinked(): void
    {
        $tester = $this->tester(['linked' => ['.claude/skills/c975l-config'], 'skipped' => 4]);

        $statusCode = $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('.claude/skills/c975l-config', $display);
        $this->assertStringContainsString('1 skill(s) linked, 4 already linked.', $display);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteWarnsAboutTheSkillsItLeftUntouched(): void
    {
        $tester = $this->tester(['kept' => ['.claude/skills/c975l-blocks']]);

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('1 skill(s) left untouched', $display);
        $this->assertStringContainsString('.claude/skills/c975l-blocks', $display);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteNamesTheDeadLinksItDeleted(): void
    {
        $tester = $this->tester(['pruned' => ['.claude/skills/c975l-gone']]);

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Dead links deleted', $display);
        $this->assertStringContainsString('.claude/skills/c975l-gone', $display);
    }

    // A dry run says what it would do, and says it wrote nothing - a site reading "linked" would otherwise believe it did
    #[AllowMockObjectsWithoutExpectations]
    public function testADryRunSaysNothingWasWritten(): void
    {
        $tester = $this->tester(['linked' => ['.claude/skills/c975l-config']]);

        $tester->execute(['--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('To link:', $display);
        $this->assertStringContainsString('Nothing was written.', $display);
    }
}
