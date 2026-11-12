<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\UiBundle\Listener\HolderAnonymizedListener;
use c975L\UiBundle\Repository\FavoriteRepository;
use c975L\UiBundle\Repository\PlayRepository;
use PHPUnit\Framework\TestCase;

class HolderAnonymizedListenerTest extends TestCase
{
    // The account's list and plays are both erased, held under its key
    public function testTheAccountsListAndPlaysAreErased(): void
    {
        $favorites = $this->createMock(FavoriteRepository::class);
        $favorites->expects($this->once())->method('deleteByHolder')->with('u9');
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects($this->once())->method('deleteByHolder')->with('u9');

        new HolderAnonymizedListener($favorites, $plays)(new UserAnonymizedEvent($this->user(9)));
    }

    // An account never saved holds nothing, and erasing under "u" would match no one's key
    public function testAnUnsavedAccountErasesNothing(): void
    {
        $favorites = $this->createMock(FavoriteRepository::class);
        $favorites->expects($this->never())->method('deleteByHolder');
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects($this->never())->method('deleteByHolder');

        new HolderAnonymizedListener($favorites, $plays)(new UserAnonymizedEvent($this->user(null)));
    }

    private function user(?int $id): InactivityAwareInterface
    {
        $user = $this->createStub(InactivityAwareInterface::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
