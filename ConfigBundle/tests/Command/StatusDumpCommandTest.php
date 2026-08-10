<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\StatusDumpCommand;
use c975L\ConfigBundle\Management\StatusReportBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StatusDumpCommandTest extends TestCase
{
    private const REPORT = ['version' => 1, 'site' => 'https://papa-calin.com', 'packages' => ['c975l/core-bundle' => 'v1.4.3']];

    private function createTester(): CommandTester
    {
        $statusReportBuilder = $this->createStub(StatusReportBuilder::class);
        $statusReportBuilder->method('build')->willReturn(self::REPORT);

        return new CommandTester(new StatusDumpCommand($statusReportBuilder));
    }

    // The only way to see exactly what a console would be served, and it must need no key and no network
    public function testPrintsTheReportAsJson(): void
    {
        $tester = $this->createTester();

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(self::REPORT, json_decode($tester->getDisplay(), true));
    }

    // Meant to be piped into a file or jq, which a decorated output would break
    public function testTheOutputIsNothingButJson(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        $this->assertStringStartsWith('{', trim($tester->getDisplay()));
        $this->assertStringEndsWith('}', trim($tester->getDisplay()));
    }

    // Slashes unescaped: the report is read by a human as often as by a console, and "https:\/\/" is not
    public function testUrlsAreNotEscaped(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        $this->assertStringContainsString('https://papa-calin.com', $tester->getDisplay());
    }
}
