<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Rating;
use PHPUnit\Framework\TestCase;

class RatingTest extends TestCase
{
    public function testItHoldsWhatWasSetOnIt(): void
    {
        $rating = new Rating()
            ->setOwnerType('book')
            ->setOwnerId(42)
            ->setVoter('u7')
            ->setValue(4)
        ;

        $this->assertNull($rating->getId());
        $this->assertSame('book', $rating->getOwnerType());
        $this->assertSame(42, $rating->getOwnerId());
        $this->assertSame('u7', $rating->getVoter());
        $this->assertSame(4, $rating->getValue());
    }

    // Stamped by the constructor rather than left to the caller: every row is written by RatingService::vote(), which has nothing else to say about when
    public function testItIsStampedOnCreation(): void
    {
        $this->assertEqualsWithDelta(
            new \DateTimeImmutable()->getTimestamp(),
            new Rating()->getCreatedAt()->getTimestamp(),
            5
        );
    }

    // Names the thing rather than the row: what an admin screen listing votes has to read
    public function testItReadsAsTheThingItRates(): void
    {
        $rating = new Rating()->setOwnerType('book')->setOwnerId(42)->setValue(4);

        $this->assertSame('book#42: 4', (string) $rating);
    }
}
