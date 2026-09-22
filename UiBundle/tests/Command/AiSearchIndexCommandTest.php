<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\AiSearchIndexCommand;
use c975L\UiBundle\Service\AiSearchIndexer;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\AiSiteSearchClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// The purge keeps the privacy policy's promise whatever the state, the index being read only on a site whose search is configured
class AiSearchIndexCommandTest extends TestCase
{
    public function testASearchSwitchedOffStillPurgesAndIndexesNothing(): void
    {
        $indexer = $this->createMock(AiSearchIndexer::class);
        $indexer->expects($this->never())->method('index');

        $search = $this->createMock(AiSiteSearch::class);
        $search->expects($this->once())->method('purge')->willReturn(3);

        $tester = $this->tester(false, $indexer, $search);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('3 question(s) purgée(s)', $tester->getDisplay());
    }

    public function testAConfiguredSearchPurgesThenIndexes(): void
    {
        $indexer = $this->createMock(AiSearchIndexer::class);
        $indexer->expects($this->once())->method('index')->willReturn(['pages' => 2, 'chunks' => 5, 'changed' => true]);

        $search = $this->createMock(AiSiteSearch::class);
        $search->expects($this->once())->method('purge')->willReturn(0);

        $tester = $this->tester(true, $indexer, $search);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('2 page(s), 5 passage(s)', $tester->getDisplay());
    }

    // A run reading no page keeps the index it had, and says so
    public function testARunReadingNoPageWarns(): void
    {
        $indexer = $this->createStub(AiSearchIndexer::class);
        $indexer->method('index')->willReturn(['pages' => 0, 'chunks' => 0, 'changed' => false]);

        $tester = $this->tester(true, $indexer, $this->createStub(AiSiteSearch::class));
        $tester->execute([]);

        $this->assertStringContainsString('Aucune page lue', $tester->getDisplay());
    }

    private function tester(bool $enabled, AiSearchIndexer $indexer, AiSiteSearch $search): CommandTester
    {
        $client = $this->createStub(AiSiteSearchClient::class);
        $client->method('isEnabled')->willReturn($enabled);

        return new CommandTester(new AiSearchIndexCommand($client, $indexer, $search));
    }
}
