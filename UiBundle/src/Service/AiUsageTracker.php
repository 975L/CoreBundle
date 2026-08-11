<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\AiUsage;
use c975L\UiBundle\Repository\AiUsageRepository;
use Doctrine\ORM\EntityManagerInterface;

// Rolls up the "rephrase" feature's token spend into one row per calendar month - see AiUsage. Low enough volume (an editor-triggered action, not a hot path) that a plain find-or-create is fine without guarding against the very unlikely race of two concurrent first-requests-of-the-month
class AiUsageTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AiUsageRepository $aiUsageRepository,
    ) {
    }

    public function record(int $inputTokens, int $outputTokens): void
    {
        $usage = $this->findOrCreateCurrentMonth();
        $usage->addUsage($inputTokens, $outputTokens);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();
    }

    public function recordFailure(string $message): void
    {
        $usage = $this->findOrCreateCurrentMonth();
        $usage->recordFailure($message);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();
    }

    private function findOrCreateCurrentMonth(): AiUsage
    {
        $yearMonth = new \DateTimeImmutable()->format('Y-m');

        return $this->aiUsageRepository->findOneByYearMonth($yearMonth)
            ?? new AiUsage()->setYearMonth($yearMonth);
    }

    public function getCurrentMonth(): ?AiUsage
    {
        return $this->aiUsageRepository->findOneByYearMonth(new \DateTimeImmutable()->format('Y-m'));
    }
}
