<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Favorite;
use PHPUnit\Framework\TestCase;

class FavoriteTest extends TestCase
{
    public function testItHoldsWhatWasSetOnIt(): void
    {
        $favorite = new Favorite()
            ->setOwnerType('shop_product')
            ->setOwnerId(39)
            ->setHolder('u7')
        ;

        $this->assertNull($favorite->getId());
        $this->assertSame('shop_product', $favorite->getOwnerType());
        $this->assertSame(39, $favorite->getOwnerId());
        $this->assertSame('u7', $favorite->getHolder());
    }

    // Stamped by the constructor rather than left to the caller: the list is drawn newest first and every row is written by FavoriteService::toggle(), which has nothing else to say about when
    public function testItIsStampedOnCreation(): void
    {
        $this->assertEqualsWithDelta(
            new \DateTimeImmutable()->getTimestamp(),
            new Favorite()->getCreatedAt()->getTimestamp(),
            5
        );
    }

    // A list rebuilt from an export keeps the order it was built in, which the stamp alone carries
    public function testItsStampIsTheCallersToSet(): void
    {
        $stamped = new \DateTimeImmutable('2026-08-01 09:00:00');

        $this->assertSame($stamped, new Favorite()->setCreatedAt($stamped)->getCreatedAt());
    }

    // Names the thing rather than the row: what an admin screen listing entries has to read
    public function testItReadsAsTheThingItHolds(): void
    {
        $favorite = new Favorite()->setOwnerType('book')->setOwnerId(42);

        $this->assertSame('book#42', (string) $favorite);
    }
}
