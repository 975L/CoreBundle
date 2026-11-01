<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\ReviewRatingsRekeyCommand;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ReviewRatingsRekeyCommandTest extends TestCase
{
    private function review(string $email, int $ownerId = 12): Review
    {
        return new Review()->setOwnerType('book')->setOwnerId($ownerId)->setAuthorEmail($email);
    }

    /** @param Review[] $reviews */
    private function tester(array $reviews, ReviewService $service): CommandTester
    {
        return new CommandTester(new ReviewRatingsRekeyCommand(
            $this->createConfiguredStub(ReviewRepository::class, ['findPublishedUnverifiedRated' => $reviews]),
            $service,
        ));
    }

    // The service deals with an address and an owner at once, whatever its case: a second review of the pair is not asked about, another owner is
    public function testEachAddressOfAnOwnerIsAskedOnce(): void
    {
        $first = $this->review('jean@example.org');
        $otherOwner = $this->review('jean@example.org', 13);

        $service = $this->createMock(ReviewService::class);
        $service->expects($this->exactly(2))->method('rekeyLegacyRating')->willReturnCallback(function (Review $review, bool $dryRun) use ($first, $otherOwner): int {
            $this->assertContains($review, [$first, $otherOwner]);
            $this->assertFalse($dryRun);

            return 1;
        });

        $tester = $this->tester([$first, $this->review(' JEAN@example.org'), $otherOwner], $service);

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('2 review(s) got', $tester->getDisplay());
    }

    public function testADryRunSaysItWritesNothing(): void
    {
        $service = $this->createMock(ReviewService::class);
        $service->expects($this->once())->method('rekeyLegacyRating')->with($this->anything(), true)->willReturn(3);

        $tester = $this->tester([$this->review('jean@example.org')], $service);

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('3 review(s) would get', $tester->getDisplay());
    }

    public function testNothingToMoveStillSucceeds(): void
    {
        $service = $this->createMock(ReviewService::class);
        $service->expects($this->never())->method('rekeyLegacyRating');

        $this->assertSame(0, $this->tester([], $service)->execute([]));
    }
}
