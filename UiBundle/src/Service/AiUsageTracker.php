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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

// Rolls up each AI feature's token spend into one row per feature and calendar month - see AiUsage. The feature defaults to the rephrase, the one caller this had before the site search. The site search being public, two first-requests-of-the-month may race on the month's row: the second one's count is dropped rather than failing the visitor's request
class AiUsageTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AiUsageRepository $aiUsageRepository,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function record(int $inputTokens, int $outputTokens, string $feature = AiUsage::FEATURE_REPHRASE): void
    {
        $usage = $this->findOrCreateCurrentMonth($feature);
        $usage->addUsage($inputTokens, $outputTokens);

        $this->save($usage);
    }

    public function recordFailure(string $message, string $feature = AiUsage::FEATURE_REPHRASE): void
    {
        $usage = $this->findOrCreateCurrentMonth($feature);
        $usage->recordFailure($message);

        $this->save($usage);
    }

    // A lost race on the month's row leaves the manager closed, reset so the rest of the request can still write
    private function save(AiUsage $usage): void
    {
        try {
            $this->entityManager->persist($usage);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->managerRegistry->resetManager();
        }
    }

    private function findOrCreateCurrentMonth(string $feature): AiUsage
    {
        $yearMonth = new \DateTimeImmutable()->format('Y-m');

        return $this->aiUsageRepository->findOneByYearMonth($yearMonth, $feature)
            ?? new AiUsage()->setYearMonth($yearMonth)->setFeature($feature);
    }

    public function getCurrentMonth(string $feature = AiUsage::FEATURE_REPHRASE): ?AiUsage
    {
        return $this->aiUsageRepository->findOneByYearMonth(new \DateTimeImmutable()->format('Y-m'), $feature);
    }
}
