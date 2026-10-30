<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\SitemapCreateCommand;
use c975L\ConfigBundle\Management\IndexNowNotifier;
use c975L\ConfigBundle\Management\SitemapWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SitemapCreateCommandTest extends TestCase
{
    private function createCommandTester(array $names, ?IndexNowNotifier $indexNowNotifier = null): CommandTester
    {
        $sitemapWriter = $this->createMock(SitemapWriter::class);
        $sitemapWriter->expects($this->once())->method('write')->willReturn($names);
        $sitemapWriter->method('getDeclaredUrls')->willReturn(['https://example.com/' => null]);

        return new CommandTester(new SitemapCreateCommand($sitemapWriter, $indexNowNotifier ?? $this->createStub(IndexNowNotifier::class)));
    }

    // The command is only the console entry point to SitemapWriter, so it must report the names the writer actually wrote
    public function testExecuteWritesSitemapsAndListsThem(): void
    {
        $commandTester = $this->createCommandTester(['site', 'book']);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('site', $display);
        $this->assertStringContainsString('book', $display);
    }

    // No provider having anything to declare is a valid state (a brand new site), so it's a warning and not a failure
    public function testExecuteWarnsWhenNothingWasWritten(): void
    {
        $commandTester = $this->createCommandTester([]);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('No SitemapProvider', $commandTester->getDisplay());
    }

    // The urls the writer just declared are what IndexNow is handed, and how many went is reported
    public function testExecuteSubmitsTheDeclaredUrlsToIndexNow(): void
    {
        $indexNowNotifier = $this->createMock(IndexNowNotifier::class);
        $indexNowNotifier->expects($this->once())->method('notify')->with(['https://example.com/' => null])->willReturn(1);

        $commandTester = $this->createCommandTester(['site'], $indexNowNotifier);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('1 url(s) submitted', $commandTester->getDisplay());
    }

    // IndexNow being down must not fail the nightly run: the sitemaps are written, and a console error in production raises an alert
    public function testExecuteOnlyWarnsWhenIndexNowRefuses(): void
    {
        $indexNowNotifier = $this->createStub(IndexNowNotifier::class);
        $indexNowNotifier->method('notify')->willThrowException(new \RuntimeException('IndexNow answered 429.'));

        $commandTester = $this->createCommandTester(['site'], $indexNowNotifier);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('IndexNow answered 429.', $commandTester->getDisplay());
        $this->assertStringContainsString('Sitemaps created', $commandTester->getDisplay());
    }
}
