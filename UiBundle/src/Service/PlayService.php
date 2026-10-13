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
use Doctrine\ORM\EntityManagerInterface;

// What a visitor listened to or watched to its end, counted under the key their favorites are held under: the device's own token, then the account once they sign in (see FavoriteService::resolveHolder())
class PlayService
{
    public function __construct(
        private readonly PlayRepository $playRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Writes one play down, and answers with how many times this holder played each thing
    /** @return array<string, int> "book:12" => 3 */
    public function record(string $ownerType, int $ownerId, string $holder): array
    {
        $this->entityManager->persist(new Play()
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
            ->setHolder($holder));
        $this->entityManager->flush();

        return $this->counts($holder);
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
