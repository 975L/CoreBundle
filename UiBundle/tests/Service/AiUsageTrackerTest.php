<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\AiUsage;
use c975L\UiBundle\Repository\AiUsageRepository;
use c975L\UiBundle\Service\AiUsageTracker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AiUsageTrackerTest extends TestCase
{
    public function testRecordCreatesNewRowForCurrentMonth(): void
    {
        $repository = $this->createStub(AiUsageRepository::class);
        $repository->method('findOneByYearMonth')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(AiUsage::class));
        $entityManager->expects($this->once())->method('flush');

        $tracker = new AiUsageTracker($entityManager, $repository);
        $tracker->record(42, 17);
    }

    public function testRecordAccumulatesOnExistingRow(): void
    {
        $existing = new AiUsage()->setYearMonth(new \DateTimeImmutable()->format('Y-m'));
        $existing->addUsage(10, 5);

        $repository = $this->createStub(AiUsageRepository::class);
        $repository->method('findOneByYearMonth')->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($existing);
        $entityManager->expects($this->once())->method('flush');

        $tracker = new AiUsageTracker($entityManager, $repository);
        $tracker->record(3, 2);

        $this->assertSame(13, $existing->getInputTokens());
        $this->assertSame(7, $existing->getOutputTokens());
        $this->assertSame(2, $existing->getRequestCount());
    }

    public function testGetCurrentMonthReturnsRepositoryResult(): void
    {
        $usage = new AiUsage()->setYearMonth(new \DateTimeImmutable()->format('Y-m'));

        $repository = $this->createStub(AiUsageRepository::class);
        $repository->method('findOneByYearMonth')->willReturn($usage);

        $tracker = new AiUsageTracker($this->createStub(EntityManagerInterface::class), $repository);

        $this->assertSame($usage, $tracker->getCurrentMonth());
    }

    public function testRecordFailureSetsFailureStateOnCurrentMonth(): void
    {
        $repository = $this->createStub(AiUsageRepository::class);
        $repository->method('findOneByYearMonth')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(
            function (AiUsage $usage): bool {
                $this->assertNotNull($usage->getLastFailureAt());
                $this->assertSame('HTTP 401 returned', $usage->getLastFailureMessage());

                return true;
            }
        ));
        $entityManager->expects($this->once())->method('flush');

        $tracker = new AiUsageTracker($entityManager, $repository);
        $tracker->recordFailure('HTTP 401 returned');
    }

    public function testRecordAfterFailureClearsIt(): void
    {
        $existing = new AiUsage()->setYearMonth(new \DateTimeImmutable()->format('Y-m'));
        $existing->recordFailure('HTTP 401 returned');

        $repository = $this->createStub(AiUsageRepository::class);
        $repository->method('findOneByYearMonth')->willReturn($existing);

        $tracker = new AiUsageTracker($this->createStub(EntityManagerInterface::class), $repository);
        $tracker->record(5, 5);

        $this->assertNull($existing->getLastFailureAt());
        $this->assertNull($existing->getLastFailureMessage());
    }
}
