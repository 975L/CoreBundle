<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\UiBundle\Repository\FavoriteRepository;
use c975L\UiBundle\Repository\PlayRepository;
use c975L\UiBundle\Service\FavoriteService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// An account deleted or anonymized loses what it put aside and played: both are held under "u<id>", a key no foreign key ties to the account, so nothing would erase them otherwise (see FavoriteService::holderOf())
#[AsEventListener]
class HolderAnonymizedListener
{
    public function __construct(
        private readonly FavoriteRepository $favoriteRepository,
        private readonly PlayRepository $playRepository,
    ) {
    }

    // Erases the list and the plays of the account just anonymized, one never saved holding none
    public function __invoke(UserAnonymizedEvent $event): void
    {
        if (null === $event->user->getId()) {
            return;
        }

        $holder = FavoriteService::holderOf($event->user);
        $this->favoriteRepository->deleteByHolder($holder);
        $this->playRepository->deleteByHolder($holder);
    }
}
