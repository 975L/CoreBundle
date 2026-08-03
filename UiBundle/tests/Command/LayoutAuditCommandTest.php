<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\LayoutAuditCommand;
use c975L\UiBundle\Service\LayoutAuditor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// The auditor is stubbed throughout: driving a real browser here is exactly the flakiness this command is built to stay out of
class LayoutAuditCommandTest extends TestCase
{
    public function testItReportsWhatTheAuditorFound(): void
    {
        $tester = $this->tester([[
            'width' => 1280,
            'check' => 'centering',
            'selector' => 'section.slider',
            'summary' => 'sits 0px from the left and 486px from the right',
        ]]);

        $tester->execute(['urls' => ['https://example.com']]);
        $output = $tester->getDisplay();

        $this->assertStringContainsString('centering', $output);
        $this->assertStringContainsString('section.slider', $output);
        $this->assertStringContainsString('1280px', $output);
    }

    // The whole point of the command: a browser is never deterministic enough to stop a push on
    public function testItSucceedsOnFindingsUnlessStrictIsAsked(): void
    {
        $findings = [['width' => 1280, 'check' => 'overflow', 'selector' => 'div', 'summary' => 'past the viewport']];

        $tester = $this->tester($findings);
        $this->assertSame(Command::SUCCESS, $tester->execute(['urls' => ['https://example.com']]));

        $tester = $this->tester($findings);
        $this->assertSame(Command::FAILURE, $tester->execute(['urls' => ['https://example.com'], '--strict' => true]));
    }

    public function testACleanPageSucceedsEvenInStrictMode(): void
    {
        $tester = $this->tester([]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['urls' => ['https://example.com'], '--strict' => true]));
        $this->assertStringContainsString('Aucun écart', $tester->getDisplay());
    }

    // A browser that would not start is a broken tool, not a broken page, and must never read as a clean run either
    public function testAnUnreachablePageIsReportedWithoutStoppingTheRun(): void
    {
        $tester = $this->tester(new \RuntimeException('Chrome did not start'));

        $this->assertSame(Command::SUCCESS, $tester->execute(['urls' => ['https://example.com'], '--strict' => true]));
        $this->assertStringContainsString('Chrome did not start', $tester->getDisplay());
        $this->assertStringNotContainsString('Aucun écart', $tester->getDisplay());
    }

    public function testEveryUrlGivenIsMeasured(): void
    {
        $auditor = $this->auditor([]);
        $tester = $this->testerFor($auditor);

        $tester->execute(['urls' => ['https://example.com/a', 'https://example.com/b']]);

        $this->assertSame(['https://example.com/a', 'https://example.com/b'], $auditor->audited);
    }

    public function testTheDefaultWidthsCoverAMobileAndADesktopViewport(): void
    {
        $auditor = $this->auditor([]);
        $this->testerFor($auditor)->execute(['urls' => ['https://example.com']]);

        $this->assertLessThan(621, min($auditor->widths), 'No width below the first breakpoint is measured.');
        $this->assertGreaterThan(1025, max($auditor->widths), 'No width above the last breakpoint is measured.');
    }

    private function tester(array | \Throwable $result): CommandTester
    {
        return $this->testerFor($this->auditor($result));
    }

    private function testerFor(LayoutAuditor $auditor): CommandTester
    {
        return new CommandTester(new LayoutAuditCommand($auditor));
    }

    private function auditor(array | \Throwable $result): LayoutAuditor
    {
        return new class ($result) extends LayoutAuditor {
            public array $audited = [];
            public array $widths = [];

            public function __construct(private readonly array | \Throwable $result)
            {
                parent::__construct();
            }

            public function audit(string $url, array $widths = LayoutAuditor::DEFAULT_WIDTHS): array
            {
                $this->audited[] = $url;
                $this->widths = $widths;

                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }

                return $this->result;
            }
        };
    }
}
