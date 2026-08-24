<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Enum;

use c975L\UiBundle\Enum\ReviewStatus;
use PHPUnit\Framework\TestCase;

// The badge the moderation screen paints a status in, and the three shapes EasyAdmin hands that status over as
class ReviewStatusTest extends TestCase
{
    // What is waiting has to be the one that catches the eye
    public function testWhatIsWaitingIsTheOneThatCatchesTheEye(): void
    {
        $this->assertSame('warning', ReviewStatus::Pending->badge());
        $this->assertSame('success', ReviewStatus::Published->badge());
        $this->assertSame('secondary', ReviewStatus::Rejected->badge());
    }

    // The case name is what ChoiceConfigurator flattens a translatable enum to - which this one is, so this is the shape the screen actually gets
    public function testTheBadgeIsFoundFromTheCaseName(): void
    {
        $this->assertSame('success', ReviewStatus::badgeFor('Published'));
    }

    // The value, which is what a non-translatable enum would be flattened to
    public function testTheBadgeIsFoundFromTheValue(): void
    {
        $this->assertSame('secondary', ReviewStatus::badgeFor('rejected'));
    }

    public function testTheBadgeIsFoundFromTheCaseItself(): void
    {
        $this->assertSame('warning', ReviewStatus::badgeFor(ReviewStatus::Pending));
    }

    // A badge is decoration: a status nobody recognises is not worth a 500 on the screen whose whole job is to show what is waiting
    public function testAnythingElseFallsBackOnPending(): void
    {
        foreach (['', 'unknown', null, 42, ['published']] as $value) {
            $this->assertSame('warning', ReviewStatus::badgeFor($value));
        }
    }
}
