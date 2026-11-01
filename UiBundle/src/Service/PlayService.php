<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Play;
use c975L\UiBundle\Repository\PlayRepository;
use c975L\UiBundle\Repository\PlayTotalRepository;
use Doctrine\ORM\EntityManagerInterface;

// What a visitor listened to or watched to its end, counted under the key their favorites are held under: the device's own token, then the account once they sign in (see FavoriteService::resolveHolder())
class PlayService
{
    // No thing is raised past this in one go: a child does not hear one story more often than that before a parent connects the app
    private const int RAISE_MAX = 500;

    // What an app says it played is held apart from the public total, in rows of its own that totals() never reads for the same owner type: "app:book" beside "book"
    public const string APP_TOTAL_PREFIX = 'app:';

    public function __construct(
        private readonly PlayRepository $playRepository,
        private readonly PlayTotalRepository $playTotalRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Writes one play down: always in the total, and under its holder when there is one - a listener nobody knows is counted and nothing more. Answers with how many times this holder played each thing
    /** @return array<string, int> "book:12" => 3 */
    public function record(string $ownerType, int $ownerId, ?string $holder): array
    {
        $this->playTotalRepository->increment($ownerType, $ownerId);

        return $this->hold($ownerType, $ownerId, $holder);
    }

    // The same play on the word of an app, which holds no ticket: it counts its listens offline and answers to nobody on the time they took, so anyone can post one. Written down for its holder and in its own count, never in the total the pages show
    /** @return array<string, int> "book:12" => 3 */
    public function recordFromApp(string $ownerType, int $ownerId, ?string $holder): array
    {
        $this->playTotalRepository->increment(self::APP_TOTAL_PREFIX . $ownerType, $ownerId);

        return $this->hold($ownerType, $ownerId, $holder);
    }

    // The row under its holder, none for a listener nobody knows
    /** @return array<string, int> */
    private function hold(string $ownerType, int $ownerId, ?string $holder): array
    {
        if (null === $holder) {
            return [];
        }

        $this->entityManager->persist(new Play()
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
            ->setHolder($holder));
        $this->entityManager->flush();

        return $this->counts($holder);
    }

    // How many times each of these things was played, by anyone
    /**
     * @param int[] $ownerIds
     *
     * @return array<int, int> owner id => plays
     */
    public function totals(string $ownerType, array $ownerIds): array
    {
        return $this->playTotalRepository->totals($ownerType, $ownerIds);
    }

    // Counts each thing as played at least as many times as said, which is how a device used before it was connected hands its counts over to the account: said twice, it adds nothing the second time
    /** @param array<string, int> $counts "book:12" => 3 */
    public function raise(string $holder, array $counts): void
    {
        $held = $this->counts($holder);

        foreach ($counts as $key => $count) {
            [$ownerType, $ownerId] = explode(':', $key);
            for ($missing = min($count, self::RAISE_MAX) - ($held[$key] ?? 0); $missing > 0; --$missing) {
                $this->entityManager->persist(new Play()
                    ->setOwnerType($ownerType)
                    ->setOwnerId((int) $ownerId)
                    ->setHolder($holder));
            }
        }

        $this->entityManager->flush();
    }

    /** @return array<string, int> */
    public function counts(string $holder): array
    {
        return $this->playRepository->countsByHolder($holder);
    }

    // What a device played before its holder signed in becomes the account's
    public function merge(string $token, string $holder): void
    {
        if ($token !== $holder) {
            $this->playRepository->moveHolder($token, $holder);
        }
    }
}
