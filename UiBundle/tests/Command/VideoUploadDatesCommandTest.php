<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\VideoUploadDatesCommand;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\VideoUploadDateFetcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class VideoUploadDatesCommandTest extends TestCase
{
    private function createBlock(array $data): Block
    {
        return new Block()->setKind('video_iframe')->setData($data);
    }

    private function createTester(array $blocks, array $dates, EntityManagerInterface $entityManager): CommandTester
    {
        $repository = $this->createStub(BlockRepository::class);
        $repository->method('findByKind')->willReturn($blocks);

        $fetcher = $this->createStub(VideoUploadDateFetcher::class);
        $fetcher->method('fetch')->willReturnCallback(static fn (?string $url): ?string => $dates[$url] ?? null);

        return new CommandTester(new VideoUploadDatesCommand($repository, $fetcher, $entityManager));
    }

    public function testExecuteFillsTheDateAskedFromThePlatform(): void
    {
        $block = $this->createBlock(['src' => 'https://youtu.be/abc']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester([$block], ['https://youtu.be/abc' => '2024-05-12'], $entityManager);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('2024-05-12', $block->getData()['uploadDate']);
        $this->assertStringContainsString('1 vidéo(s) datée(s) sur 1', $tester->getDisplay());
    }

    // A date typed by hand is left alone, never even asked for
    public function testExecuteSkipsABlockAlreadyDated(): void
    {
        $block = $this->createBlock(['src' => 'https://youtu.be/abc', 'uploadDate' => '2020-01-01']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->createTester([$block], ['https://youtu.be/abc' => '2024-05-12'], $entityManager);
        $tester->execute([]);

        $this->assertSame('2020-01-01', $block->getData()['uploadDate']);
        $this->assertStringContainsString('0 vidéo(s) datée(s) sur 0', $tester->getDisplay());
    }

    // A platform giving no date leaves the block untouched and names it, for the editor to type the date
    public function testExecuteReportsAVideoItCouldNotDate(): void
    {
        $block = $this->createBlock(['src' => 'https://www.tiktok.com/@a/video/1']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->createTester([$block], [], $entityManager);
        $tester->execute([]);

        $this->assertArrayNotHasKey('uploadDate', $block->getData());
        $this->assertStringContainsString('https://www.tiktok.com/@a/video/1', $tester->getDisplay());
    }
}
