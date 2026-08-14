<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\UrlMetadataSyncCommand;
use c975L\ConfigBundle\Management\UrlMetadataSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UrlMetadataSyncCommandTest extends TestCase
{
    /** @param array{created: list<string>, orphaned: list<string>, declared: int} $result */
    private function createTester(array $result): CommandTester
    {
        $urlMetadataSynchronizer = $this->createStub(UrlMetadataSynchronizer::class);
        $urlMetadataSynchronizer->method('synchronize')->willReturn($result);

        return new CommandTester(new UrlMetadataSyncCommand($urlMetadataSynchronizer));
    }

    // The urls a release brought in are waiting in the screen rather than typed by hand, so the run names them
    public function testListsTheUrlsItAdded(): void
    {
        $tester = $this->createTester(['created' => ['/animaux', '/caste/guerrier'], 'orphaned' => [], 'declared' => 5]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('5 urls declared, 2 added', $tester->getDisplay());
        $this->assertStringContainsString('/animaux', $tester->getDisplay());
        $this->assertStringContainsString('/caste/guerrier', $tester->getDisplay());
    }

    // It runs at every deployment, so the run adding nothing has to read as a success and not as a doubt
    public function testSaysSoWhenEveryDeclaredUrlIsListedAlready(): void
    {
        $tester = $this->createTester(['created' => [], 'orphaned' => [], 'declared' => 5]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('all of them listed already', $tester->getDisplay());
    }

    // Reported and never deleted: the sentence written for an url is work, and an url can leave a listing for one release and come back
    public function testWarnsAboutTheRowsNoBundleDeclaresAnymore(): void
    {
        $tester = $this->createTester(['created' => [], 'orphaned' => ['/ancienne-liste'], 'declared' => 5]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('no longer declared', $tester->getDisplay());
        $this->assertStringContainsString('/ancienne-liste', $tester->getDisplay());
    }

    // A deployment step whose output nobody reads must not fail on what is only worth a warning
    public function testAnOrphanedRowDoesNotFailTheRun(): void
    {
        $tester = $this->createTester(['created' => ['/animaux'], 'orphaned' => ['/ancienne-liste'], 'declared' => 5]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
