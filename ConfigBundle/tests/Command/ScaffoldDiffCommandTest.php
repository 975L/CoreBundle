<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\ScaffoldDiffCommand;
use c975L\ConfigBundle\Service\ScaffoldDiffer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldDiffCommandTest extends TestCase
{
    private string | false $columns;

    // SymfonyStyle wraps its output to the terminal's width, which would otherwise decide inside a narrow terminal whether a sentence is asserted whole or cut in two
    protected function setUp(): void
    {
        $this->columns = getenv('COLUMNS');
        putenv('COLUMNS=200');
    }

    protected function tearDown(): void
    {
        false === $this->columns ? putenv('COLUMNS') : putenv('COLUMNS=' . $this->columns);
    }

    private function tester(array $result): CommandTester
    {
        $scaffoldDiffer = $this->createStub(ScaffoldDiffer::class);
        $scaffoldDiffer->method('diff')->willReturn($result);

        return new CommandTester(new ScaffoldDiffCommand($scaffoldDiffer));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReportsASiteWhoseCustomizedFilesHaveNothingToCarryOver(): void
    {
        $tester = $this->tester(['files' => [['file' => 'src/Foo.php', 'source' => 'vendor/c975l/config-bundle/scaffold/src/Foo.php', 'base' => 'this site (abc1234)', 'upstream' => '', 'fallback' => null]], 'unmatched' => []]);

        $statusCode = $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('Nothing to carry over', $display);
        $this->assertStringContainsString('1 customized file(s): 1 with nothing to carry over, 0 the scaffold has moved on from, 0 undecided.', $display);
    }

    // The one case worth a warning, and the diff has to show: a count would say a file is behind without saying what it is missing
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteShowsWhatTheScaffoldGainedAndWarnsAboutIt(): void
    {
        $tester = $this->tester(['files' => [['file' => 'templates/security/login.html.twig', 'source' => 'vendor/c975l/config-bundle/scaffold/templates/security/login.html.twig', 'base' => 'CoreBundle (abc1234)', 'upstream' => "@@ -1,3 +1,4 @@\n+{% set robots = 'noindex, follow' %}\n", 'fallback' => null]], 'unmatched' => []]);

        $statusCode = $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('The scaffold gained this since CoreBundle (abc1234)', $display);
        $this->assertStringContainsString("+{% set robots = 'noindex, follow' %}", $display);
        $this->assertStringContainsString('[WARNING]', $display);
        $this->assertStringContainsString('0 with nothing to carry over, 1 the scaffold has moved on from', $display);
        // The digest an update script keeps is the warning block alone, so the file has to be named inside it
        $this->assertStringContainsString('* templates/security/login.html.twig', $display);
    }

    // An unrecoverable base must never read as a clean bill of health, hence the plain diff rather than a silence
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteFallsBackOnThePlainDiffWhenTheDeliveredVersionIsUnknown(): void
    {
        $tester = $this->tester(['files' => [['file' => 'src/Foo.php', 'source' => 'vendor/c975l/config-bundle/scaffold/src/Foo.php', 'base' => null, 'upstream' => null, 'fallback' => "@@ -1 +1 @@\n-delivered\n+mine\n"]], 'unmatched' => []]);

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Delivered version unrecoverable', $display);
        $this->assertStringContainsString('+mine', $display);
        $this->assertStringContainsString('1 undecided', $display);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReportsASiteWithNoCustomizedScaffoldFileAtAll(): void
    {
        $tester = $this->tester(['files' => [], 'unmatched' => []]);

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('No customized scaffold file', $tester->getDisplay());
    }

    // The way out of a report the site has read and turned down: it names what it moved and says the file itself was left alone, this being the one run of the command that writes anything
    public function testExecuteAcknowledgesTheFilesInsteadOfReportingThem(): void
    {
        $scaffoldDiffer = $this->createMock(ScaffoldDiffer::class);
        $scaffoldDiffer->expects($this->once())->method('acknowledge')->with(['templates/security'])->willReturn(['files' => ['templates/security/login.html.twig'], 'unmatched' => []]);
        $scaffoldDiffer->expects($this->never())->method('diff');
        $tester = new CommandTester(new ScaffoldDiffCommand($scaffoldDiffer));

        $statusCode = $tester->execute(['--acknowledge' => true, '--path' => ['templates/security']]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('* templates/security/login.html.twig', $display);
        $this->assertStringContainsString('1 file(s) acknowledged', $display);
        $this->assertStringContainsString('Nothing was copied', $display);
    }

    // Same stance on the acknowledge branch: a --path naming nothing there wrote nothing either, and saying so with a success is how a typo goes unnoticed
    public function testExecuteFailsWhenAcknowledgingAPathMatchingNoScaffoldFile(): void
    {
        $scaffoldDiffer = $this->createMock(ScaffoldDiffer::class);
        $scaffoldDiffer->expects($this->once())->method('acknowledge')->with(['scaffold/src'])->willReturn(['files' => [], 'unmatched' => ['scaffold/src']]);
        $scaffoldDiffer->expects($this->never())->method('diff');
        $tester = new CommandTester(new ScaffoldDiffCommand($scaffoldDiffer));

        $statusCode = $tester->execute(['--acknowledge' => true, '--path' => ['scaffold/src']]);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('No scaffold file matches: scaffold/src', $tester->getDisplay());
    }

    // A typo reports zero everywhere, which reads exactly like a site with nothing to say - the non-zero exit code is what stops a loop over a dozen sites from scrolling green
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteFailsOnAPathMatchingNoScaffoldFile(): void
    {
        $tester = $this->tester(['files' => [], 'unmatched' => ['src/Nawak']]);

        $statusCode = $tester->execute(['--path' => ['src/Nawak']]);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('No scaffold file matches: src/Nawak', $tester->getDisplay());
    }
}
