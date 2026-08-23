<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\ReviewReplyPublisherInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Registry\ReviewReplyRegistry;
use PHPUnit\Framework\TestCase;

// Which publisher, if any, speaks for a review's platform - empty on every site bringing none, which is the ordinary case
class ReviewReplyRegistryTest extends TestCase
{
    // A review written here is answered here, and the answer goes nowhere else
    public function testALocalReviewIsAnswerableWithNoPublisherAtAll(): void
    {
        $this->assertTrue(new ReviewReplyRegistry()->supports(new Review()));
    }

    public function testALocalReviewIsHandedToNoPublisher(): void
    {
        $registry = new ReviewReplyRegistry();
        $registry->addProvider($this->publisher(false));

        // Not a failure but the whole point: the row was already stored by the caller, and the publisher's own never() expectation is what witnesses it
        $registry->publish(new Review());

        $this->assertTrue($registry->supports(new Review()));
    }

    // A site bringing no platform still gets a moderation screen that knows there is nothing to push, rather than a missing service
    public function testAnImportedReviewIsNotAnswerableWithoutAPublisher(): void
    {
        $this->assertFalse(new ReviewReplyRegistry()->supports($this->imported()));
    }

    public function testTheAnswerGoesToThePublisherClaimingTheReview(): void
    {
        $review = $this->imported();

        $claiming = $this->createMock(ReviewReplyPublisherInterface::class);
        $claiming->method('supports')->willReturn(true);
        $claiming->expects($this->once())->method('publish')->with($review);

        $registry = new ReviewReplyRegistry();
        $registry->addProvider($this->publisher(false));
        $registry->addProvider($claiming);

        $this->assertTrue($registry->supports($review));
        $registry->publish($review);
    }

    private function imported(): Review
    {
        return new Review()->setSource('google')->setExternalId('r1');
    }

    private function publisher(bool $supports): ReviewReplyPublisherInterface
    {
        $publisher = $this->createMock(ReviewReplyPublisherInterface::class);
        $publisher->method('supports')->willReturn($supports);
        $publisher->expects($this->never())->method('publish');

        return $publisher;
    }
}
