<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\SeoCrawlersUpdateCommand;
use c975L\ConfigBundle\Management\AiCrawlerListUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SeoCrawlersUpdateCommandTest extends TestCase
{
    private function createUpdater(array $comparison): AiCrawlerListUpdater
    {
        $updater = $this->createMock(AiCrawlerListUpdater::class);
        $updater->method('compare')->willReturn($comparison + ['missing' => [], 'answerEngines' => [], 'source' => 'https://example.com/robots.json']);

        return $updater;
    }

    public function testExecuteAddsWhatAppearedUpstreamAndNamesIt(): void
    {
        $updater = $this->createUpdater(['missing' => ['NewScraperBot']]);
        $updater->expects($this->once())->method('apply')->with(['NewScraperBot'])->willReturn(['GPTBot', 'NewScraperBot']);

        $commandTester = new CommandTester(new SeoCrawlersUpdateCommand($updater));

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('NewScraperBot', $commandTester->getDisplay());
    }

    // Named one by one rather than counted: this is the list deciding what the site stops serving, and it is meant to be read
    public function testExecuteNamesTheAnswerEnginesItLeavesOut(): void
    {
        $updater = $this->createUpdater(['answerEngines' => ['Claude-User']]);
        $updater->expects($this->never())->method('apply');

        $commandTester = new CommandTester(new SeoCrawlersUpdateCommand($updater));

        $commandTester->execute([]);

        $this->assertStringContainsString('Claude-User', $commandTester->getDisplay());
    }

    public function testExecuteWritesNothingWhenTheListIsUpToDate(): void
    {
        $updater = $this->createUpdater([]);
        $updater->expects($this->never())->method('apply');

        $commandTester = new CommandTester(new SeoCrawlersUpdateCommand($updater));

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('up to date', $commandTester->getDisplay());
    }

    public function testDryRunListsWhatWouldBeAddedAndWritesNothing(): void
    {
        $updater = $this->createUpdater(['missing' => ['NewScraperBot']]);
        $updater->expects($this->never())->method('apply');

        $commandTester = new CommandTester(new SeoCrawlersUpdateCommand($updater));

        $this->assertSame(Command::SUCCESS, $commandTester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('NewScraperBot', $commandTester->getDisplay());
        $this->assertStringContainsString('Nothing was written', $commandTester->getDisplay());
    }

    // An empty source is how a site keeps its list by hand
    public function testExecuteSaysNothingToDoWithoutASource(): void
    {
        $updater = $this->createUpdater(['source' => null]);
        $updater->expects($this->never())->method('apply');

        $commandTester = new CommandTester(new SeoCrawlersUpdateCommand($updater));

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('keeps its list by hand', $commandTester->getDisplay());
    }
}
