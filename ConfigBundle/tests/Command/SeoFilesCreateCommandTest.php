<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\SeoFilesCreateCommand;
use c975L\ConfigBundle\Management\SeoFilesWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SeoFilesCreateCommandTest extends TestCase
{
    private function createCommandTester(array $files): CommandTester
    {
        $seoFilesWriter = $this->createMock(SeoFilesWriter::class);
        $seoFilesWriter->expects($this->once())->method('write')->willReturn($files);

        return new CommandTester(new SeoFilesCreateCommand($seoFilesWriter));
    }

    // The command is only the console entry point to SeoFilesWriter, so it must report the files the writer actually wrote
    public function testExecuteWritesTheFilesAndListsThem(): void
    {
        $commandTester = $this->createCommandTester(['robots.txt', 'humans.txt', 'llms.txt']);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('robots.txt', $display);
        $this->assertStringContainsString('llms.txt', $display);
    }

    // Nothing to index and nothing configured to say is a valid state, but a silent one would leave the site wondering where its llms.txt went
    public function testExecuteSaysWhyLlmsWasNotWritten(): void
    {
        $commandTester = $this->createCommandTester(['robots.txt', 'humans.txt']);

        $this->assertSame(Command::SUCCESS, $commandTester->execute([]));
        $this->assertStringContainsString('llms.txt was not written', $commandTester->getDisplay());
    }
}
